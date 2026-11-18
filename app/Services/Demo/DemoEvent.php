<?php

namespace App\Services\Demo;

use App\Enums\AccountStatus;
use App\Models\Account;
use App\Models\Event;
use App\Models\Photo;
use App\Models\Plan;
use App\Models\SiteSetting;
use App\Services\QrCodeGenerator;
use Illuminate\Contracts\Session\Session;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Evento de demonstração da página inicial (conta interna "Photo Party Demo").
 *
 * O evento é identificado SOMENTE por events.is_demo (nunca pelo nome da conta, que um
 * organizador poderia copiar). Sem geolocalização, moderação pós, janela sempre cobrindo "agora".
 * Privacidade: as fotos são públicas na home e apagadas de hora em hora (purgePhotos).
 */
class DemoEvent
{
    public const PLAN_SLUG = 'demo';

    /** site_settings.demo_live: '1' (padrão) ou '0'. */
    public const SETTING_KEY = 'demo_live';

    public function __construct(private readonly QrCodeGenerator $qr)
    {
    }

    /** Toggle do admin (hero): mostrar a demonstração ao vivo. Padrão: ligado. */
    public static function enabled(): bool
    {
        return SiteSetting::query()->where('key', self::SETTING_KEY)->value('value') !== '0';
    }

    /** Pareamento: chave na sessão do visitante da home e validade (1 h). */
    public const PAIR_SESSION = 'demo.pair';

    public const PAIR_TTL_MINUTES = 60;

    public static function hashPair(string $code): string
    {
        return hash('sha256', $code);
    }

    /** Código de pareamento válido do visitante (gera na primeira visita; reaproveita por 1 h). */
    public function pairCode(Session $session): string
    {
        $stored = $session->get(self::PAIR_SESSION);

        if (is_array($stored) && is_string($stored['code'] ?? null) && ($stored['expires_at'] ?? 0) > now()->getTimestamp()) {
            return $stored['code'];
        }

        $code = Str::random(32);
        $expires = now()->addMinutes(self::PAIR_TTL_MINUTES);
        $session->put(self::PAIR_SESSION, ['code' => $code, 'expires_at' => $expires->getTimestamp()]);
        // O celular não tem a sessão do visitante: o servidor só confere se o código foi emitido (guarda o hash).
        Cache::put('demo.pair.'.self::hashPair($code), 1, $expires);

        return $code;
    }

    /** Hash do código da sessão, se ainda válido; senão null. */
    public function sessionPairHash(Session $session): ?string
    {
        $stored = $session->get(self::PAIR_SESSION);

        if (! is_array($stored) || ! is_string($stored['code'] ?? null) || ($stored['expires_at'] ?? 0) <= now()->getTimestamp()) {
            return null;
        }

        return self::hashPair($stored['code']);
    }

    /** Código vindo do QR (?d=): formato certo e emitido há menos de 1 h => hash; senão null. */
    public function resolvePair(mixed $code): ?string
    {
        if (! is_string($code) || strlen($code) !== 32 || ! ctype_alnum($code)) {
            return null;
        }

        $hash = self::hashPair($code);

        return Cache::has('demo.pair.'.$hash) ? $hash : null;
    }

    public function event(): ?Event
    {
        return Event::withoutGlobalScopes()->where('is_demo', true)->orderBy('id')->first();
    }

    /** O slug informado é o do evento de demonstração? (sem consultar o banco: usado em rate limit) */
    public static function isDemoSlug(mixed $slug): bool
    {
        return is_string($slug) && $slug === config('photoparty.demo.slug');
    }

    /**
     * Cria (se faltar) conta, plano e evento; mantém a janela ao vivo. Idempotente.
     */
    public function ensure(): Event
    {
        return DB::transaction(function () {
            $event = $this->event();

            if ($event !== null) {
                return $this->keepLive($event);
            }

            $plan = Plan::query()->firstOrCreate(['slug' => self::PLAN_SLUG], [
                'name' => 'Demonstração (interno)',
                'price' => 0,
                'max_events_per_month' => 1,
                'max_photos_per_event' => (int) config('photoparty.demo.max_photos_per_hour'),
                'retention_days' => 1,
                'allows_custom_frames' => false,
                'allows_geofence' => false,
                'white_label' => false,
                'is_public' => false,
                'sort_order' => 99,
            ]);

            $account = new Account(['name' => (string) config('photoparty.demo.account_name')]);
            $account->status = AccountStatus::Active;
            $account->plan_id = $plan->id;
            $account->save();

            $event = new Event([
                'name' => 'Demonstração ao vivo',
                'moderation' => 'post',
                'welcome_message' => 'Tire uma foto e veja ela aparecer na página inicial do Photo Party.',
                'ask_guest_name' => false,
                'enabled_filters' => config('photoparty.filters'),
                'screen_settings' => config('photoparty.screen_defaults'),
                'language' => 'pt_BR',
                'starts_at' => now()->subDay(),
                'ends_at' => now()->addDays(2),
            ]);
            $event->slug = (string) config('photoparty.demo.slug');
            $event->account_id = $account->id;
            $event->is_demo = true;
            $event->is_trial = false;
            $event->published_at = now();
            $event->declared_at = now();
            $event->geofence_enabled = false;
            $event->expires_at = now()->addDays(30);
            $event->status = \App\Enums\EventStatus::Live;
            $event->save();

            return $event;
        });
    }

    /**
     * Janela de ontem até depois de amanhã: cobre o dia inteiro mesmo se o cron atrasar.
     */
    public function keepLive(Event $event): Event
    {
        $event->forceFill([
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addDays(2),
            'expires_at' => now()->addDays(30),
            'published_at' => $event->published_at ?? now(),
            'closed_at' => null,
            'purged_at' => null,
            'is_trial' => false,
            'moderation' => 'post',
            'geofence_enabled' => false,
            'geofence_lat' => null,
            'geofence_lng' => null,
            'status' => \App\Enums\EventStatus::Live,
        ])->save();

        return $event;
    }

    /**
     * Apaga TODAS as fotos da demonstração (registros, incluindo "excluídas pelo convidado") e
     * os arquivos (original, miniatura, prévia e a pasta do evento). Nada de outro evento.
     *
     * @return int fotos apagadas
     */
    public function purgePhotos(Event $event): int
    {
        abort_unless($event->is_demo, 500);

        $disk = Storage::disk('local');
        $paths = [];

        Photo::withoutGlobalScopes()->where('event_id', $event->id)->chunkById(200, function ($photos) use (&$paths) {
            foreach ($photos as $photo) {
                array_push($paths, ...array_filter([$photo->original_path, $photo->thumb_path, $photo->previewPath()]));
            }
        });

        $count = Photo::withoutGlobalScopes()->where('event_id', $event->id)->delete();

        $disk->delete(array_values(array_filter($paths, fn (string $p) => str_starts_with($p, 'tenants/') && ! str_contains($p, '..'))));

        $account = Account::query()->find($event->account_id);

        if ($account !== null) {
            $disk->deleteDirectory($account->storagePath().'/events/'.$event->uuid);
        }

        return $count;
    }

    /**
     * Últimas fotos aprovadas da demonstração enviadas por QUEM tem este pareamento (e só dela), mais novas primeiro.
     *
     * @return \Illuminate\Support\Collection<int, Photo>
     */
    public function recentPhotos(Event $event, string $pairHash, ?int $limit = null)
    {
        return Photo::withoutGlobalScopes()
            ->where('event_id', $event->id)
            ->where('account_id', $event->account_id)
            ->where('demo_pair', $pairHash)
            ->where('status', 'approved')
            ->whereNotNull('approved_at')
            ->orderByDesc('approved_at')
            ->orderByDesc('id')
            ->limit($limit ?? (int) config('photoparty.demo.recent_photos'))
            ->get();
    }

    /**
     * Dados da demonstração na home: link do convidado (mesmo host da página), QR (data URI SVG,
     * gerado localmente e em cache) e URL do polling. Null se o evento de demo não existe/está fora do ar.
     *
     * @return array{guestUrl: string, qr: string, pollUrl: string, pollMs: int, max: int}|null
     */
    public function heroData(): ?array
    {
        $event = self::enabled() ? $this->event() : null;

        if ($event === null || ! $event->isLive() || $event->uploadToken() === null) {
            return null;
        }

        // Demo privada por visitante: o QR leva o código de pareamento da sessão (?d=). Sem cache do QR.
        $guestUrl = route('guest.show', ['slug' => $event->slug, 't' => $event->uploadToken(), 'd' => $this->pairCode(app('session.store'))]);

        $qr = 'data:image/svg+xml;base64,'.base64_encode($this->qr->render($guestUrl, 'svg'));

        return [
            'guestUrl' => $guestUrl,
            'qr' => $qr,
            'pollUrl' => route('demo.photos', [], false),
            'pollMs' => 5000,
            'max' => (int) config('photoparty.demo.recent_photos'),
        ];
    }
}
