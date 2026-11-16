<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('account_id')->constrained('accounts')->cascadeOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            // dateTime (e não timestamp): evita o ON UPDATE CURRENT_TIMESTAMP implícito do MariaDB/MySQL antigo.
            // dateTime (não timestamp): evita o ON UPDATE CURRENT_TIMESTAMP implícito de MySQL/MariaDB antigos.
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            // draft, scheduled, live, ended, expired
            $table->string('status', 20)->default('draft');
            // pre, post
            $table->string('moderation', 10)->default('pre');
            $table->boolean('is_trial')->default(false);
            $table->text('welcome_message')->nullable();
            $table->boolean('ask_guest_name')->default(false);
            $table->unsignedSmallInteger('max_photos_per_guest')->nullable();
            $table->json('enabled_filters')->nullable();
            // Tokens: hash SHA-256 para busca + cópia criptografada (Crypt) para reimprimir o QR.
            $table->char('upload_token_hash', 64)->nullable()->unique();
            $table->text('upload_token_encrypted')->nullable();
            $table->char('screen_token_hash', 64)->nullable()->unique();
            $table->text('screen_token_encrypted')->nullable();
            $table->json('screen_settings')->nullable();
            $table->string('language', 5)->default('pt_BR');
            $table->timestamp('published_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index(['account_id', 'status']);
            $table->index(['status', 'starts_at', 'ends_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('events');
    }
};
