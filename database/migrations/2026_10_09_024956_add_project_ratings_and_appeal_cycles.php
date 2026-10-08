<?php

use App\Models\Agreement;
use App\Models\Project;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Ratings on completion, real experience counts, and appeal cycles (owner,
 * 2026-10-09).
 *
 * project_ratings: the client's 1-5 rating and optional feedback, written once
 * when they complete a project, one row per student on the build (the signer
 * and their teammates). Permanent: nothing edits or deletes a row, and the
 * project, agreement and client may disappear without taking it along, so the
 * row keeps the titles it was given for.
 *
 * student_profiles.rating_average and completed_projects_count existed but
 * nothing wrote them; only seeded or factory values sat there. They are now
 * real: completed_projects_count is backfilled from completed agreements
 * (signer and teammates), and every rating starts at none.
 *
 * users.restricted_at marks when the account was last deactivated (or put
 * under monitoring). Only appeals filed since then belong to the current
 * decision, so a resolved appeal from an earlier one never resurfaces.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_ratings', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(Agreement::class)->nullable()->constrained()->nullOnDelete();
            $table->foreignIdFor(Project::class)->nullable()->constrained()->nullOnDelete();
            $table->foreignIdFor(User::class, 'student_id')->constrained('users')->cascadeOnDelete();
            $table->foreignIdFor(Team::class, 'client_team_id')->nullable()->constrained('teams')->nullOnDelete();
            $table->foreignIdFor(User::class, 'rated_by')->nullable()->constrained('users')->nullOnDelete();
            /* Kept as written, so the review still reads after the project or business is gone. */
            $table->string('project_title');
            $table->string('client_name');
            $table->unsignedTinyInteger('rating');
            $table->text('feedback')->nullable();
            $table->timestamps();

            $table->unique(['agreement_id', 'student_id']);
            $table->index(['student_id', 'created_at']);
        });

        Schema::table('student_profiles', function (Blueprint $table) {
            $table->unsignedInteger('ratings_count')->default(0)->after('rating_average');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('restricted_at')->nullable()->after('status');
        });

        $this->backfillExperience();

        DB::table('users')
            ->whereIn('status', ['deactivated', 'monitored'])
            ->update(['restricted_at' => DB::raw('COALESCE(updated_at, CURRENT_TIMESTAMP)')]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('restricted_at');
        });

        Schema::table('student_profiles', function (Blueprint $table) {
            $table->dropColumn('ratings_count');
        });

        Schema::dropIfExists('project_ratings');
    }

    /**
     * Count each student's completed builds: the signer and their teammates.
     */
    private function backfillExperience(): void
    {
        $counts = [];

        $signers = DB::table('agreements')
            ->where('status', 'completed')
            ->whereNull('deleted_at')
            ->pluck('student_id');

        foreach ($signers as $signerId) {
            $teammates = DB::table('team_members')
                ->whereIn('team_id', DB::table('team_members')
                    ->select('team_id')
                    ->where('user_id', $signerId)
                    ->where('role', 'owner'))
                ->pluck('user_id');

            foreach ($teammates->push($signerId)->unique() as $userId) {
                $counts[$userId] = ($counts[$userId] ?? 0) + 1;
            }
        }

        DB::table('student_profiles')->update([
            'completed_projects_count' => 0,
            'rating_average' => 0,
            'ratings_count' => 0,
        ]);

        foreach ($counts as $userId => $count) {
            DB::table('student_profiles')->where('user_id', $userId)->update(['completed_projects_count' => $count]);
        }
    }
};
