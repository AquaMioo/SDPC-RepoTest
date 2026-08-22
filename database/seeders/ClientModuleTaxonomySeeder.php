<?php

namespace Database\Seeders;

use App\Enums\SkillType;
use App\Models\Barangay;
use App\Models\Course;
use App\Models\Location;
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
        $this->seedLocations();
    }

    /**
     * Seed the places a business profile can name.
     *
     * Bulacan only, because that is the province the platform serves — a
     * nationwide list would be mostly rows nobody ever picks. "San Jose Del
     * Monte" is spelled the way the seeded profiles already spell it, so the
     * businesses that exist keep validating against this list.
     */
    protected function seedLocations(): void
    {
        $bulacan = [
            'Angat',
            'Balagtas',
            'Baliuag',
            'Bocaue',
            'Bulakan',
            'Bustos',
            'Calumpit',
            'Doña Remedios Trinidad',
            'Guiguinto',
            'Hagonoy',
            'Malolos',
            'Marilao',
            'Meycauayan',
            'Norzagaray',
            'Obando',
            'Pandi',
            'Paombong',
            'Plaridel',
            'Pulilan',
            'San Ildefonso',
            'San Jose Del Monte',
            'San Miguel',
            'San Rafael',
            'Santa Maria',
        ];

        foreach ($bulacan as $city) {
            Location::updateOrCreate(
                ['slug' => Str::slug('bulacan-'.$city)],
                ['province' => 'Bulacan', 'city' => $city],
            );
        }

        $this->seedBarangays();
    }

    /**
     * Seed the barangays of San Jose Del Monte.
     *
     * VERIFY THIS LIST against the city's own roster before anyone relies on
     * it. San Jose Del Monte has 59 barangays; the names below were written
     * from general knowledge rather than copied from an official source, so
     * treat spellings and completeness as unconfirmed. It is one array —
     * correcting it and re-running this seeder is the whole fix.
     */
    protected function seedBarangays(): void
    {
        $sanJoseDelMonte = Location::firstWhere([
            'province' => 'Bulacan',
            'city' => 'San Jose Del Monte',
        ]);

        if ($sanJoseDelMonte === null) {
            return;
        }

        $barangays = [
            'Assumption', 'Bagong Buhay I', 'Bagong Buhay II', 'Bagong Buhay III',
            'Citrus', 'Ciudad Real', 'Dulong Bayan', 'Fatima I', 'Fatima II',
            'Fatima III', 'Fatima IV', 'Fatima V', 'Francisco Homes–Guijo',
            'Francisco Homes–Mulawin', 'Francisco Homes–Narra',
            'Francisco Homes–Yakal', 'Gaya-Gaya', 'Graceville', 'Gumaoc Central',
            'Gumaoc East', 'Gumaoc West', 'Kaybanban', 'Kaypian', 'Lawang Pari',
            'Maharlika', 'Minuyan I', 'Minuyan II', 'Minuyan III', 'Minuyan IV',
            'Minuyan V', 'Minuyan Proper', 'Muzon', 'Paradise III', 'Poblacion',
            'Poblacion I', 'San Manuel', 'San Martin I', 'San Martin II',
            'San Martin III', 'San Martin IV', 'San Pedro', 'San Rafael I',
            'San Rafael II', 'San Rafael III', 'San Rafael IV', 'San Rafael V',
            'San Roque', 'Santa Cruz I', 'Santa Cruz II', 'Santa Cruz III',
            'Santa Cruz IV', 'Santa Cruz V', 'Santo Cristo', 'Santo Niño I',
            'Santo Niño II', 'Sapang Palay Proper', 'Tungkong Mangga',
        ];

        foreach ($barangays as $name) {
            Barangay::updateOrCreate(
                ['slug' => Str::slug('sjdm-'.$name)],
                ['location_id' => $sanJoseDelMonte->id, 'name' => $name],
            );
        }
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
