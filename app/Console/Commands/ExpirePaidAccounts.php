<?php

namespace App\Console\Commands;

use App\Enums\AccountStatus;
use App\Models\Account;
use App\Models\AuditLog;
use Illuminate\Console\Command;

/**
 * Conta active com paid_until vencido vira past_due.
 */
class ExpirePaidAccounts extends Command
{
    protected $signature = 'accounts:expire-paid';

    protected $description = 'Marca como past_due as contas ativas cujo pagamento venceu (paid_until no passado)';

    public function handle(): int
    {
        $count = 0;

        Account::query()
            ->where('status', AccountStatus::Active->value)
            ->whereNotNull('paid_until')
            ->where('paid_until', '<', now())
            ->chunkById(200, function ($accounts) use (&$count) {
                foreach ($accounts as $account) {
                    $account->status = AccountStatus::PastDue;
                    $account->save();
                    AuditLog::record('account.past_due', $account, ['paid_until' => $account->paid_until->toIso8601String()]);
                    $count++;
                }
            });

        $this->info("Contas vencidas: {$count}");

        return self::SUCCESS;
    }
}
