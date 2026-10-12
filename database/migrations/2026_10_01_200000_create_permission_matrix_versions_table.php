<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Historique de la couche de droits que la console pose sur un établissement.
 *
 * Chaque enregistrement réussi y laisse l'état complet de la couche — l'API
 * la remplace en entier —, qui l'a posé, quand et pourquoi. Revenir à une
 * version, c'est la renvoyer telle quelle.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('permission_matrix_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('numero');
            // etat_initial : trouvé dans l'établissement avant le premier
            // enregistrement ; modification ; retour à une version antérieure.
            $table->string('nature', 20)->default('modification');
            $table->json('ecarts');
            $table->string('motif', 255)->nullable();
            $table->boolean('derogation')->default(false);
            $table->json('cumuls')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            // Nom figé : le compte de l'auteur peut disparaître, pas l'histoire.
            $table->string('auteur')->nullable();
            $table->unsignedBigInteger('restauree_depuis')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'numero']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('permission_matrix_versions');
    }
};
