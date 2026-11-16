<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('photos', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('account_id')->constrained('accounts')->cascadeOnDelete();
            $table->foreignId('event_id')->constrained('events')->cascadeOnDelete();
            $table->foreignId('frame_id')->nullable()->constrained('frames')->nullOnDelete();
            $table->string('filter', 30)->nullable();
            // portrait, landscape
            $table->string('orientation', 10)->default('portrait');
            // pending, approved, rejected
            $table->string('status', 10)->default('pending');
            $table->string('guest_name', 80)->nullable();
            $table->json('guest_texts')->nullable();
            $table->char('device_hash', 64)->index();
            $table->string('original_path')->nullable();
            $table->string('thumb_path')->nullable();
            $table->unsignedInteger('bytes')->default(0);
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();

            $table->index(['event_id', 'status', 'approved_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('photos');
    }
};
