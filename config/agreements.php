<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Reference Prefix
    |--------------------------------------------------------------------------
    |
    | Agreements are quoted by reference in messages, in email and on paper, so
    | they need a short human-readable identifier rather than a database id.
    | The year and a sequence are appended: SDPC-2026-014.
    |
    */

    'reference_prefix' => env('AGREEMENT_REFERENCE_PREFIX', 'SDPC'),

    /*
    |--------------------------------------------------------------------------
    | Acknowledgements
    |--------------------------------------------------------------------------
    |
    | The statements each party ticks before signing. Both sides must tick all
    | of them — a signature with anything missing is refused, because a partial
    | acknowledgement is not an agreement.
    |
    | The keys are stored on the signature row, so changing one here does not
    | rewrite what somebody already agreed to; add a new key instead and let
    | the old signatures keep their own record.
    |
    | `acknowledgements` go with the Memorandum of Agreement below, which every
    | new agreement uses. `clause_acknowledgements` stay for the agreements
    | somebody signed under the earlier clauses (AgreementTemplate::Clauses).
    |
    */

    'acknowledgements' => [
        'moa_responsibilities' => 'I understand and agree to my responsibilities set out in this memorandum.',
        'moa_scope' => 'I understand that major updates or features beyond the approved scope need a separate written agreement and additional payment.',
        'moa_confidentiality' => 'I agree to protect sensitive information shared during the project.',
        'moa_duration' => 'I understand this agreement remains valid until the team passes Capstone 2, unless ended earlier by mutual consent.',
    ],

    'clause_acknowledgements' => [
        'intellectual_property' => 'I understand and agree to the intellectual property terms set out in this agreement.',
        'availability' => 'I confirm that this project timeline aligns with my academic availability.',
        'confidentiality' => 'I agree to maintain confidentiality of all client proprietary information.',
        'schedule' => 'I commit to the milestone schedule and delivery dates outlined above.',
    ],

    /*
    |--------------------------------------------------------------------------
    | Memorandum of Agreement
    |--------------------------------------------------------------------------
    |
    | The school's Memorandum of Agreement between the client and the student
    | developer team, as the contract screen shows it. The blanks of the paper
    | form are filled from the agreement itself:
    |
    |   :project  the project title
    |   :starts   the date the client set for the work to start, or "the date
    |             both parties sign" while none is set
    |
    | **Double asterisks** mark the words the paper form prints in bold.
    |
    */

    'memorandum' => [
        'title' => 'Memorandum of Agreement',

        'purpose' => 'This Agreement establishes cooperation between the Client and the Student Developer Team for the project entitled ":project", setting clear responsibilities, scope, and terms.',

        'commitments' => [
            'The Student Developer Team agrees to design, develop, and deliver the project based on the approved scope.',
            'The Client agrees to provide necessary information, resources, and timely feedback.',
            'Major updates or additional features beyond the agreed scope will not be accepted unless accompanied by a separate written agreement and additional payment.',
        ],

        'sections' => [
            [
                'heading' => 'Client',
                'items' => [
                    'Provide clear requirements and supporting materials.',
                    'Review and approve deliverables promptly.',
                    'Discuss and agree on payment terms directly with the Student Developer Team.',
                ],
            ],
            [
                'heading' => 'Student Developer Team',
                'items' => [
                    'Deliver work according to the approved scope and timeline.',
                    'Provide regular updates and progress reports.',
                    'Maintain professionalism, confidentiality, and quality standards.',
                ],
            ],
            [
                'heading' => 'Revisions',
                'body' => 'Minor revisions necessary for project completion are acceptable. Major updates require additional payment.',
            ],
            [
                'heading' => 'Duration',
                'body' => 'This Agreement shall take effect on :starts and remain valid **until the Student/Team has successfully passed Capstone 2**, unless terminated earlier by mutual consent.',
            ],
            [
                'heading' => 'Termination',
                'body' => 'Either party may request the termination of this Agreement for a valid reason, subject to proper notification and approval. Work completed up to termination shall be compensated accordingly.',
            ],
            [
                'heading' => 'Confidentiality',
                'body' => 'Both parties agree to protect sensitive information shared during the project.',
            ],
        ],

        'closing' => 'This Agreement is effective upon signing and remains valid until project completion or mutual termination.',
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Milestones
    |--------------------------------------------------------------------------
    |
    | The empty structure a new agreement starts with. Titles only — every
    | amount is zero and every date is blank until the client fills them in,
    | so nothing here asserts a price or a deadline the two sides never agreed.
    |
    | Their purpose is to give the client a shape to fill rather than a blank
    | page, and to guarantee that the progress ring and the Project process
    | screen always have rows to read.
    |
    */

    'default_milestones' => ['Design', 'Build', 'Turnover'],

    /*
    |--------------------------------------------------------------------------
    | Default Terms
    |--------------------------------------------------------------------------
    |
    | The clauses agreements were written in before the Memorandum of
    | Agreement. Still stored on every new agreement, and still what an
    | agreement signed under them shows (AgreementTemplate::Clauses), but no
    | longer shown for — or edited on — a memorandum. Not legal advice.
    |
    */

    'default_terms' => [

        'intellectual_property' => 'All deliverables produced under this agreement transfer to the client on final payment, including source code, database schema and written documentation. The developer keeps a non-exclusive licence to present the work in an academic portfolio; no commercial resale or derivative distribution is permitted.',

        'confidentiality' => 'The developer treats all client-proprietary information as strictly confidential. Project data may be hosted on platform-approved repositories only, and access is revoked at turnover.',

        'academic' => 'The client agrees to provide a formal evaluation of the project on completion and permits the developer to present the project architecture and outcomes to their academic panel. The developer must ensure academic milestone deadlines are prioritised alongside project deliverables.',

    ],

];
