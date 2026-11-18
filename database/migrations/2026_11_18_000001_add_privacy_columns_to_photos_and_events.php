<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * LGPD/GDPR:
 * - photos.consent_version / consented_at: prova do consentimento do convidado no envio.
 * - events.declared_at: declaração do organizador (autorização para coletar imagens, inclusive de menores).
 * - events.purged_at: arquivos apagados pelo prazo de retenção (privacy:enforce-retention).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('photos', function (Blueprint $table) {
            $table->string('consent_version', 20)->nullable()->after('device_hash');
            $table->timestamp('consented_at')->nullable()->after('consent_version');
        });

        Schema::table('events', function (Blueprint $table) {
            $table->timestamp('declared_at')->nullable()->after('expires_at');
            $table->timestamp('purged_at')->nullable()->after('declared_at');
        });
    }

    public function down(): void
    {
        Schema::table('photos', function (Blueprint $table) {
            $table->dropColumn(['consent_version', 'consented_at']);
        });

        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn(['declared_at', 'purged_at']);
        });
    }
};
