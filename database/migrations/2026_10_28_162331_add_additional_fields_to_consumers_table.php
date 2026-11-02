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
            $table->text('address')->nullable()->after('mobile');
            $table->string('load')->nullable()->after('address');
            $table->string('meter_number')->nullable()->after('load');
            $table->string('tariff')->nullable()->after('meter_number');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('consumers', function (Blueprint $table): void {
            $table->dropColumn(['address', 'load', 'meter_number', 'tariff']);
        });
    }
};
