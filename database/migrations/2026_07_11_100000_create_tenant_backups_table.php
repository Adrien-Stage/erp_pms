<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sauvegardes (dumps PostgreSQL) de la base de chaque établissement, prises
 * via pg_dump sur son container DB puis archivées côté pms (Import/Export >
 * Backups). Déclenchées manuellement ou par une planification cron
 * (backup_schedules).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_backups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->onDelete('cascade');
            $table->string('filename');
            $table->string('path');                 // chemin relatif sur le disque 'local'
            $table->unsignedBigInteger('size_bytes')->default(0);
            $table->string('status')->default('completed'); // completed | failed
            $table->string('trigger')->default('manual');   // manual | scheduled
            $table->text('error')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_backups');
    }
};
