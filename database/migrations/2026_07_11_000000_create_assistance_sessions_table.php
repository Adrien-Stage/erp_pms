<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sessions d'assistance (Support > Mode assistance) : un admin TECH ouvre
 * un accès temporaire et audité à l'application d'un établissement pour
 * diagnostiquer un problème. Chaque session exige une justification (motif),
 * porte un jeton signé consommé par le endpoint /assistance/enter côté
 * meka_template, et expire automatiquement.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assistance_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade'); // admin TECH demandeur
            $table->text('reason'); // justification obligatoire
            $table->string('token', 64)->unique(); // référence de session (opaque)
            $table->string('status')->default('active'); // active | closed | revoked | expired
            $table->timestamp('expires_at');
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assistance_sessions');
    }
};
