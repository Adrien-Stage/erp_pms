<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('discussion_conversation_user', function (Blueprint $table) {
            $table->timestamp('last_read_at')->nullable()->after('user_id');
            $table->index(['user_id', 'last_read_at']);
        });
    }

    public function down(): void
    {
        Schema::table('discussion_conversation_user', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'last_read_at']);
            $table->dropColumn('last_read_at');
        });
    }
};
