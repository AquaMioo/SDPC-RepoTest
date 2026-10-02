<?php

namespace App\Enums;

/**
 * Which wording an agreement was written in.
 *
 * Every new agreement uses the SDPC Memorandum of Agreement. Agreements
 * somebody had already signed before it arrived keep the wording they were
 * signed against — the earlier memorandum, or the clauses before that — since
 * a signature must stay attached to the words it was given for.
 */
enum AgreementTemplate: string
{
    /** The SDPC Memorandum of Agreement (sections I–X), adopted 2026-10-03. */
    case SdpcMemorandum = 'sdpc_moa';

    /** The earlier school memorandum, kept for agreements signed under it. */
    case Memorandum = 'memorandum';

    /** The clauses agreements were written in before any memorandum. */
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
            self::SdpcMemorandum, self::Memorandum => 'agreements.acknowledgements',
            self::Clauses => 'agreements.clause_acknowledgements',
        }, []);
    }

    /**
     * The school's blank form for this wording, for printing and signing by
     * hand, or null when the wording never had one.
     */
    public function blankForm(): ?string
    {
        return match ($this) {
            self::SdpcMemorandum => resource_path('documents/sdpc-memorandum-of-agreement.pdf'),
            self::Memorandum => resource_path('documents/memorandum-of-agreement.pdf'),
            self::Clauses => null,
        };
    }
}
