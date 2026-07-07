<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Retire source_type/source_path : le provisioning ne clone plus le
 * template (git clone) — il pull l'image publiée sur GHCR (voir
 * Tenant::docker_image_tag). Ces colonnes ne sont plus lues nulle part.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn(['source_type', 'source_path']);
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->string('source_type')->default('github')->after('docker_status');
            $table->string('source_path')->nullable()->after('source_type');
        });
    }
};
