<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('frames', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            // Nulo = modelo do sistema (disponível para todas as contas).
            $table->foreignId('account_id')->nullable()->constrained('accounts')->cascadeOnDelete();
            // template, custom
            $table->string('type', 20)->default('template');
            $table->string('template_key', 50)->nullable();
            $table->string('name');
            $table->json('config')->nullable();
            $table->string('portrait_path')->nullable();
            $table->string('landscape_path')->nullable();
            $table->timestamps();
        });

        Schema::create('event_frame', function (Blueprint $table) {
            $table->foreignId('event_id')->constrained('events')->cascadeOnDelete();
            $table->foreignId('frame_id')->constrained('frames')->cascadeOnDelete();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->primary(['event_id', 'frame_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_frame');
        Schema::dropIfExists('frames');
    }
};
