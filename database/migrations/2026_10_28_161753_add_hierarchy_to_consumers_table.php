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
        Schema::table('consumers', function (Blueprint $table): void {
            $table->foreignId('discom_id')->nullable()->after('region')->constrained()->nullOnDelete();
            $table->foreignId('circle_id')->nullable()->after('discom_id')->constrained()->nullOnDelete();
            $table->foreignId('division_id')->nullable()->after('circle_id')->constrained()->nullOnDelete();
            $table->foreignId('subdivision_id')->nullable()->after('division_id')->constrained()->nullOnDelete();
            $table->foreignId('section_id')->nullable()->after('subdivision_id')->constrained()->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('consumers', function (Blueprint $table): void {
            $table->dropForeign(['discom_id']);
            $table->dropForeign(['circle_id']);
            $table->dropForeign(['division_id']);
            $table->dropForeign(['subdivision_id']);
            $table->dropForeign(['section_id']);
            $table->dropColumn(['discom_id', 'circle_id', 'division_id', 'subdivision_id', 'section_id']);
        });
    }
};
