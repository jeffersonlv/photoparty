<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Demo pareada: hash (sha256) do código de pareamento do visitante da home. Nunca o código em claro.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('photos', function (Blueprint $table) {
            $table->char('demo_pair', 64)->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::table('photos', function (Blueprint $table) {
            $table->dropIndex(['demo_pair']);
            $table->dropColumn('demo_pair');
        });
    }
};
