<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ajoute les colonnes de sourcing du template applicatif.
 *
 * source_type  : 'local'  → code déjà présent en local (source_path requis)
 *                'github' → clone depuis TEMPLATE_GITHUB_URL
 *
 * source_path  : chemin absolu hôte (uniquement pour source_type = 'local')
 *                ex: /c/Users/user/Herd/villab
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->string('source_type')->default('github')->after('docker_status');
            // 'local' | 'github'

            $table->string('source_path')->nullable()->after('source_type');
            // Chemin absolu hôte — renseigné uniquement si source_type = 'local'
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn(['source_type', 'source_path']);
        });
    }
};