<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Vencimento do plano pago (definido manualmente pelo admin). Conta `active` com
 * paid_until no passado vira `past_due` pelo comando accounts:expire-paid.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('accounts', function (Blueprint $table): void {
            $table->timestamp('paid_until')->nullable()->after('trial_ends_at');
            $table->index('paid_until');
        });
    }

    public function down(): void
    {
        Schema::table('accounts', function (Blueprint $table): void {
            $table->dropIndex(['paid_until']);
            $table->dropColumn('paid_until');
        });
    }
};
