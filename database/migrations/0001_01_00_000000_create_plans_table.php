<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->decimal('price', 10, 2)->default(0);
            $table->char('currency', 3)->default('EUR');
            $table->unsignedInteger('max_events_per_month')->nullable();
            $table->unsignedInteger('max_photos_per_event');
            $table->unsignedInteger('retention_days');
            $table->boolean('allows_custom_frames')->default(false);
            $table->boolean('white_label')->default(false);
            $table->boolean('is_public')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plans');
    }
};
