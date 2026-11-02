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
        Schema::create('sections', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('subdivision_id')->constrained('subdivisions')->restrictOnDelete();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('short_name')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['subdivision_id', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sections');
    }
};
