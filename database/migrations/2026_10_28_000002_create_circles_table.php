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
        Schema::create('circles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('discom_id')->constrained('discoms')->restrictOnDelete();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('short_name')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['discom_id', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('circles');
    }
};
