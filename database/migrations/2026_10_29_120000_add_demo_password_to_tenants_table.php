<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Mot de passe commun aux comptes du personnel de démonstration d'un
 * établissement. Tiré au hasard à la première installation du jeu, chiffré
 * (cast « encrypted » du modèle), affiché au seul administrateur TECH.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->text('demo_password')->nullable()->after('db_password');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn('demo_password');
        });
    }
};
