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
    */

    'acknowledgements' => [
        'intellectual_property' => 'I understand and agree to the intellectual property terms set out in this agreement.',
        'availability' => 'I confirm that this project timeline aligns with my academic availability.',
        'confidentiality' => 'I agree to maintain confidentiality of all client proprietary information.',
        'schedule' => 'I commit to the milestone schedule and delivery dates outlined above.',
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
    | The starting text of a new agreement. The client edits it before anybody
    | signs; this is a sensible default, not a fixed contract, and the platform
    | is not offering legal advice by supplying it.
    |
    */

    'default_terms' => [

        'intellectual_property' => 'All deliverables produced under this agreement transfer to the client on final payment, including source code, database schema and written documentation. The developer keeps a non-exclusive licence to present the work in an academic portfolio; no commercial resale or derivative distribution is permitted.',

        'confidentiality' => 'The developer treats all client-proprietary information as strictly confidential. Project data may be hosted on platform-approved repositories only, and access is revoked at turnover.',

        'academic' => 'The client agrees to provide a formal evaluation of the project on completion and permits the developer to present the project architecture and outcomes to their academic panel. The developer must ensure academic milestone deadlines are prioritised alongside project deliverables.',

    ],

];
