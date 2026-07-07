<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * docker_image_tag : digest (sha256:...) de l'image ghcr.io/adrien-stage/villa_b
 * épinglée pour cet établissement lors de son provisioning. Reste vide tant
 * que l'établissement n'a pas encore été provisionné (résolu automatiquement
 * depuis le tag "latest" au premier pull, puis figé).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->string('docker_image_tag')->nullable()->after('docker_db_container');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn('docker_image_tag');
        });
    }
};
