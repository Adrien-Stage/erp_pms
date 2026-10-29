<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Les comptes de démonstration partagent désormais un mot de passe unique,
 * celui du jeu (demo/data/staff.json) : le tirage par établissement n'a plus
 * de raison d'être.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('tenants', 'demo_password')) {
            Schema::table('tenants', function (Blueprint $table) {
                $table->dropColumn('demo_password');
            });
        }
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->text('demo_password')->nullable()->after('db_password');
        });
    }
};
