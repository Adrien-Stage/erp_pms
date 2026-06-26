<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('housekeeping_assignments', function (Blueprint $table) {
            $table->foreignId('reported_by')->nullable()->after('assigned_by')->constrained('users')->nullOnDelete();
            $table->timestamp('reported_at')->nullable()->after('completed_at');
            $table->text('issue_notes')->nullable()->after('notes');
        });
    }

    public function down(): void
    {
        Schema::table('housekeeping_assignments', function (Blueprint $table) {
            $table->dropForeign(['reported_by']);
            $table->dropColumn([
                'reported_by',
                'reported_at',
                'issue_notes',
            ]);
        });
    }
};
