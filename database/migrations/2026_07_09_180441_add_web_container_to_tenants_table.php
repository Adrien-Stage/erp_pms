<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Container "web" (site vitrine template_site) : 3e service optionnel du
 * docker-compose généré par tenant, provisionné uniquement si le module
 * "website" est actif. web_image_tag suit le même principe de pin par
 * digest que docker_image_tag, mais sur le registre distinct de l'image
 * template_site (voir TenantProvisioningService, PLAN_CMS_SITE_VITRINE.md).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->string('docker_web_container')->nullable()->after('docker_image_tag');
            $table->string('web_image_tag')->nullable()->after('docker_web_container');
            $table->integer('web_port')->nullable()->after('db_port');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn(['docker_web_container', 'web_image_tag', 'web_port']);
        });
    }
};
