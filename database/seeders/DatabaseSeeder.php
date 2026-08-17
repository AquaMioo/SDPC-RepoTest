<?php

namespace Database\Seeders;

use App\Actions\Teams\CreateTeam;
use App\Enums\CredentialStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Enums\VerificationStatus;
use App\Models\ClientProfile;
use App\Models\Course;
use App\Models\School;
use App\Models\Skill;
use App\Models\StudentProfile;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    /*
     * Deliberately not WithoutModelEvents: Team assigns its slug from a
     * creating hook, so silencing events makes every team insert fail on the
     * slug's NOT NULL constraint.
     */

    /**
     * Seed the application's database.
     *
     * Builds the accounts the team signs in with, plus two testers per module
     * so the student and client sides can both be exercised from two angles at
     * once — one account acting, another to receive.
     *
     * Accounts are built the way CreateNewUser builds them rather than through
     * the factory, so a tester is indistinguishable from someone who genuinely
     * registered: same role, same team shape, same client profile.
     *
     * The testers arrive verified with their profiles already filled in. An
     * empty, unverified account cannot post work or be hired — every write
     * route sits behind EnsureAccountIsVerified — so a tester that still had
     * to be walked through document review before it could do anything would
     * not be much of a tester.
     *
     * Passwords come from config/seeding.php, which reads the environment.
     */
    public function run(): void
    {
        $this->call(ClientModuleTaxonomySeeder::class);

        $adminPassword = (string) config('seeding.admin_password');
        $testerPassword = (string) config('seeding.tester_password');

        // The one way into the admin portal. There is no Google button there,
        // so this password is the only key.
        $this->createAccount(
            name: 'SDPC Administrator',
            email: 'sdpc@admin.test',
            role: UserRole::Admin,
            password: $adminPassword,
        );

        $studentOne = $this->createAccount(
            name: 'Student Tester One',
            email: 'student.one@sdpc.test',
            role: UserRole::Student,
            password: $testerPassword,
        );

        $this->fillStudentProfile($studentOne, [
            'headline' => 'Laravel and React developer · 4th year BSIT',
            'biography' => 'Fourth-year IT student from San Jose Del Monte. I build inventory and point-of-sale systems for small businesses, and I have shipped two capstone-scale Laravel applications end to end.',
            'school' => 'City College of Technology',
            'course' => 'BS Information Technology',
            'year_level' => 4,
            'github_url' => 'https://github.com/student-tester-one',
            'portfolio_url' => 'https://student-tester-one.test',
            'hourly_rate' => 250,
        ], skills: ['PHP', 'Laravel', 'React', 'MySQL', 'Tailwind CSS']);

        $studentTwo = $this->createAccount(
            name: 'Student Tester Two',
            email: 'student.two@sdpc.test',
            role: UserRole::Student,
            password: $testerPassword,
        );

        $this->fillStudentProfile($studentTwo, [
            'headline' => 'Mobile developer · 3rd year BSCS',
            'biography' => 'Third-year Computer Science student focused on Flutter. I build the phone half of a system — ordering, delivery tracking, and anything a customer holds in their hand.',
            'school' => 'Northgate Institute of Technology',
            'course' => 'BS Computer Science',
            'year_level' => 3,
            'github_url' => 'https://github.com/student-tester-two',
            'portfolio_url' => 'https://student-tester-two.test',
            'hourly_rate' => 220,
        ], skills: ['Dart', 'Flutter', 'Firebase', 'UI/UX Design']);

        $this->createAccount(
            name: 'Client Tester One',
            email: 'client.one@sdpc.test',
            role: UserRole::Client,
            password: $testerPassword,
            businessName: 'Northwind Trading',
            businessProfile: [
                'business_description' => 'A family-run hardware and construction supply store serving contractors around San Jose Del Monte since 2009. We are moving off paper ledgers and need a point-of-sale and stock system that our counter staff can actually use.',
                'address' => '128 Quirino Highway, Barangay Tungkong Mangga',
                'city' => 'San Jose Del Monte',
                'province' => 'Bulacan',
                'phone_number' => '+63 917 555 0142',
                'website_url' => 'https://northwind-trading.test',
                'facebook_url' => 'https://facebook.com/northwindtrading.test',
            ],
        );

        $this->createAccount(
            name: 'Client Tester Two',
            email: 'client.two@sdpc.test',
            role: UserRole::Client,
            password: $testerPassword,
            businessName: 'Bayview Logistics',
            businessProfile: [
                'business_description' => 'A regional delivery and freight forwarding company running routes between Bulacan and Metro Manila. We want drivers and dispatchers on one screen instead of a group chat.',
                'address' => '7 Gov. Fortunato Halili Avenue, Barangay Muzon',
                'city' => 'San Jose Del Monte',
                'province' => 'Bulacan',
                'phone_number' => '+63 918 555 0177',
                'website_url' => 'https://bayview-logistics.test',
                'facebook_url' => 'https://facebook.com/bayviewlogistics.test',
            ],
        );

        // Left pending on purpose so the admin Users screen has something in
        // its review queue on a fresh install. No profile and no credential —
        // this one is meant to look like someone who just signed up.
        $this->createAccount(
            name: 'Adrian Seth Lagasca',
            email: 'pending.student@sdpc.test',
            role: UserRole::Student,
            password: $testerPassword,
            status: UserStatus::Pending,
        );
    }

    /**
     * Create one account with the team the rest of the platform assumes.
     *
     * Idempotent on the email, so re-seeding an existing database refreshes the
     * password and the profile rather than colliding on the unique index.
     *
     * @param  array<string, string>  $businessProfile
     */
    private function createAccount(
        string $name,
        string $email,
        UserRole $role,
        string $password,
        UserStatus $status = UserStatus::Approved,
        ?string $businessName = null,
        array $businessProfile = [],
    ): User {
        return DB::transaction(function () use ($name, $email, $role, $password, $status, $businessName, $businessProfile): User {
            $user = User::firstWhere('email', $email) ?? new User;

            $user->forceFill([
                'name' => $name,
                'first_name' => str($name)->before(' ')->toString(),
                'last_name' => str($name)->afterLast(' ')->toString(),
                'email' => $email,
                'password' => $password,
                'role' => $role,
                'status' => $status,
                'email_verified_at' => now(),
            ])->save();

            // An administrator lives outside the team-scoped half of the
            // platform, so they get no team at all.
            if ($role === UserRole::Admin) {
                return $user;
            }

            $team = $user->teams()->first() ?? app(CreateTeam::class)->handle(
                $user,
                $businessName ?? $name."'s Team",
                isPersonal: $role !== UserRole::Client,
            );

            if ($businessName !== null) {
                $this->fillBusinessProfile($team, $name, $email, $businessName, $businessProfile);
            }

            return $user;
        });
    }

    /**
     * Fill in a tester business and mark it verified.
     *
     * Verification is what unlocks posting work and hiring, so a client tester
     * without it can sign in and look around but cannot exercise the module.
     *
     * @param  array<string, string>  $attributes
     */
    private function fillBusinessProfile(
        Team $team,
        string $ownerName,
        string $email,
        string $businessName,
        array $attributes,
    ): void {
        ClientProfile::updateOrCreate(
            ['team_id' => $team->id],
            [
                ...$attributes,
                'business_name' => $businessName,
                'owner_name' => $ownerName,
                'contact_email' => $email,
                /*
                 * A stand-in for the permit an administrator would normally
                 * have accepted. No file is written — nothing reads the
                 * document itself, only that a decision was recorded.
                 */
                'permit_path' => 'permits/seeded/'.Str::slug($businessName).'.pdf',
                'verification_status' => VerificationStatus::Verified,
                'verified_at' => now(),
            ],
        );
    }

    /**
     * Fill in a tester's student profile and verify their credential.
     *
     * @param  array<string, mixed>  $attributes
     * @param  list<string>  $skills
     */
    private function fillStudentProfile(User $user, array $attributes, array $skills): void
    {
        $school = School::firstWhere('slug', Str::slug($attributes['school']));
        $course = Course::firstWhere('slug', Str::slug($attributes['course']));

        $profile = StudentProfile::updateOrCreate(
            ['user_id' => $user->id],
            [
                ...collect($attributes)->except(['school', 'course'])->all(),
                'school_id' => $school?->id,
                'course_id' => $course?->id,
                'is_available' => true,
            ],
        );

        $profile->skills()->sync(
            Skill::query()->whereIn('slug', array_map(Str::slug(...), $skills))->pluck('id'),
        );

        $this->verifyStudentCredential($user, $attributes['school']);
    }

    /**
     * Record an already-approved credential for a tester.
     *
     * Mirrors what the admin credential screen writes when it accepts a
     * document, so isVerifiedForOperating() reads true without anyone having
     * to upload and review a file first.
     */
    private function verifyStudentCredential(User $user, string $school): void
    {
        $credential = $user->studentCredentials()->firstOrNew([]);

        $credential->forceFill([
            'user_id' => $user->id,
            'school' => $school,
            'disk' => 'local',
            'path' => 'credentials/seeded/'.Str::slug($user->name).'.pdf',
            'original_name' => 'registration-form.pdf',
            'mime_type' => 'application/pdf',
            'size' => 182_400,
            'checksum' => hash('sha256', $user->email),
            'status' => CredentialStatus::Verified,
            'reviewed_at' => now(),
        ])->save();
    }
}
