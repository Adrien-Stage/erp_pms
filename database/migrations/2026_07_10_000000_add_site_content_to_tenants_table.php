<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * site_content : contenu marketing du site vitrine (module "website"),
 * distinct de `settings` (config app) — structure attendue :
 * { hero: {title, subtitle, cta_label, background_image}, about: {title, body},
 *   contact: {intro, hours}, gallery: [path, ...], seo: {title, description} }
 * Exposé publiquement via GET /api/public/establishments/{slug}/content.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->json('site_content')->nullable()->after('settings');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn('site_content');
        });
    }
};
