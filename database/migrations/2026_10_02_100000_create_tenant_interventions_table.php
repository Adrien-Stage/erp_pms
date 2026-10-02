<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Interventions des administrateurs d'établissement dans leur exploitation,
 * telles que chaque établissement les transmet. Le support et le
 * propriétaire les consultent dans « Droits & rôles ».
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_interventions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            // Numéro de l'intervention dans l'établissement.
            $table->unsignedBigInteger('reference');
            $table->string('administrateur')->nullable();
            $table->string('email')->nullable();
            $table->string('motif', 500);
            $table->json('perimetres');
            $table->timestamp('debut');
            $table->timestamp('fin_prevue');
            $table->timestamp('fin_reelle')->nullable();
            $table->string('cloture', 20)->nullable();
            $table->unsignedInteger('actions')->default(0);
            // Transmise après coup : la console était injoignable au moment
            // des faits. Une fois tardive, toujours tardive.
            $table->boolean('tardive')->default(false);
            $table->timestamps();

            $table->unique(['tenant_id', 'reference']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_interventions');
    }
};
