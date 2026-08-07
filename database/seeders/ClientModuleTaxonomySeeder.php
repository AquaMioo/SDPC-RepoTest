<?php

namespace Database\Seeders;

use App\Enums\SkillType;
use App\Models\Course;
use App\Models\School;
use App\Models\Skill;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Seeds the lookup data the posting form and recruit filters read from.
 *
 * Idempotent, so it can be re-run as the taxonomy grows without duplicating
 * rows or detaching anything already linked to a project.
 */
class ClientModuleTaxonomySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->seedSkills();
        $this->seedSchools();
        $this->seedCourses();
    }

    /**
     * Seed the skill taxonomy across all four types.
     */
    protected function seedSkills(): void
    {
        $skills = [
            SkillType::Language->value => [
                'PHP', 'JavaScript', 'TypeScript', 'Python', 'Java',
                'C#', 'Kotlin', 'Swift', 'Dart', 'Go',
            ],
            SkillType::Framework->value => [
                'Laravel', 'React', 'Vue', 'Next.js', 'Livewire',
                'Django', 'Flask', 'Spring Boot', 'Flutter', 'React Native',
                '.NET', 'Tailwind CSS',
            ],
            SkillType::Database->value => [
                'MySQL', 'PostgreSQL', 'SQLite', 'MongoDB',
                'Firebase', 'Redis', 'Microsoft SQL Server',
            ],
            SkillType::General->value => [
                'UI/UX Design', 'System Analysis', 'Technical Writing',
                'Quality Assurance', 'Project Management', 'Forecasting',
                'Data Analytics', 'API Integration', 'Payment Integration',
                'Deployment & DevOps',
            ],
        ];

        foreach ($skills as $type => $names) {
            foreach ($names as $name) {
                Skill::updateOrCreate(
                    ['slug' => Str::slug($name)],
                    ['name' => $name, 'type' => $type],
                );
            }
        }
    }

    /**
     * Seed the schools students enrol at.
     *
     * Placeholder institutions and a placeholder city, so a public repository
     * does not carry a real campus list. Replace with the real values in your
     * own environment before demoing.
     */
    protected function seedSchools(): void
    {
        $schools = [
            'City College of Technology',
            'Northgate Institute of Technology',
            'Riverside Polytechnic College',
            'St. Andrew College',
            'Metro State University — Main Campus',
            'Central Computer College',
        ];

        foreach ($schools as $name) {
            School::updateOrCreate(
                ['slug' => Str::slug($name)],
                ['name' => $name, 'city' => 'Sample City'],
            );
        }
    }

    /**
     * Seed the courses the preferred-course filter offers.
     */
    protected function seedCourses(): void
    {
        $courses = [
            'BS Information Technology' => 'BSIT',
            'BS Computer Science' => 'BSCS',
            'BS Information Systems' => 'BSIS',
            'BS Computer Engineering' => 'BSCpE',
            'BS Entertainment and Multimedia Computing' => 'BSEMC',
            'Associate in Computer Technology' => 'ACT',
        ];

        foreach ($courses as $name => $abbreviation) {
            Course::updateOrCreate(
                ['slug' => Str::slug($name)],
                ['name' => $name, 'abbreviation' => $abbreviation],
            );
        }
    }
}
