<?php

namespace App\Enums;

/**
 * Which wording an agreement was written in.
 *
 * Every new agreement uses the school's Memorandum of Agreement. Agreements
 * somebody had already signed before the memorandum arrived keep the clauses
 * they were signed against — a signature must stay attached to the words it
 * was given for.
 */
enum AgreementTemplate: string
{
    case Memorandum = 'memorandum';
    case Clauses = 'clauses';

    /**
     * The statements each party ticks before signing this wording, keyed as
     * they are stored on the signature row.
     *
     * @return array<string, string>
     */
    public function acknowledgements(): array
    {
        return (array) config(match ($this) {
            self::Memorandum => 'agreements.acknowledgements',
            self::Clauses => 'agreements.clause_acknowledgements',
        }, []);
    }
}
