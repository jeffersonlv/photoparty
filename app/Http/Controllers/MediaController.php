<?php

namespace App\Http\Controllers;

use App\Enums\EventStatus;
use App\Enums\AccountStatus;
use App\Models\Account;
use App\Models\AuditLog;
use App\Models\Event;
use App\Models\Photo;
use App\Services\Photos\PhotoVariants;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Entrega de imagens (SPEC 4 e 13). Fotos nunca ficam em public/.
 *
 * - media.show   GET /media/{photo}/{variant}           usuário logado + PhotoPolicy
 * - media.signed GET /media/signed/{photo}/{variant}    URL assinada temporária (telão), só aprovadas
 *
 * {photo} é o uuid; a resolução é manual (sem route model binding) para que todo 404
 * passe pelo GuardMediaNotFound (rate limit + log de rajadas).
 * Regra de plano (PhotoVariants): conta não-active só recebe a prévia (thumb => preview 200 px,
 * original => 404). No telão (signed) vale o comportamento antigo: trial_ended não recebe original.
 */
class MediaController extends Controller
{
    public function __construct(private readonly PhotoVariants $variants)
    {
    }

    public function show(Request $request, string $photo, string $variant): BinaryFileResponse
    {
        // Global scope da conta: foto de outro tenant nem é encontrada (404).
        $model = Photo::query()->where('uuid', $photo)->first();

        abort_if($model === null, 404);

        $this->authorize('view', $model);

        if ($request->user()->isAdmin()) {
            AuditLog::record('media.admin_view', $model, ['variant' => $variant]);
        }

        $account = Account::query()->find($model->account_id);

        abort_if($account === null, 404);

        $resolved = $this->variants->resolve($account, $variant);

        abort_if($resolved === null, 404);

        $path = $resolved === 'preview' ? $this->variants->ensurePreview($model) : $model->pathFor($resolved);

        return $this->serve($model, $account, $path);
    }

    public function signed(string $photo, string $variant): BinaryFileResponse
    {
        // Sem global scope: a autorização é a assinatura (middleware signed). Mesmo
        // assim só serve foto aprovada de evento não expirado.
        $model = Photo::withoutGlobalScopes()
            ->where('uuid', $photo)
            ->where('status', 'approved')
            ->first();

        abort_if($model === null, 404);

        $event = Event::withoutGlobalScopes()->find($model->event_id);

        abort_if($event === null || $event->computeStatus() === EventStatus::Expired, 404);

        $account = Account::query()->find($model->account_id);

        abort_if($account === null, 404);

        // Telão inalterado: teste encerrado sem assinatura => só miniatura.
        abort_if($variant === 'original' && $account->status === AccountStatus::TrialEnded, 404);

        return $this->serve($model, $account, $model->pathFor($variant));
    }

    private function serve(Photo $photo, Account $account, ?string $path): BinaryFileResponse
    {
        $disk = Storage::disk('local');

        // Defesa extra: o caminho tem de estar dentro da pasta do tenant.
        abort_if(
            $path === null
            || ! str_starts_with($path, $account->storagePath().'/')
            || str_contains($path, '..')
            || ! $disk->exists($path),
            404,
        );

        $response = new BinaryFileResponse($disk->path($path), 200, [
            'Content-Type' => 'image/jpeg',
            'Content-Disposition' => 'inline; filename="'.$photo->uuid.'.jpg"',
            'X-Content-Type-Options' => 'nosniff',
        ], false);

        // Cache-Control: private, max-age=600 (nunca em cache compartilhado/proxy).
        return $response->setPrivate()->setMaxAge(600);
    }
}
