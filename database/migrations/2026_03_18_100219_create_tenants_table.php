<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $table) {
            $table->id();

            // Identité de l'établissement
            $table->string('name');
            // slug : version URL-friendly du nom
            // "Villa Boutanga" → "villa-boutanga"
            // Utilisé dans les routes : /villa-boutanga/dashboard
            // Plus sûr qu'exposer l'ID numérique dans l'URL
            $table->string('slug')->unique();

            // Coordonnées
            $table->string('address')->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('email')->nullable();

            // settings en JSON : chaque hôtel peut avoir sa propre config
            // Ex: {"checkin_time": "14:00", "checkout_time": "11:00", "tax_rate": 19.25}
            // Évite d'ajouter une colonne pour chaque petite config
            $table->json('settings')->nullable();

            // Devise : XAF = Franc CFA d'Afrique Centrale
            // On la stocke ici car un futur hôtel au Nigéria utilisera NGN
            $table->string('currency', 3)->default('XAF');

            // Pattern "soft disable" : désactiver sans supprimer
            // Préserve tout l'historique des réservations passées
            $table->boolean('is_active')->default(true);

            $table->timestamps();

            // Index sur is_active car on filtrera souvent : WHERE is_active = true
            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenants');
    }
};