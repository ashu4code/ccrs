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
        Schema::create('complaints', function (Blueprint $table): void {
            $table->id();
            $table->string('ticket_number')->unique();
            $table->string('consumer_number')->index();
            $table->string('category');
            $table->string('subject');
            $table->text('description');
            $table->string('region');
            $table->string('status')->default('submitted');
            $table->string('current_level')->default('regional_manager');
            $table->foreignId('consumer_id')->constrained('consumers')->cascadeOnDelete();
            $table->foreignId('regional_manager_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('competent_authority_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['region', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('complaints');
    }
};
