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
        Schema::create('complaint_actions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('complaint_id')->constrained('complaints')->cascadeOnDelete();
            // The actor is either a consumer or an official, enforced via the
            // morph map (consumer | official); only this code writes the table.
            $table->nullableMorphs('actor');
            $table->string('action');
            $table->string('from_status')->nullable();
            $table->string('to_status');
            $table->string('from_level')->nullable();
            $table->string('to_level');
            $table->foreignId('competent_authority_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('comment')->nullable();
            $table->timestamps();

            $table->index(['complaint_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('complaint_actions');
    }
};
