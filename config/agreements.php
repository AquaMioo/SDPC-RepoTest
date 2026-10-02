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
    | SDPC Memorandum of Agreement
    |--------------------------------------------------------------------------
    |
    | The wording of every new agreement: the SDPC MOA template, sections I to
    | X, word for word (resources/documents/sdpc-memorandum-of-agreement.pdf is
    | the blank form). Nobody edits this text on the site. The blanks are
    | filled from the agreement by PresentAgreement:
    |
    |   :ca1                 CA1, the Contracting Agency: the client's company
    |                        name, or its representative when it has none
    |   :ca2                 CA2, the Customer Agency: the student representative
    |   :ca1_representative  the client's representative (the individual at CA1)
    |   :ca2_representative  the student representative (the individual at CA2)
    |   :services            the Description of Services: the project title
    |
    | **Double asterisks** mark bold runs. `section` names the sections either
    | side may add to (App\Enums\MemorandumSection); `addition.placeholder` is
    | the template's "add more here" line, which the editor shows as a prompt
    | and the printed copy leaves out when nothing was added. Section VII's
    | additions are required, and each is an Objective with its Scope.
    |
    | The template prints IX's "Revisions and Modifications" paragraph twice,
    | word for word; the copy is kept once here.
    |
    */

    'sdpc_memorandum' => [
        'title' => 'MEMORANDUM OF AGREEMENT (MOA)',
        'footer' => 'SDPC MOA',

        'sections' => [
            [
                'numeral' => 'I',
                'heading' => 'PARTIES',
                'blocks' => [
                    ['paragraph' => 'The parties to this MOA are the CONTRACTING AGENCY, **:ca1**, hereafter referenced as "CA1" and the CUSTOMER AGENCY, **:ca2**, referenced as "CA2".'],
                ],
            ],
            [
                'numeral' => 'II',
                'heading' => 'PURPOSE',
                'blocks' => [
                    ['paragraph' => 'The purpose of this Memorandum of Agreement (MOA) is to specifically identify the scope of services to be provided, establish the authorized individual or agency as the decision-maker or owner in the agreement, as well as the financial and human resources that are required for the provision of services. This MOA will also clearly identify the roles and responsibilities of each party as they relate to providing the consolidated services that serve both CA1 and CA2.'],
                ],
            ],
            [
                'numeral' => 'III',
                'heading' => 'LINES OF AUTHORITY',
                'blocks' => [
                    ['numbered' => [
                        'CA1 and CA2 are legislatively established as separate agencies with distinct appropriations.',
                        'CA2 designates **:ca2_representative** as having the final authority on all policy or procedural matters pertaining to CA2.',
                        'The relationship between CA1 and CA2 as defined by this MOA is one of Contractor to customer.',
                        '**:ca1_representative** at CA1 and **:ca2_representative** at CA2 will be the Contract Administrators for this MOA.',
                    ]],
                ],
            ],
            [
                'numeral' => 'IV',
                'heading' => 'CUSTOMER AGENCY RESPONSIBILITIES',
                'section' => 'customer_agency',
                'new_page' => true,
                'blocks' => [
                    ['paragraph' => 'CUSTOMER AGENCY shall undertake the following activities during the duration of the MOA term:'],
                    ['numbered' => [
                        'Ensure adherence of CA2 to applicable federal and state laws and regulations and program guidelines.',
                        'Participate in training and meetings as requested by CA1.',
                        "Review and approve all documentation evidencing CA1's performance of services as set forth in the DESCRIPTION OF SERVICES and monitor CA1's compliance with the MOA.",
                        'Promptly reimburse allowable expenses according to the terms and conditions set forth in this MOA.',
                        'Be responsible for sharing results of security audits, internal audits or APA findings that pertain to the services set forth in this MOA and/or the supporting infrastructure with the CA1.',
                    ]],
                ],
                'addition' => ['placeholder' => 'Additional responsibilities should be included if required.', 'as' => 'numbered'],
            ],
            [
                'numeral' => 'V',
                'heading' => 'CONTRACTING AGENCY RESPONSIBILITIES',
                'section' => 'contracting_agency',
                'new_page' => true,
                'blocks' => [
                    ['paragraph' => 'CONTRACTING AGENCY shall undertake the following activities during the duration of the MOA term:'],
                    ['numbered' => [
                        'Offer training and meetings as necessary to meet the needs of CA2 in order to enhance effectiveness of **:services** provided per this MOA.',
                        'Be responsible for sharing results of security audits, internal audits or APA findings that pertain to the services as set forth in this MOA and/or the supporting infrastructure with the CA2.',
                    ]],
                ],
                'addition' => ['placeholder' => 'Additional responsibilities should be included if required.', 'as' => 'numbered'],
            ],
            [
                'numeral' => 'VI',
                'heading' => 'CONTRACTING AGENCY and CUSTOMER AGENCY RESPONSIBILITIES',
                'section' => 'joint',
                'new_page' => true,
                'blocks' => [
                    ['paragraph' => 'Both agencies shall jointly agree to undertake the following activities during the duration of the MOA term:'],
                    ['numbered' => [
                        'Work cooperatively to resolve any issues that arise during the course of administering this MOA.',
                        'Regularly monitor and evaluate the provision of services set forth by this MOA; and,',
                        'Modify and/or revise the scope of services as mutually agreed to in writing by both parties. Major updates or additional features beyond the agreed scope will not be accepted unless accompanied by a separate written agreement and additional payment.',
                    ]],
                ],
                'addition' => ['placeholder' => 'Additional responsibilities should be included if required.', 'as' => 'numbered'],
            ],
            [
                'numeral' => 'VII',
                'heading' => 'DESCRIPTION OF SERVICES',
                'section' => 'services',
                'new_page' => true,
                'blocks' => [],
                'addition' => [
                    'placeholder' => 'Enter specifics relative to the services covered by this MOA. They can follow this format:',
                    'example' => [
                        'CA1 has acquired an online tool/system to perform specific services that can be used by multiple agencies with the same or similar need. Use of this tool/system is shared between two or more agencies. Data will/will not be shared between the agencies.',
                        'CA1 offers the online tool/system as an accommodation to CA2 that has the express requirement. This tool/system is a shared online service to reduce the burden on customer agencies for duplicating purchases of the same tool/system or competing tools/systems and reduce the overall cost to the Commonwealth of Virginia.',
                    ],
                    'as' => 'services',
                ],
            ],
            [
                'numeral' => 'VIII',
                'heading' => 'COMPLIANCE TO REGULATIONS',
                'section' => 'compliance',
                'new_page' => true,
                'blocks' => [
                    ['paragraph' => 'In accordance with the Data Privacy Act of 2012 (Republic Act No. 10173) and the National Privacy Commission (NPC) Circular No. 16-03 on Personal Data Breach Management, if an employee from either agency, or subcontractor, or agent of either, knows or reasonably suspects that any personal data (personal information or sensitive personal information) obtained has been lost, stolen, or otherwise subject to unauthorized access, the discovering agency shall immediately notify the other through the appropriate Program Manager and their respective Data Protection Officer (DPO).'],
                    ['paragraph' => 'Furthermore, the responsible agency must notify the National Privacy Commission (NPC) and the affected data subjects within seventy-two (72) hours upon knowledge of, or reasonable belief that, a personal data breach requiring notification has occurred. The notification must include the following information:'],
                    ['lettered' => [
                        'Cause(s) of the breach incident',
                        'Date(s) of the breach incident',
                        'Estimated size of the affected population (number of personal records)',
                        'The type of data exposed',
                        'Any mitigating factors',
                    ]],
                    ['paragraph' => 'In the event of a security breach, CA1 and CA2 must comply with all notification actions and data protection protocols as required by Philippine law and NPC regulations. Any costs associated with the breach will be the responsibility of the agency that caused the breach.'],
                ],
                'addition' => ['placeholder' => 'Enter additional regulations that may pertain to this MOA.', 'as' => 'paragraph'],
            ],
            [
                'numeral' => 'IX',
                'heading' => 'TERMS OF AGREEMENT',
                'section' => 'terms',
                'new_page' => true,
                'blocks' => [
                    ['paragraph' => '**Revisions and Modifications:** Minor revisions necessary for project completion are acceptable. Major updates or additional features beyond the agreed scope will not be accepted unless accompanied by a separate written agreement and additional payment.'],
                    ['paragraph' => '**Termination:** Either party may request the termination of this Agreement for a valid reason, subject to proper notification and approval. Work completed up to termination shall be compensated accordingly.'],
                    ['paragraph' => '**Duration (if applicable):** This Agreement takes effect on the specified date and remains valid until the Student/Team has successfully passed Capstone 2, unless terminated earlier by mutual consent.'],
                ],
                'addition' => ['placeholder' => 'Describe the terms and conditions under which this agreement may be modified or terminated by the parties.', 'as' => 'paragraph'],
            ],
            [
                'numeral' => 'X',
                'heading' => 'EFFECTIVE DATE AND SIGNATURE',
                'new_page' => true,
                'blocks' => [
                    ['paragraph' => 'This MOA shall be effective upon the signature of authorized officials of CA1 and CA2. It shall continue in perpetuity with yearly amendments as appropriate unless canceled in its entirety by either party in accordance with the applicable clauses contained herein. The terms will be reviewed annually. The MOA is automatically renewed unless written notice to modify the agreement is given by either party within 60 days of new fiscal year. The MOA can be terminated anytime by either party with written notice 60 days in advance of termination date.'],
                ],
            ],
        ],

        /* Printed on each party's (Title) line under the signature block. */
        'signature_titles' => [
            'client' => 'Client Representative',
            'student' => 'Student Representative',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Memorandum of Agreement (earlier wording)
    |--------------------------------------------------------------------------
    |
    | The school's earlier Memorandum of Agreement, kept only for agreements
    | somebody signed under it (AgreementTemplate::Memorandum) — a signature
    | stays with the words it was given for. The blanks of the paper form are
    | filled from the agreement itself:
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
    | The phases a new agreement starts with: Turnover alone, always the last
    | phase. Every phase before it is a Section VII service either side adds
    | to the memorandum (its Objective and Scope), so Design and Build are no
    | longer seeded. The amount is zero and the dates blank until the client
    | sets them, so nothing here asserts a price or a deadline nobody agreed.
    |
    */

    'default_milestones' => ['Turnover'],

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
