<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Which student team a thread belongs to, so the student side can be a group.
 *
 * The client side already was one: every member of the business team shares
 * the thread, because the conversation is with the business rather than with
 * whoever opened it. This is the same idea on the other side — a student who
 * leads a team brings that team into the thread with their client.
 *
 * Nullable, and null is the ordinary case: a student working alone has only a
 * personal team, and storing that would say nothing the user_id does not
 * already say.
 *
 * nullOnDelete rather than cascade: a deleted team must not take a client's
 * conversation with it. The thread falls back to the student alone.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('conversations', function (Blueprint $table): void {
            $table->foreignId('student_team_id')
                ->nullable()
                ->after('user_id')
                ->constrained('teams')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('conversations', function (Blueprint $table): void {
            if (Schema::hasColumn('conversations', 'student_team_id')) {
                $table->dropConstrainedForeignId('student_team_id');
            }
        });
    }
};
