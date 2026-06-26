<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('discussion_conversation_user')
            || !Schema::hasColumn('discussion_conversation_user', 'deleted_at')
            || !Schema::hasColumn('discussion_conversation_user', 'archived_at')) {
            return;
        }

        // Nettoyage des anciens etats:
        // avant, "supprimer pour moi" renseignait aussi archived_at.
        // On garde deleted_at (conversation cachee), mais on vide archived_at.
        DB::table('discussion_conversation_user')
            ->whereNotNull('deleted_at')
            ->whereNotNull('archived_at')
            ->update(['archived_at' => null]);
    }

    public function down(): void
    {
        // Migration de nettoyage irreversible.
    }
};
