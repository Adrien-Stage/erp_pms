<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('discussion_conversation_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('discussion_conversation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['discussion_conversation_id', 'user_id'], 'discussion_conv_user_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('discussion_conversation_user');
    }
};
