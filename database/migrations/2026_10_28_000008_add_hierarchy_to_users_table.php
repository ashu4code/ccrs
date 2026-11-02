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
        Schema::table('users', function (Blueprint $table): void {
            $table->foreignId('discom_id')->nullable()->after('region')->constrained('discoms')->restrictOnDelete();
            $table->foreignId('circle_id')->nullable()->after('discom_id')->constrained('circles')->restrictOnDelete();
            $table->foreignId('division_id')->nullable()->after('circle_id')->constrained('divisions')->restrictOnDelete();
            $table->foreignId('subdivision_id')->nullable()->after('division_id')->constrained('subdivisions')->restrictOnDelete();
            $table->foreignId('section_id')->nullable()->after('subdivision_id')->constrained('sections')->restrictOnDelete();

            $table->index('discom_id');
            $table->index('circle_id');
            $table->index('division_id');
            $table->index('subdivision_id');
            $table->index('section_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropForeign(['discom_id']);
            $table->dropForeign(['circle_id']);
            $table->dropForeign(['division_id']);
            $table->dropForeign(['subdivision_id']);
            $table->dropForeign(['section_id']);

            $table->dropIndex(['discom_id']);
            $table->dropIndex(['circle_id']);
            $table->dropIndex(['division_id']);
            $table->dropIndex(['subdivision_id']);
            $table->dropIndex(['section_id']);

            $table->dropColumn(['discom_id', 'circle_id', 'division_id', 'subdivision_id', 'section_id']);
        });
    }
};
