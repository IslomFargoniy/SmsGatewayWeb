<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * When the person chose their own password. Null: they have none they know (Google sign-up stores a random one),
     * so Settings → Password sets one without asking for the current one.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('password_set_at')->nullable()->after('password');
        });

        // Existing accounts: a password and no Google sign-in → they know it (registered with it).
        // Accounts with a google_id are treated as having no password of their own: a Google sign-up got a random one,
        // and an email account that later linked Google cannot be told apart from it.
        DB::table('users')
            ->whereNotNull('password')
            ->whereNull('google_id')
            ->update(['password_set_at' => DB::raw('COALESCE(updated_at, created_at)')]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('password_set_at');
        });
    }
};
