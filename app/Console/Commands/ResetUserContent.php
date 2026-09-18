<?php

namespace App\Console\Commands;

use App\Actions\Teams\GiveOwnTeam;
use App\Enums\TeamRole;
use App\Enums\UserRole;
use App\Enums\VerificationProvider;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Console\ConfirmableTrait;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Put every student and client back to the moment they signed up.
 *
 * The accounts stay: name, email, password, role, status and sign-in links,
 * along with everything sign up itself made — each person's own team, a
 * client's business name and verified standing, a student's school-email
 * verification. Everything typed or uploaded after that goes: postings and
 * everything hanging off them (applications, agreements, chats, calls,
 * transactions), profiles, portfolios, credentials, testimonials, team
 * invitations, joined teams and notifications.
 *
 * Moderation is left alone. Account status, appeals and reports about people
 * are the administrators' record, not the users' input; a report about a
 * posting goes with the posting through its foreign key.
 */
class ResetUserContent extends Command
{
    use ConfirmableTrait;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'users:reset-content
                            {--dry-run : Count what would be cleared without clearing it}
                            {--force : Skip the confirmation prompt}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clear every posting, agreement, chat and profile, keeping the accounts as they were at sign up';

    /**
     * The client profile columns filled in after sign up.
     *
     * Sign up writes business_name, owner_name, contact_email and the verified
     * standing (see CreateNewUser); those stay, because a client without a
     * verified profile could never post again.
     *
     * @var list<string>
     */
    private const CLIENT_PROFILE_INPUT = [
        'business_description', 'industry', 'organization_size', 'tagline',
        'logo_path', 'address', 'city', 'barangay', 'province', 'phone_number',
        'website_url', 'facebook_url', 'permit_path',
    ];

    /**
     * Upload folders whose every file belongs to content this command clears.
     *
     * Avatars are not here: an administrator's sits in the same folder, so
     * those are removed per person instead.
     *
     * @var array<string, list<string>>
     */
    private const UPLOAD_DIRECTORIES = [
        'public' => ['business-logos', 'business-permits', 'message-images', 'task-proofs'],
        'local' => ['business-permits', 'student-credentials'],
    ];

    public function __construct(private readonly GiveOwnTeam $giveOwnTeam)
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->table(['Cleared', 'Rows'], collect($this->counts())
            ->map(fn (int $count, string $label): array => [$label, $count])
            ->values()
            ->all());

        if ($this->option('dry-run')) {
            $this->components->info('Dry run: nothing was changed.');

            return self::SUCCESS;
        }

        if (! $this->confirmToProceed('This permanently clears the content above for every student and client.', fn (): bool => true)) {
            return self::FAILURE;
        }

        /** @var list<array{disk: string, path: string}> $attachments */
        $attachments = DB::table('project_attachments')
            ->get(['disk', 'path'])
            ->map(fn (object $row): array => ['disk' => $row->disk, 'path' => $row->path])
            ->all();

        /** @var list<int> $peopleIds */
        $peopleIds = $this->people()->pluck('id')->all();

        DB::transaction(function () use ($peopleIds): void {
            /*
             * Hard deletes through the query builder: projects and agreements
             * soft delete, and Eloquent would leave their rows behind. The
             * foreign keys cascade from here to applications, agreements with
             * their milestones, tasks, signatures and transactions,
             * conversations with their messages, reactions and meetings,
             * recommendations, attachments and reports about the posting.
             */
            DB::table('projects')->delete();
            DB::table('conversations')->delete();

            DB::table('notifications')->delete();
            DB::table('testimonials')->delete();
            DB::table('team_invitations')->delete();
            DB::table('team_removal_votes')->delete();

            // Skills, education, languages and portfolio cascade with the
            // profile. Every student screen already copes with no row: sign up
            // makes none.
            DB::table('student_profiles')->delete();
            DB::table('student_credentials')->delete();
            DB::table('student_verifications')
                ->where('provider', VerificationProvider::Document->value)
                ->delete();

            DB::table('client_profiles')->update(
                array_fill_keys(self::CLIENT_PROFILE_INPUT, null) + ['updated_at' => now()],
            );

            DB::table('users')->whereIn('id', $peopleIds)->update(['avatar_path' => null]);

            $this->returnStudentsToTheirOwnTeams();
        });

        $this->deleteUploads($attachments, $peopleIds);

        $this->components->info('Every student and client is back to how they were at sign up.');

        return self::SUCCESS;
    }

    /**
     * Take each student off the teams they joined and hand them back one of their own.
     *
     * Owner is never assignable, so the teams a student owns are exactly the
     * ones they created. Joining dissolved their own team (see JoinTeam), so
     * someone who only ever sat on another person's team comes out with none —
     * GiveOwnTeam makes them a fresh one, the same kind sign up does.
     */
    private function returnStudentsToTheirOwnTeams(): void
    {
        DB::table('team_members')
            ->whereIn('user_id', $this->students()->select('id'))
            ->where('role', '!=', TeamRole::Owner->value)
            ->delete();

        $this->students()->each(function (User $student): void {
            $this->giveOwnTeam->handle($student)->update(['name' => $student->name."'s Team"]);
        });
    }

    /**
     * Remove the files behind the rows that were cleared.
     *
     * Runs after the transaction commits, so a failed reset never leaves rows
     * pointing at files that are gone.
     *
     * @param  list<array{disk: string, path: string}>  $attachments
     * @param  list<int>  $peopleIds
     */
    private function deleteUploads(array $attachments, array $peopleIds): void
    {
        foreach (self::UPLOAD_DIRECTORIES as $disk => $directories) {
            foreach ($directories as $directory) {
                Storage::disk($disk)->deleteDirectory($directory);
            }
        }

        foreach ($peopleIds as $id) {
            Storage::disk('public')->deleteDirectory('avatars/'.$id);
        }

        foreach ($attachments as $attachment) {
            Storage::disk($attachment['disk'])->delete($attachment['path']);
        }
    }

    /**
     * Every student and client — everyone but the administrators.
     *
     * @return Builder<User>
     */
    private function people(): Builder
    {
        return User::query()->where('role', '!=', UserRole::Admin->value);
    }

    /**
     * Every student.
     *
     * @return Builder<User>
     */
    private function students(): Builder
    {
        return User::query()->where('role', UserRole::Student->value);
    }

    /**
     * Count what the reset clears, for the report printed before it runs.
     *
     * @return array<string, int>
     */
    private function counts(): array
    {
        return [
            'Project postings' => DB::table('projects')->count(),
            'Applications' => DB::table('applications')->count(),
            'Agreements' => DB::table('agreements')->count(),
            'Transactions' => DB::table('transactions')->count(),
            'Chats' => DB::table('conversations')->count(),
            'Messages' => DB::table('messages')->count(),
            'Calls' => DB::table('meetings')->count(),
            'Notifications' => DB::table('notifications')->count(),
            'Testimonials' => DB::table('testimonials')->count(),
            'Team invitations' => DB::table('team_invitations')->count(),
            'Joined team memberships' => DB::table('team_members')
                ->whereIn('user_id', $this->students()->select('id'))
                ->where('role', '!=', TeamRole::Owner->value)
                ->count(),
            'Student profiles' => DB::table('student_profiles')->count(),
            'Student credentials' => DB::table('student_credentials')->count(),
            'Document verifications' => DB::table('student_verifications')
                ->where('provider', VerificationProvider::Document->value)
                ->count(),
            'Client profiles (reset to sign up)' => DB::table('client_profiles')->count(),
            'Uploaded photos' => $this->people()->whereNotNull('avatar_path')->count(),
        ];
    }
}
