<?php

namespace App\Enums;

/**
 * How well a student speaks a language they have listed.
 *
 * A short ladder on purpose. The point of the field is to tell a client
 * whether a handover document, a call or a training session can happen in this
 * language — not to grade anybody, so the rungs are the ones a person can pick
 * honestly about themselves without a test in front of them.
 */
enum LanguageProficiency: string
{
    /** Reads and writes a little; not enough to run a meeting. */
    case Elementary = 'elementary';

    /** Comfortable day to day, and in writing with time to think. */
    case Conversational = 'conversational';

    /** Can run a client meeting and write the handover document. */
    case Professional = 'professional';

    /** A first language, or as good as. */
    case NativeOrBilingual = 'native_or_bilingual';

    /**
     * Get the display label for the proficiency.
     */
    public function label(): string
    {
        return match ($this) {
            self::Elementary => __('Elementary'),
            self::Conversational => __('Conversational'),
            self::Professional => __('Professional working'),
            self::NativeOrBilingual => __('Native or Bilingual'),
        };
    }

    /**
     * Get every proficiency as a value/label pair, for a select.
     *
     * @return array<int, array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $proficiency): array => [
                'value' => $proficiency->value,
                'label' => $proficiency->label(),
            ],
            self::cases(),
        );
    }
}
