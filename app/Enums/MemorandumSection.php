<?php

namespace App\Enums;

/**
 * The parts of the SDPC Memorandum of Agreement either side may add to.
 *
 * Each case is a section of the paper form whose last line is "add more here
 * if required". The base wording of every section is the school's and nobody
 * edits it; what a party adds is appended after it, and only the person who
 * added an entry may change or remove it.
 *
 * Section VII is the one that must have entries: no signature is accepted
 * until it describes at least one service. Its entries are the agreement's
 * phases themselves (agreement_milestones before Turnover: the title is the
 * Objective, the description the Scope), which is how they reach Project
 * Management. The other sections keep their entries in agreement_requirements.
 */
enum MemorandumSection: string
{
    /** IV. Customer Agency Responsibilities (CA2). Optional. */
    case CustomerAgency = 'customer_agency';

    /** V. Contracting Agency Responsibilities (CA1). Optional. */
    case ContractingAgency = 'contracting_agency';

    /** VI. Contracting Agency and Customer Agency Responsibilities. Optional. */
    case Joint = 'joint';

    /** VII. Description of Services. Required before anyone may sign. */
    case Services = 'services';

    /** VIII. Compliance to Regulations. Optional. */
    case Compliance = 'compliance';

    /** IX. Terms of Agreement. Optional. */
    case Terms = 'terms';

    /**
     * Whether an agreement has to have an entry here before it can be signed.
     */
    public function isRequired(): bool
    {
        return $this === self::Services;
    }

    /**
     * The sections whose entries are stored in agreement_requirements.
     *
     * @return list<self>
     */
    public static function requirementSections(): array
    {
        return array_values(array_filter(
            self::cases(),
            fn (self $section): bool => $section !== self::Services,
        ));
    }
}
