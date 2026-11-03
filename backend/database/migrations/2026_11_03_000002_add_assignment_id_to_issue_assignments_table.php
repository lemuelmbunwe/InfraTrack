<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('issue_assignments', function (Blueprint $table) {
            $table->foreignId('assignment_id')
                ->nullable()
                ->constrained('assignments')
                ->restrictOnDelete();
        });

        DB::statement(
            'CREATE UNIQUE INDEX issue_assignments_one_current_per_issue '
            .'ON issue_assignments (issue_id) WHERE unassigned_at IS NULL'
        );
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS issue_assignments_one_current_per_issue');

        Schema::table('issue_assignments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('assignment_id');
        });
    }
};
