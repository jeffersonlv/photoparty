<?php

namespace App\Console\Commands;

use App\Enums\AccountStatus;
use App\Models\Account;
use App\Models\AuditLog;
use Illuminate\Console\Command;

/**
 * Encerra o período de teste das contas cujo prazo de 3 h acabou (trial -> trial_ended).
 */
class ExpireTrials extends Command
{
    protected $signature = 'accounts:expire-trials';

    protected $description = 'Marca como trial_ended as contas cujo período de teste terminou';

    public function handle(): int
    {
        $count = 0;

        Account::query()
            ->where('status', AccountStatus::Trial->value)
            ->whereNotNull('trial_ends_at')
            ->where('trial_ends_at', '<=', now())
            ->chunkById(200, function ($accounts) use (&$count) {
                foreach ($accounts as $account) {
                    $account->status = AccountStatus::TrialEnded;
                    $account->save();
                    AuditLog::record('account.trial_ended', $account);
                    $count++;
                }
            });

        $this->info("Testes encerrados: {$count}");

        return self::SUCCESS;
    }
}
