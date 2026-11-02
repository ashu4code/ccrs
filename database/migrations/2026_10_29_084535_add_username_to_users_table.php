<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'username')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->string('username')->nullable()->after('email');
            });
        }

        // Backfill: officials created before this change signed in with the
        // login username stored in the email column, so mirror it over.
        $pending = DB::table('users')
            ->select('id', 'email')
            ->whereNull('username')
            ->limit(1000)
            ->get();

        foreach ($pending as $user) {
            DB::table('users')->where('id', $user->id)->update(['username' => $user->email]);
        }

        if (! Schema::hasIndex('users', 'users_username_unique')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->unique('username');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasIndex('users', 'users_username_unique')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->dropUnique('users_username_unique');
            });
        }

        if (Schema::hasColumn('users', 'username')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->dropColumn('username');
            });
        }
    }
};
