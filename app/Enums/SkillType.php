<?php

namespace App\Enums;

enum SkillType: string
{
    case Language = 'language';
    case Framework = 'framework';
    case Database = 'database';
    case General = 'general';

    /**
     * Get the display label for the skill type.
     */
    public function label(): string
    {
        return match ($this) {
            self::Language => 'Programming language',
            self::Framework => 'Framework',
            self::Database => 'Database',
            self::General => 'Skill',
        };
    }

    /**
     * Get the plural label used for filter group headings.
     */
    public function groupLabel(): string
    {
        return match ($this) {
            self::Language => 'Programming languages',
            self::Framework => 'Frameworks',
            self::Database => 'Databases',
            self::General => 'Skills',
        };
    }
}
