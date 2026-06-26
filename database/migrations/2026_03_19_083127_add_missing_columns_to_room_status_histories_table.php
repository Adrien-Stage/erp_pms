<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('room_status_histories', function (Blueprint $table) {
            $table->string('from_status', 30)->nullable()->after('room_id');
            $table->string('to_status', 30)->nullable()->after('from_status');
            $table->text('reason')->nullable()->after('to_status');
            $table->unsignedBigInteger('changed_by')->nullable()->after('reason');
            $table->foreign('changed_by')->references('id')->on('users')->nullOnDelete();
            $table->timestamp('changed_at')->nullable()->after('changed_by');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('room_status_histories', function (Blueprint $table) {
            $table->dropForeign(['changed_by']);
            $table->dropColumn(['from_status', 'to_status', 'reason', 'changed_by', 'changed_at']);
        });
    }
};
