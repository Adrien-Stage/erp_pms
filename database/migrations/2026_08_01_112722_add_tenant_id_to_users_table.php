<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Rattachement d'un compte ERP à un établissement.
 *
 * Ne concerne que les rôles cloisonnés, l'éditeur de contenu en premier : il
 * n'a accès qu'au site de l'établissement auquel il est rattaché. Les comptes
 * techniques et les propriétaires gardent la valeur nulle — leur périmètre se
 * détermine autrement (tout le parc, ou les établissements qu'ils possèdent).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // En cascade : sans son établissement, un éditeur n'a plus d'objet.
            $table->foreignId('tenant_id')
                ->nullable()
                ->after('role')
                ->constrained('tenants')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['tenant_id']);
            $table->dropColumn('tenant_id');
        });
    }
};
