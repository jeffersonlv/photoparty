<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('home_sections', function (Blueprint $table) {
            $table->id();
            // hero, how_it_works, audiences, features, pricing, faq, final_cta
            $table->string('key', 40)->unique();
            $table->boolean('enabled')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            // JSON por idioma: {"pt_BR": {...}, "en": {...}}
            $table->json('content')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('home_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('section_id')->constrained('home_sections')->cascadeOnDelete();
            $table->boolean('enabled')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            // JSON por idioma: {"pt_BR": {...}, "en": {...}}
            $table->json('data')->nullable();
            $table->timestamps();

            $table->index(['section_id', 'sort_order']);
        });

        Schema::create('site_settings', function (Blueprint $table) {
            $table->string('key', 80)->primary();
            $table->text('value')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_settings');
        Schema::dropIfExists('home_items');
        Schema::dropIfExists('home_sections');
    }
};
