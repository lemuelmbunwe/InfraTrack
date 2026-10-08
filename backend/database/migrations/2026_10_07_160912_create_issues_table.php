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
        Schema::create('issues', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reported_by')->constrained('users')->restrictOnDelete();
            $table->string('photo_path');
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 11, 7);
            $table->string('address')->nullable();
            $table->string('address_text')->nullable();
            $table->text('description')->nullable();
            $table->string('severity', 20);
            $table->string('status', 20)->default('reported');
            $table->timestamp('reported_at');
            $table->timestamps();

            $table->index(['reported_by', 'reported_at']);
            $table->index(['status', 'reported_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('issues');
    }
};
