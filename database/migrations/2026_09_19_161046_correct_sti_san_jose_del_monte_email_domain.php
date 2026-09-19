<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * STI College San Jose Del Monte hands out mail on its campus domain.
 *
 * The school was seeded with "sti.edu.ph", but its students' Microsoft
 * accounts are surname.number@sjdelmonte.sti.edu.ph (checked against a real
 * student address on 2026-09-20). School::forEmailDomain() matches exactly —
 * see .ai/rules/verification.md — so not one of those addresses was
 * recognised: no tick on the sign up form, and no school-email verification
 * recorded when the code came back.
 *
 * Only the seeded value is corrected. A domain an administrator has already
 * changed by hand is theirs and is left alone.
 */
return new class extends Migration
{
    private const SLUG = 'sti-college-san-jose-del-monte';

    public function up(): void
    {
        DB::table('schools')
            ->where('slug', self::SLUG)
            ->where('domain', 'sti.edu.ph')
            ->update(['domain' => 'sjdelmonte.sti.edu.ph', 'updated_at' => now()]);
    }

    public function down(): void
    {
        DB::table('schools')
            ->where('slug', self::SLUG)
            ->where('domain', 'sjdelmonte.sti.edu.ph')
            ->update(['domain' => 'sti.edu.ph', 'updated_at' => now()]);
    }
};
