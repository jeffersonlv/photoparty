<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Event;
use App\Models\Photo;
use Illuminate\Support\Facades\DB;

/**
 * Moderação de fotos (módulo 6). Só mexe em fotos do evento informado: uuids de outros
 * eventos/contas simplesmente não são encontrados e são ignorados.
 *
 * Transições:
 *  - approve: pending|rejected -> approved  (rejected -> approved = "voltar ao telão")
 *  - reject:  pending|approved -> rejected  (approved -> rejected = "tirar do telão")
 * Cada foto alterada gera uma linha em audit_logs.
 */
class PhotoModerator
{
    public const ACTIONS = ['approve', 'reject'];

    /**
     * @param  array<int, string>  $uuids
     * @return array{changed: int, ignored: int}
     */
    public function apply(Event $event, array $uuids, string $action): array
    {
        $uuids = array_values(array_unique($uuids));
        $target = $action === 'approve' ? 'approved' : 'rejected';
        $from = $action === 'approve' ? ['pending', 'rejected'] : ['pending', 'approved'];

        $changed = DB::transaction(function () use ($event, $uuids, $target, $from) {
            // Escopo do evento + (via global scope) da conta do usuário autenticado.
            $photos = Photo::query()
                ->where('event_id', $event->id)
                ->whereIn('uuid', $uuids)
                ->whereIn('status', $from)
                ->lockForUpdate()
                ->get();

            if ($photos->isEmpty()) {
                return $photos;
            }

            $now = now();
            // Strings com microssegundos: o binder do query builder cortaria a fração de um Carbon.
            // A foto que volta ao telão entra no fim do cursor (approved_at novo).
            $update = [
                'status' => $target,
                'updated_at' => $now->format('Y-m-d H:i:s.u'),
                'approved_at' => $target === 'approved' ? $now->format('Y-m-d H:i:s.u') : null,
            ];

            Photo::query()->whereIn('id', $photos->modelKeys())->update($update);

            foreach ($photos as $photo) {
                AuditLog::record($this->auditAction($photo->status, $target), $photo, [
                    'event' => $event->uuid,
                    'photo' => $photo->uuid,
                    'from' => $photo->status,
                    'to' => $target,
                ]);
            }

            return $photos;
        });

        return ['changed' => $changed->count(), 'ignored' => count($uuids) - $changed->count()];
    }

    private function auditAction(string $from, string $to): string
    {
        return match (true) {
            $from === 'pending' && $to === 'approved' => 'photo.approved',
            $from === 'pending' && $to === 'rejected' => 'photo.rejected',
            $from === 'approved' => 'photo.removed_from_screen',
            default => 'photo.restored_to_screen',
        };
    }
}
