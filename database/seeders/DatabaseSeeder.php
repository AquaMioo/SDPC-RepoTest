<?php

namespace Database\Seeders;

use App\Actions\Teams\CreateTeam;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\ClientProfile;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

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

        $this->createAccount(
            name: 'Student Tester One',
            email: 'student.one@sdpc.test',
            role: UserRole::Student,
            password: $testerPassword,
        );

        $this->createAccount(
            name: 'Student Tester Two',
            email: 'student.two@sdpc.test',
            role: UserRole::Student,
            password: $testerPassword,
        );

        $this->createAccount(
            name: 'Client Tester One',
            email: 'client.one@sdpc.test',
            role: UserRole::Client,
            password: $testerPassword,
            businessName: 'Northwind Trading',
        );

        $this->createAccount(
            name: 'Client Tester Two',
            email: 'client.two@sdpc.test',
            role: UserRole::Client,
            password: $testerPassword,
            businessName: 'Bayview Logistics',
        );

        // Left pending on purpose so the admin Users screen has something in
        // its review queue on a fresh install.
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
     * password rather than colliding on the unique index.
     */
    private function createAccount(
        string $name,
        string $email,
        UserRole $role,
        string $password,
        UserStatus $status = UserStatus::Approved,
        ?string $businessName = null,
    ): User {
        return DB::transaction(function () use ($name, $email, $role, $password, $status, $businessName): User {
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
            if ($role === UserRole::Admin || $user->teams()->exists()) {
                return $user;
            }

            $team = app(CreateTeam::class)->handle(
                $user,
                $businessName ?? $name."'s Team",
                isPersonal: $role !== UserRole::Client,
            );

            if ($businessName !== null) {
                ClientProfile::create([
                    'team_id' => $team->id,
                    'business_name' => $businessName,
                    'owner_name' => $name,
                    'contact_email' => $email,
                ]);
            }

            return $user;
        });
    }
}
