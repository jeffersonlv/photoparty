<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * O cursor do telão ordena por (approved_at, id). Com precisão de segundo, duas
 * aprovações no mesmo segundo podem chegar "fora de ordem" e uma foto seria
 * perdida pelo cursor; com microssegundos isso deixa de acontecer na prática.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('photos', function (Blueprint $table) {
            $table->timestamp('approved_at', 6)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('photos', function (Blueprint $table) {
            $table->timestamp('approved_at')->nullable()->change();
        });
    }
};
