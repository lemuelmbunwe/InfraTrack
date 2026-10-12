<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('issue_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('issue_id')->constrained('issues')->cascadeOnDelete();
            $table->foreignId('contractor_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('assigned_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('assigned_at');
            $table->timestamp('unassigned_at')->nullable();
            $table->timestamps();

            $table->index(['issue_id', 'unassigned_at']);
            $table->index(['contractor_id', 'unassigned_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('issue_assignments');
    }
};
