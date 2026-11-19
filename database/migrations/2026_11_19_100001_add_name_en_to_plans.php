<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Nome do plano em inglês. O nome em português continua em `name`;
 * Plan::displayName() escolhe conforme o idioma da página.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plans', function (Blueprint $table): void {
            $table->string('name_en', 100)->nullable()->after('name');
        });

        // Preenche os planos padrão que já existem (produção não roda o PlanSeeder no update).
        foreach (['event' => 'Single event', 'pro' => 'Professional', 'agency' => 'Agency'] as $slug => $name) {
            DB::table('plans')->where('slug', $slug)->whereNull('name_en')->update(['name_en' => $name]);
        }
    }

    public function down(): void
    {
        Schema::table('plans', function (Blueprint $table): void {
            $table->dropColumn('name_en');
        });
    }
};
