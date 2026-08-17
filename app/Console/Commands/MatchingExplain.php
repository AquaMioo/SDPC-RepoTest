<?php

namespace App\Console\Commands;

use App\Models\StudentProfile;
use App\Services\Matching\MatchingEngine;
use App\Services\Matching\ScopeProfile;
use App\Services\Matching\SkillInference;
use Illuminate\Console\Command;

/**
 * Explains a match without needing a browser or a login.
 *
 * The matching vocabulary is a guess about how local businesses describe what
 * they want. This is how that guess gets corrected: type in a real request and
 * see whether the platform understood it. A phrase that comes back with no
 * skills is a hole in the vocabulary, and finding those is the whole point.
 */
class MatchingExplain extends Command
{
    /**
     * @var string
     */
    protected $signature = 'matching:explain
                            {scope* : What a client wants built, in their own words}
                            {--limit=8 : How many students to list}';

    /**
     * @var string
     */
    protected $description = 'Show what the matcher understood from a phrase, and who it ranks for it';

    /**
     * Execute the console command.
     */
    public function handle(SkillInference $inference, MatchingEngine $engine): int
    {
        $phrase = implode(' ', $this->argument('scope'));
        $scope = ScopeProfile::fromSearch($phrase, $inference);

        $this->newLine();
        $this->line("  <options=bold>\"{$phrase}\"</>");
        $this->newLine();

        if (! $scope->isMeaningful()) {
            $this->components->error('Nothing recognised in that phrase.');
            $this->line('  The matcher found no skills, so it would rank nobody.');
            $this->line('  That is a gap worth filling — tell your developer what');
            $this->line('  building this kind of system actually takes.');
            $this->newLine();

            return self::FAILURE;
        }

        $this->components->twoColumnDetail(
            '<fg=gray>Words it recognised</>',
            $scope->phrases->isEmpty() ? '<fg=gray>none</>' : $scope->phrases->implode(', '),
        );
        $this->components->twoColumnDetail(
            '<fg=gray>Skills that implies</>',
            $scope->allSkills()->implode(', '),
        );

        $students = StudentProfile::with(['user', 'skills'])->get();

        if ($students->isEmpty()) {
            $this->newLine();
            $this->components->warn('No student profiles exist yet, so there is nobody to rank.');

            return self::SUCCESS;
        }

        $this->newLine();
        $this->line('  <options=bold>Ranked students</>');
        $this->newLine();

        $students
            ->map(fn (StudentProfile $profile) => [
                'profile' => $profile,
                'result' => $engine->score($scope, $profile),
            ])
            ->sortByDesc(fn (array $row) => $row['result']->compatibility)
            ->take((int) $this->option('limit'))
            ->each(function (array $row) {
                $name = $row['profile']->user->name;
                $score = $row['result']->compatibility;

                $this->components->twoColumnDetail(
                    "  {$name}",
                    $this->tint($score)."{$score}%</>",
                );
                $this->line("     <fg=gray>{$row['result']->insight}</>");
            });

        $this->newLine();

        return self::SUCCESS;
    }

    /**
     * Colour a score so a weak match is obvious at a glance.
     */
    protected function tint(int $score): string
    {
        return match (true) {
            $score >= 70 => '<fg=green>',
            $score >= 50 => '<fg=yellow>',
            default => '<fg=red>',
        };
    }
}
