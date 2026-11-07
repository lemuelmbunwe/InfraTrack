<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement(<<<'SQL'
            ALTER TABLE issues
            ADD CONSTRAINT issues_status_allowed_check
            CHECK (status IN ('reported', 'assigned', 'in_progress', 'resolved'))
            SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE issues
            ADD CONSTRAINT issues_severity_allowed_check
            CHECK (severity IN ('low', 'medium', 'high'))
            SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE issues DROP CONSTRAINT IF EXISTS issues_severity_allowed_check');
        DB::statement('ALTER TABLE issues DROP CONSTRAINT IF EXISTS issues_status_allowed_check');
    }
};
