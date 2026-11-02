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
        if (! Schema::hasColumn('complaints', 'attachments')) {
            Schema::table('complaints', function (Blueprint $table): void {
                $table->json('attachments')->nullable()->after('description');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('complaints', 'attachments')) {
            Schema::table('complaints', function (Blueprint $table): void {
                $table->dropColumn('attachments');
            });
        }
    }
};
