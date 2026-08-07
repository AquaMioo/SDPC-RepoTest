<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     *
     * One account per role, so both portals can be signed into straight after a
     * migrate:fresh. Every account uses the password "password".
     */
    public function run(): void
    {
        User::factory()->admin()->approved()->create([
            'name' => 'Nigen A. Bustamante',
            'first_name' => 'Nigen',
            'last_name' => 'Bustamante',
            'email' => 'admin@sdpc.test',
        ]);

        User::factory()->client()->approved()->create([
            'name' => 'Samuel L. Clemens',
            'first_name' => 'Samuel',
            'last_name' => 'Clemens',
            'email' => 'client@sdpc.test',
        ]);

        User::factory()->student()->approved()->create([
            'name' => 'Kristiane Dela Pena',
            'first_name' => 'Kristiane',
            'last_name' => 'Dela Pena',
            'email' => 'student@sdpc.test',
        ]);

        // Left pending on purpose so the admin Users screen has something in
        // its review queue on a fresh install.
        User::factory()->student()->create([
            'name' => 'Adrian Seth Lagasca',
            'first_name' => 'Adrian',
            'last_name' => 'Lagasca',
            'email' => 'adrian@sdpc.test',
        ]);
    }
}
