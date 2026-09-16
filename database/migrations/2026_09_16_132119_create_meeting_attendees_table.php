<?php

use App\Models\Meeting;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Who is in a call right now.
 *
 * A group thread can have several people in one call, so "is this call still
 * running" can no longer be answered by whoever hung up first. Agora knows who
 * is connected but will not tell us without a separate REST credential, so the
 * platform keeps its own record: a row per person, refreshed by a heartbeat
 * while their call screen is open, closed when they leave.
 *
 * Additive only — nothing existing is touched.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meeting_attendees', function (Blueprint $table) {
            $table->id();

            $table->foreignIdFor(Meeting::class)->constrained()->cascadeOnDelete();
            $table->foreignIdFor(User::class)->constrained()->cascadeOnDelete();

            $table->timestamp('joined_at');

            /*
             * Refreshed by the heartbeat. A closed tab never says goodbye, so
             * somebody whose heartbeat has lapsed counts as gone.
             */
            $table->timestamp('last_seen_at');

            /** Set by the Leave button; cleared again if they come back. */
            $table->timestamp('left_at')->nullable();

            $table->timestamps();

            /* One row per person per call; rejoining reuses it. */
            $table->unique(['meeting_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meeting_attendees');
    }
};
