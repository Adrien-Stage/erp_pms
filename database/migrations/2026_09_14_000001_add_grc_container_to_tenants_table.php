<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Container "grc" (Plateforme de Contrôle de gestion & GRC wetchah_GRC) :
 * 4e service optionnel du docker-compose généré par tenant, provisionné
 * uniquement si le module "grc" est actif.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->string('docker_grc_container')->nullable()->after('docker_web_container');
            $table->string('grc_image_tag')->nullable()->after('docker_grc_container');
            $table->integer('grc_port')->nullable()->after('web_port');
            $table->boolean('grc_enabled')->default(false)->after('website_enabled');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn(['docker_grc_container', 'grc_image_tag', 'grc_port', 'grc_enabled']);
        });
    }
};
