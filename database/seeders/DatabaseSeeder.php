<?php

namespace Database\Seeders;

use App\Enums\AccountStatus;
use App\Enums\UserRole;
use App\Models\Account;
use App\Models\Event;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Dados de demonstração (apenas ambiente local).
 *
 * - Admin: admin@photoparty.test (sem conta/tenant).
 * - Conta A (ativa, plano Profissional): owner-a@demo.test e moderator-a@demo.test.
 * - Conta B (em teste): owner-b@demo.test.
 *
 * Senha de todos: SEED_PASSWORD do .env (padrão "Password!123"). Nunca rode em produção.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->environment('production')) {
            $this->command?->error('DatabaseSeeder de demonstração não roda em produção. Use --class=PlanSeeder e --class=HomeSeeder.');

            return;
        }

        // No console não há usuário autenticado: o global scope não filtra e
        // account_id é definido explicitamente em cada registro.
        $this->call(PlanSeeder::class);
        $this->call(HomeSeeder::class);
        // Antes dos eventos: evento novo ativa os modelos do sistema por padrão.
        $this->call(FrameSeeder::class);

        $password = env('SEED_PASSWORD', 'Password!123');

        $this->user('Administrador', 'admin@photoparty.test', $password, UserRole::Admin, null);

        // Conta A: ativa
        $accountA = $this->account('Estúdio Demo A', AccountStatus::Active, Plan::query()->where('slug', 'pro')->value('id'));
        $this->user('Ana (Conta A)', 'owner-a@demo.test', $password, UserRole::Owner, $accountA);
        $this->user('Marcos (moderador A)', 'moderator-a@demo.test', $password, UserRole::Moderator, $accountA);

        $this->event($accountA, 'Casamento Ana & Leo', now()->addDays(7)->setTime(19, 0), 5, published: true);
        $this->event($accountA, 'Festa da firma', now()->addDays(20)->setTime(21, 0), 4, published: false);

        // Conta B: em teste
        $accountB = $this->account('Cabine Demo B', AccountStatus::Trial, null);
        $this->user('Bruno (Conta B)', 'owner-b@demo.test', $password, UserRole::Owner, $accountB);

        $this->event($accountB, 'Aniversário (teste)', now()->addHour()->startOfHour(), 2, published: false, trial: true);

        $this->command?->info('Demo: admin@photoparty.test, owner-a@demo.test, moderator-a@demo.test, owner-b@demo.test (senha: SEED_PASSWORD).');
    }

    private function account(string $name, AccountStatus $status, ?int $planId): Account
    {
        $account = Account::query()->firstOrNew(['name' => $name]);
        $account->status = $status;
        $account->plan_id = $planId;
        $account->save();

        return $account;
    }

    private function user(string $name, string $email, string $password, UserRole $role, ?Account $account): User
    {
        $user = User::query()->firstOrNew(['email' => $email]);
        $user->name = $name;
        $user->password = $password;
        $user->role = $role;
        $user->account_id = $account?->id;
        $user->email_verified_at ??= now();
        $user->save();

        return $user;
    }

    private function event(Account $account, string $name, $startsAt, int $hours, bool $published, bool $trial = false): Event
    {
        $event = new Event([
            'name' => $name,
            'starts_at' => $startsAt,
            'ends_at' => $startsAt->copy()->addHours($hours),
            'moderation' => 'pre',
            'welcome_message' => 'Bem-vindo! Tire uma foto e ela aparece no telão.',
            'enabled_filters' => config('photoparty.filters'),
            'screen_settings' => config('photoparty.screen_defaults'),
            'language' => 'pt_BR',
        ]);
        $event->account_id = $account->id;
        $event->is_trial = $trial;

        if ($published) {
            $event->published_at = now();
            $event->expires_at = $event->calculateExpiresAt($account->retentionDays());
        }

        $event->refreshStatus();
        $event->save();

        return $event;
    }
}
