<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Rattache explicitement une session d'assistance au ticket qui l'a motivée.
 *
 * Le lien était auparavant reconstruit en cherchant « Ticket #N » dans le
 * motif, ce qui confondait le ticket #7 avec le #77 — celui-ci héritait de
 * la session de celui-là, motif d'audit compris.
 *
 * Aucune clé étrangère : les tickets vivent dans la base de leur
 * établissement, pas ici. La colonne reste nullable car une session ouverte
 * depuis l'onglet « Mode assistance » ne se rattache à aucun ticket.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assistance_sessions', function (Blueprint $table) {
            $table->unsignedBigInteger('ticket_id')->nullable()->after('tenant_id');
            $table->index(['tenant_id', 'ticket_id']);
        });
    }

    public function down(): void
    {
        Schema::table('assistance_sessions', function (Blueprint $table) {
            $table->dropIndex(['tenant_id', 'ticket_id']);
            $table->dropColumn('ticket_id');
        });
    }
};
