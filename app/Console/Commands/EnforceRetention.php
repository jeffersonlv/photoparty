<?php

namespace App\Console\Commands;

use App\Enums\AccountStatus;
use App\Models\Account;
use App\Models\AuditLog;
use App\Models\Event;
use App\Models\Export;
use App\Models\Frame;
use App\Models\Photo;
use Carbon\CarbonInterface;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Limitação de armazenamento e minimização (LGPD art. 15-16 / GDPR art. 5(1)(c)(e)).
 * Roda diariamente (routes/console.php).
 *
 * 1. Eventos com prazo de retenção vencido: apaga fotos (original, miniatura, prévia), ZIPs e a
 *    pasta do evento; apaga os registros das fotos (nome, textos, device_hash) e das exportações;
 *    mantém só o registro mínimo do evento (nome, datas) e marca purged_at.
 *    Prazo: expires_at (fim + retention_days do plano). Evento de teste de conta que não virou
 *    "active": 7 dias (trial.retention_days) depois do fim do período de teste da conta.
 * 2. Registros "excluída pelo convidado" (status deleted) mais antigos que tombstone_hours.
 * 3. Pastas órfãs no disco: tenant sem conta, evento sem registro, moldura sem registro.
 * 4. audit_logs: IP apagado depois de audit_ip_retention_days (90).
 *
 * Nunca registra nomes, e-mails ou tokens no log/saída.
 */
class EnforceRetention extends Command
{
    protected $signature = 'privacy:enforce-retention {--dry-run : Só mostra o que seria apagado}';

    protected $description = 'Aplica os prazos de retenção (fotos, ZIPs, órfãos, IPs de auditoria)';

    public function handle(): int
    {
        $dry = (bool) $this->option('dry-run');
        $now = now();

        $events = $this->purgeExpiredEvents($now, $dry);
        $tombstones = $this->purgeTombstones($now, $dry);
        $orphans = $this->purgeOrphanDirectories($dry);
        $ips = $this->anonymizeAuditIps($now, $dry);

        $this->info(sprintf(
            '%sEventos expirados limpos: %d · registros excluídos pelo convidado: %d · pastas órfãs: %d · IPs anonimizados: %d',
            $dry ? '[dry-run] ' : '',
            $events,
            $tombstones,
            $orphans,
            $ips,
        ));

        return self::SUCCESS;
    }

    /**
     * Data a partir da qual os arquivos do evento são apagados.
     */
    public static function deadlineFor(Event $event, ?Account $account): ?CarbonInterface
    {
        if ($event->published_at === null) {
            return null;
        }

        $deadline = $event->expires_at
            ?? $event->calculateExpiresAt($account?->retentionDays() ?? (int) config('photoparty.default_retention_days'));

        // Teste encerrado sem assinatura: 7 dias após o fim do período de teste da conta (SPEC 7).
        if ($event->is_trial && $account !== null && $account->status !== AccountStatus::Active && $account->trial_ends_at !== null) {
            $trialDeadline = $account->trial_ends_at->copy()->addDays((int) config('photoparty.trial.retention_days'));

            if ($trialDeadline->greaterThan($deadline)) {
                $deadline = $trialDeadline;
            }
        }

        return $deadline;
    }

    private function purgeExpiredEvents(CarbonInterface $now, bool $dry): int
    {
        $count = 0;
        $disk = Storage::disk('local');

        Event::withoutGlobalScopes()
            ->whereNotNull('published_at')
            ->whereNull('purged_at')
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '<=', $now))
            ->chunkById(100, function ($events) use ($now, $dry, $disk, &$count) {
                foreach ($events as $event) {
                    $account = Account::query()->find($event->account_id);
                    $deadline = self::deadlineFor($event, $account);

                    if ($deadline === null || $deadline->greaterThan($now)) {
                        continue;
                    }

                    $count++;

                    if ($dry) {
                        continue;
                    }

                    $this->purgeEvent($event, $account, $disk);
                }
            });

        return $count;
    }

    private function purgeEvent(Event $event, ?Account $account, $disk): void
    {
        $paths = [];

        Photo::withoutGlobalScopes()->where('event_id', $event->id)->chunkById(500, function ($photos) use (&$paths) {
            foreach ($photos as $photo) {
                array_push($paths, ...array_filter([$photo->original_path, $photo->thumb_path, $photo->previewPath()]));
            }
        });

        $exportPaths = Export::withoutGlobalScopes()->where('event_id', $event->id)->whereNotNull('path')->pluck('path')->all();

        $photos = DB::transaction(function () use ($event) {
            $deleted = Photo::withoutGlobalScopes()->where('event_id', $event->id)->delete();
            Export::withoutGlobalScopes()->where('event_id', $event->id)->update(['status' => 'expired', 'path' => null, 'bytes' => null]);

            Event::withoutGlobalScopes()->whereKey($event->id)->update([
                'status' => 'expired',
                'purged_at' => now(),
                // Registro mínimo: some a mensagem de boas-vindas (texto livre do organizador).
                'welcome_message' => null,
            ]);

            return $deleted;
        });

        $safe = fn (string $p) => str_starts_with($p, 'tenants/') && ! str_contains($p, '..');
        $disk->delete(array_values(array_filter([...$paths, ...$exportPaths], $safe)));

        if ($account !== null) {
            $disk->deleteDirectory($account->storagePath().'/events/'.$event->uuid);
        }

        AuditLog::query()->create([
            'account_id' => $event->account_id,
            'action' => 'event.retention_purged',
            'subject_type' => Event::class,
            'subject_id' => $event->id,
            'meta' => ['photos' => $photos],
            'ip' => null,
        ]);
    }

    private function purgeTombstones(CarbonInterface $now, bool $dry): int
    {
        $query = Photo::withoutGlobalScopes()
            ->where('status', Photo::STATUS_DELETED)
            ->where('updated_at', '<=', $now->copy()->subHours((int) config('photoparty.privacy.tombstone_hours', 24))->format('Y-m-d H:i:s'));

        return $dry ? $query->count() : $query->delete();
    }

    /**
     * Pastas em tenants/ sem registro correspondente (conta, evento ou moldura apagados).
     */
    private function purgeOrphanDirectories(bool $dry): int
    {
        $disk = Storage::disk('local');
        $count = 0;
        $uuidRe = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/';

        foreach ($disk->directories('tenants') as $tenantDir) {
            $accountUuid = basename($tenantDir);

            if (! preg_match($uuidRe, $accountUuid)) {
                continue;
            }

            $account = Account::query()->where('uuid', $accountUuid)->first();

            if ($account === null) {
                $count++;
                $dry || $disk->deleteDirectory($tenantDir);

                continue;
            }

            foreach ($disk->directories($tenantDir.'/events') as $eventDir) {
                $uuid = basename($eventDir);
                $exists = preg_match($uuidRe, $uuid) && Event::withoutGlobalScopes()
                    ->where('account_id', $account->id)->where('uuid', $uuid)->whereNull('purged_at')->exists();

                if (! $exists) {
                    $count++;
                    $dry || $disk->deleteDirectory($eventDir);
                }
            }

            foreach ($disk->directories($tenantDir.'/frames') as $frameDir) {
                $uuid = basename($frameDir);
                $exists = preg_match($uuidRe, $uuid) && Frame::withoutGlobalScopes()
                    ->where('account_id', $account->id)->where('uuid', $uuid)->exists();

                if (! $exists) {
                    $count++;
                    $dry || $disk->deleteDirectory($frameDir);
                }
            }
        }

        return $count;
    }

    private function anonymizeAuditIps(CarbonInterface $now, bool $dry): int
    {
        $query = AuditLog::query()
            ->whereNotNull('ip')
            ->where('created_at', '<=', $now->copy()->subDays((int) config('photoparty.privacy.audit_ip_retention_days', 90)));

        return $dry ? $query->count() : $query->update(['ip' => null]);
    }
}
