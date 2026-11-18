<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Solicitações de titulares (convidados) feitas pelo formulário público /privacidade/solicitacao.
 * Não guarda IP nem user agent (minimização). account_id/event_id são preenchidos pelo servidor
 * quando o link informado corresponde a um evento (para o admin encaminhar ao organizador).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('privacy_requests', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            // deletion, access, correction, other
            $table->string('type', 20);
            $table->string('event_link', 500);
            $table->string('email', 254);
            $table->text('details')->nullable();
            $table->string('locale', 5)->default('pt_BR');
            $table->foreignId('account_id')->nullable()->constrained('accounts')->nullOnDelete();
            $table->foreignId('event_id')->nullable()->constrained('events')->nullOnDelete();
            // open, done
            $table->string('status', 10)->default('open');
            $table->text('resolution_note')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('privacy_requests');
    }
};
