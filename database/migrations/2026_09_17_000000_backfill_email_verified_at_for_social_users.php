<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;

/**
 * Backfill email_verified_at for users created via social sign-in (Google/Apple).
 *
 * Problem: Athletes who created an account through "Continue with Google" were
 * persisted with email_verified_at = NULL, so they show up as unverified in
 * Logger even though Google (the identity provider) had already verified the
 * email address. The user-creation path (AuthController::findOrCreateSocialUser)
 * never stamped email_verified_at.
 *
 * Fix: The controller now stamps email_verified_at on social sign-in going
 * forward. This migration repairs the existing rows.
 *
 * Scope: Only rows that have a google_id AND a NULL email_verified_at. This is
 * exactly the buggy population. Email/password accounts (no google_id) are left
 * untouched, since those may legitimately still require verification.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Use each user's created_at as the verification timestamp — the email was
        // effectively provider-verified at account creation. Referencing the
        // column directly (rather than a SQL function like NOW()) keeps this
        // portable across MySQL (production) and SQLite (tests).
        DB::table('users')
            ->whereNotNull('google_id')
            ->whereNull('email_verified_at')
            ->whereNotNull('created_at')
            ->update([
                'email_verified_at' => DB::raw('created_at'),
            ]);

        // Fallback for the unexpected case of a null created_at: stamp the
        // current time so the account is still marked verified.
        DB::table('users')
            ->whereNotNull('google_id')
            ->whereNull('email_verified_at')
            ->update([
                'email_verified_at' => Carbon::now(),
            ]);
    }

    /**
     * Reverse the migrations.
     *
     * This is a data repair with no reliable inverse: we cannot distinguish rows
     * this migration stamped from rows that were verified through another path.
     * Reverting could incorrectly mark genuinely-verified accounts as unverified,
     * so down() is intentionally a no-op.
     */
    public function down(): void
    {
        // Intentionally left empty — see docblock.
    }
};
