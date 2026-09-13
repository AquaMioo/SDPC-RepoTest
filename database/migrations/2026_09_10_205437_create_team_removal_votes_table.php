<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Votes to remove somebody from a team.
 *
 * One row is one member saying yes to one removal. There is no separate
 * "proposal" table on purpose: the first vote opens the question and the last
 * one closes it, so a proposal nobody has voted for is a row that would never
 * exist. Removing the member deletes every row naming them, on either side, so
 * a stale yes cannot carry over into a later vote.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('team_removal_votes', function (Blueprint $table) {
            $table->id();

            $table->foreignId('team_id')->constrained()->cascadeOnDelete();

            /* The member the vote would remove. */
            $table->foreignId('target_user_id')->constrained('users')->cascadeOnDelete();

            /* The member casting it. */
            $table->foreignId('voter_id')->constrained('users')->cascadeOnDelete();

            $table->timestamps();

            /*
             * One vote each. Without this a member could agree twice and carry
             * a removal on their own.
             */
            $table->unique(['team_id', 'target_user_id', 'voter_id'], 'team_removal_votes_unique');

            /* The count that decides a removal reads on this pair. */
            $table->index(['team_id', 'target_user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('team_removal_votes');
    }
};
