<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "Envio só no local do evento" (recurso de plano):
 * - plans.allows_geofence: o plano oferece o recurso.
 * - events.geofence_*: local do evento (do organizador) e raio em metros.
 * - photos.location_verified: o envio passou pela conferência de local. Coordenadas do
 *   convidado NUNCA são gravadas (LGPD/GDPR: minimização).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->boolean('allows_geofence')->default(false)->after('allows_custom_frames');
        });

        Schema::table('events', function (Blueprint $table) {
            $table->boolean('geofence_enabled')->default(false)->after('language');
            $table->decimal('geofence_lat', 9, 6)->nullable()->after('geofence_enabled');
            $table->decimal('geofence_lng', 9, 6)->nullable()->after('geofence_lat');
            $table->unsignedSmallInteger('geofence_radius_m')->default(100)->after('geofence_lng');
        });

        Schema::table('photos', function (Blueprint $table) {
            $table->boolean('location_verified')->default(false)->after('consented_at');
        });
    }

    public function down(): void
    {
        Schema::table('photos', function (Blueprint $table) {
            $table->dropColumn('location_verified');
        });

        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn(['geofence_enabled', 'geofence_lat', 'geofence_lng', 'geofence_radius_m']);
        });

        Schema::table('plans', function (Blueprint $table) {
            $table->dropColumn('allows_geofence');
        });
    }
};
