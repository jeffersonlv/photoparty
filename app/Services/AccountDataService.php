<?php

namespace App\Services;

use App\Models\Account;
use App\Models\AuditLog;
use App\Models\Event;
use App\Models\Export;
use App\Models\Frame;
use App\Models\Photo;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/**
 * Direitos do titular da conta (organizador): exportação (acesso/portabilidade) e exclusão.
 *
 * Toda consulta é amarrada ao account_id da conta passada (além do global scope do usuário
 * logado): a exportação nunca inclui dados de outra conta.
 */
class AccountDataService
{
    /**
     * JSON de portabilidade: conta, usuários, eventos e metadados das fotos (sem arquivos,
     * sem device_hash, sem caminhos internos e sem tokens).
     *
     * @return array<string, mixed>
     */
    public function export(Account $account): array
    {
        $id = $account->id;
        $events = Event::withoutGlobalScopes()->where('account_id', $id)->orderBy('id')->get();
        $photos = Photo::withoutGlobalScopes()->where('account_id', $id)
            ->where('status', '!=', Photo::STATUS_DELETED)
            ->orderBy('id')->get()->groupBy('event_id');
        $frames = Frame::withoutGlobalScopes()->where('account_id', $id)->orderBy('id')->get();

        return [
            'format' => 'photo-party-account-export',
            'version' => 1,
            'generated_at' => now()->toIso8601String(),
            'note' => __('account.export_note'),
            'account' => [
                'uuid' => $account->uuid,
                'name' => $account->name,
                'status' => $account->status->value,
                'plan' => $account->plan?->name,
                'trial_used_at' => $account->trial_used_at?->toIso8601String(),
                'trial_ends_at' => $account->trial_ends_at?->toIso8601String(),
                'branding' => $account->branding,
                'created_at' => $account->created_at?->toIso8601String(),
            ],
            'users' => User::query()->where('account_id', $id)->orderBy('id')->get()->map(fn (User $u) => [
                'name' => $u->name,
                'email' => $u->email,
                'role' => $u->role->value,
                'email_verified_at' => $u->email_verified_at?->toIso8601String(),
                'created_at' => $u->created_at?->toIso8601String(),
            ])->all(),
            'events' => $events->map(fn (Event $e) => [
                'uuid' => $e->uuid,
                'name' => $e->name,
                'slug' => $e->slug,
                'status' => $e->computeStatus()->value,
                'starts_at' => $e->starts_at?->toIso8601String(),
                'ends_at' => $e->ends_at?->toIso8601String(),
                'published_at' => $e->published_at?->toIso8601String(),
                'closed_at' => $e->closed_at?->toIso8601String(),
                'expires_at' => $e->expires_at?->toIso8601String(),
                'declared_at' => $e->declared_at?->toIso8601String(),
                'purged_at' => $e->purged_at?->toIso8601String(),
                'is_trial' => $e->is_trial,
                'moderation' => $e->moderation,
                'welcome_message' => $e->welcome_message,
                'ask_guest_name' => $e->ask_guest_name,
                'max_photos_per_guest' => $e->max_photos_per_guest,
                'enabled_filters' => $e->enabled_filters,
                'screen_settings' => $e->screen_settings,
                'language' => $e->language,
                'created_at' => $e->created_at?->toIso8601String(),
                'photos' => ($photos[$e->id] ?? collect())->map(fn (Photo $p) => [
                    'uuid' => $p->uuid,
                    'status' => $p->status,
                    'orientation' => $p->orientation,
                    'filter' => $p->filter,
                    'guest_name' => $p->guest_name,
                    'guest_texts' => $p->guest_texts,
                    'bytes' => $p->bytes,
                    'consent_version' => $p->consent_version,
                    'consented_at' => $p->consented_at?->toIso8601String(),
                    'approved_at' => $p->approved_at?->toIso8601String(),
                    'created_at' => $p->created_at?->toIso8601String(),
                ])->values()->all(),
            ])->all(),
            'frames' => $frames->map(fn (Frame $f) => [
                'uuid' => $f->uuid,
                'name' => $f->name,
                'type' => $f->type,
                'created_at' => $f->created_at?->toIso8601String(),
            ])->all(),
            'exports' => Export::withoutGlobalScopes()->where('account_id', $id)->orderBy('id')->get()->map(fn (Export $x) => [
                'uuid' => $x->uuid,
                'status' => $x->status,
                'created_at' => $x->created_at?->toIso8601String(),
                'expires_at' => $x->expires_at?->toIso8601String(),
            ])->all(),
        ];
    }

    /**
     * Apaga a conta e tudo dela: arquivos do tenant (fotos, prévias, ZIPs, molduras) e banco
     * (eventos, fotos, molduras, exportações, usuários via cascade). Registra em audit_logs
     * sem dados pessoais e anonimiza os registros de auditoria antigos da conta.
     */
    public function delete(Account $account): void
    {
        $id = $account->id;
        $uuid = $account->uuid;
        $userIds = User::query()->where('account_id', $id)->pluck('id')->all();
        $emails = User::query()->where('account_id', $id)->pluck('email')->all();
        $counts = [
            'events' => Event::withoutGlobalScopes()->where('account_id', $id)->count(),
            'photos' => Photo::withoutGlobalScopes()->where('account_id', $id)->count(),
            'users' => count($userIds),
        ];

        DB::transaction(function () use ($account, $id, $userIds, $emails) {
            // Auditoria antiga da conta: sem IP nem meta (que pode ter nomes de eventos/molduras).
            AuditLog::query()->where('account_id', $id)->update(['meta' => null, 'ip' => null]);
            AuditLog::query()->whereIn('user_id', $userIds)->update(['meta' => null, 'ip' => null]);

            if (Schema::hasTable('sessions')) {
                DB::table('sessions')->whereIn('user_id', $userIds)->delete();
            }

            if ($emails !== [] && Schema::hasTable('password_reset_tokens')) {
                DB::table('password_reset_tokens')->whereIn('email', $emails)->delete();
            }

            // Ordem explícita (não depende só do cascade do banco).
            Photo::withoutGlobalScopes()->where('account_id', $id)->delete();
            Export::withoutGlobalScopes()->where('account_id', $id)->delete();
            $eventIds = Event::withoutGlobalScopes()->where('account_id', $id)->pluck('id');
            DB::table('event_frame')->whereIn('event_id', $eventIds)->delete();
            Event::withoutGlobalScopes()->where('account_id', $id)->delete();
            $frameIds = Frame::withoutGlobalScopes()->where('account_id', $id)->pluck('id');
            DB::table('event_frame')->whereIn('frame_id', $frameIds)->delete();
            Frame::withoutGlobalScopes()->where('account_id', $id)->delete();
            User::query()->where('account_id', $id)->delete();
            Account::query()->whereKey($id)->delete();
        });

        // Arquivos depois do commit: se o banco falhar, nada some do disco.
        Storage::disk('local')->deleteDirectory('tenants/'.$uuid);

        // Registro sem dados pessoais (nem nome, nem e-mail, nem IP).
        AuditLog::query()->create([
            'user_id' => null,
            'account_id' => null,
            'action' => 'account.deleted',
            'subject_type' => Account::class,
            'subject_id' => $id,
            'meta' => ['account_uuid' => $uuid] + $counts,
            'ip' => null,
        ]);
    }
}
