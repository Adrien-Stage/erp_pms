<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Planification des sauvegardes automatiques par établissement. La commande
 * artisan backups:run (déclenchée par le scheduler Laravel) parcourt les
 * planifications actives dont next_run_at est échu et lance le backup.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('backup_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->unique()->constrained('tenants')->onDelete('cascade');
            $table->boolean('enabled')->default(true);
            $table->string('frequency')->default('daily'); // daily | weekly | monthly
            $table->unsignedTinyInteger('hour')->default(2);    // heure d'exécution (0-23)
            $table->unsignedTinyInteger('minute')->default(0);  // minute (0-59)
            $table->unsignedTinyInteger('day_of_week')->nullable();  // weekly : 0 (dim) - 6 (sam)
            $table->unsignedTinyInteger('day_of_month')->nullable(); // monthly : 1-28
            $table->unsignedSmallInteger('retention')->default(7);   // nb de backups conservés
            $table->timestamp('last_run_at')->nullable();
            $table->timestamp('next_run_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('backup_schedules');
    }
};
