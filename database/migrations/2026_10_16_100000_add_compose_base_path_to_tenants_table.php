<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Dossier où la console écrit le docker-compose d'un établissement
 * (<dossier>/.compose/<slug>.yml). Vide : TENANTS_BASE_PATH, comme avant.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->string('compose_base_path')->nullable()->after('grc_port');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn('compose_base_path');
        });
    }
};
