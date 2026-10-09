/**
 * The shape App\Actions\Agreements\PresentAgreement builds.
 *
 * The Agreement screen and the Contract screen are the same document at two
 * distances, so they read one type rather than two that could drift apart.
 */
export type AgreementMilestone = {
    id: number;
    position: number;
    title: string;
    description: string | null;
    /** Whole pesos. */
    amount: number;
    startsOn: string | null;
    endsOn: string | null;
    status: string;
    statusLabel: string;
    statusVariant: string;
    progress: number;
    reviewNote: string | null;
};

export type AgreementSignature = {
    party: string;
    partyLabel: string;
    signedName: string;
    /** The account id the screen promises to record. */
    accountId: number;
    signedAt: string;
};

/** The Memorandum of Agreement with its blanks filled from the agreement. */
export type Memorandum = {
    title: string;
    parties: { client: string; developer: string };
    purpose: string;
    commitments: string[];
    /** `body` may carry **bold** runs, as the paper form prints them. */
    sections: { heading: string; body: string | null; items: string[] }[];
    closing: string;
    /** "Signed this 16th day of …" once both parties have signed. */
    signedOn: string | null;
};

/** One line a party added to a section of the SDPC memorandum. */
export type MoaEntry = {
    id: number;
    /** Section VII only: the service's Objective. */
    title: string | null;
    /** The line itself, or Section VII's Scope. */
    body: string;
    authorName: string | null;
    authorSide: 'client' | 'student' | null;
    /** Only the author may change or remove it, until somebody signs. */
    canChange: boolean;
};

/** One numbered part of a section, with its own a., b., ... list when it has one. */
export type MoaNumberedItem = { text: string; lettered: string[] };

/**
 * A run of the school's base wording: a lead-in paragraph, or a numbered
 * list (every part of every section is numbered, as the template does).
 */
export type MoaBlock =
    | { type: 'paragraph'; text: string }
    | { type: 'lettered'; items: string[] }
    | { type: 'numbered'; items: MoaNumberedItem[] };

export type MoaSectionKey =
    | 'customer_agency'
    | 'contracting_agency'
    | 'joint'
    | 'services'
    | 'compliance'
    | 'terms';

export type MoaSection = {
    /** The sections either side may add to; null for the fixed ones. */
    key: MoaSectionKey | null;
    numeral: string;
    heading: string;
    /** Starts a fresh page on paper, as the template does. */
    newPage: boolean;
    /** `text`/`items` may carry **bold** runs: the filled-in blanks. */
    blocks: MoaBlock[];
    addition: {
        /** How an addition prints: the next number, its own paragraph, or a service. */
        as: 'numbered' | 'paragraph' | 'services';
        /** The template's "add more here" line: a prompt, never printed. */
        placeholder: string;
        example: string[];
        isRequired: boolean;
    } | null;
    entries: MoaEntry[];
};

/** The SDPC Memorandum of Agreement, filled in, as PresentAgreement builds it. */
export type Moa = {
    title: string;
    footer: string;
    /** CA1: the client's company name, or its representative. */
    ca1: string;
    /** CA2: the student representative. */
    ca2: string;
    ca1Representative: string;
    ca2Representative: string;
    /** The Description of Services: the capstone/project title. */
    services: string;
    sections: MoaSection[];
    signatories: Record<
        'client' | 'student',
        { printName: string; title: string; signedOn: string | null }
    >;
};

export type Agreement = {
    id: number;
    reference: string;
    version: number;
    status: string;
    statusLabel: string;
    statusVariant: string;

    project: {
        slug: string;
        title: string;
        category: string;
        repositoryUrl: string | null;
    };

    client: {
        name: string;
        signatoryName: string | null;
    };
    student: {
        id: number;
        name: string;
        signatoryName: string | null;
    };

    scopeSummary: string | null;
    deliverables: string[];
    /**
     * The wording the contract is in. New agreements are the SDPC Memorandum
     * of Agreement (`moa`); ones somebody signed earlier keep the earlier
     * memorandum or the clauses (`terms`).
     */
    template: 'sdpc_moa' | 'memorandum' | 'clauses';
    moa: Moa | null;
    memorandum: Memorandum | null;
    terms: {
        intellectualProperty: string | null;
        confidentiality: string | null;
        academic: string | null;
    };

    startsOn: string | null;
    endsOn: string | null;
    totalAmount: number;
    progress: number;

    milestones: AgreementMilestone[];
    signatures: AgreementSignature[];
    acknowledgements: { key: string; label: string }[];

    /** What this particular reader may do next. Both sides see every term. */
    viewer: {
        party: string | null;
        partyLabel: string | null;
        hasSigned: boolean;
        canEdit: boolean;
        canSign: boolean;
        canRequestChanges: boolean;
        /** Either party, until somebody signs: the memorandum's "Add requirement". */
        canAddRequirements: boolean;
        /** What still stops anyone signing, e.g. an empty Section VII. */
        signingBlockedBy: string | null;
    };
};

export type AgreementListItem = {
    id: number;
    reference: string;
    version: number;
    status: string;
    statusLabel: string;
    statusVariant: string;
    projectTitle: string;
    counterparty: string;
    totalAmount: number;
};

/** One Section II service on the addendum: an Objective and its Scope. */
export type AddendumEntry = {
    id: number;
    objective: string;
    scope: string;
    authorName: string | null;
    authorSide: 'client' | 'student' | null;
    /** Only its author may change it, and only until somebody signs. */
    canChange: boolean;
};

export type AddendumSection = {
    numeral: string;
    heading: string;
    /** 'services' for Section II, 'payments' for Section IV. */
    key: 'services' | 'payments' | null;
    newPage: boolean;
    blocks: (
        | { type: 'paragraph'; text: string }
        | { type: 'numbered'; items: string[] }
    )[];
    /** Section IV's table: each milestone, then the total. */
    milestones: {
        name: string;
        description: string;
        allocation: string;
        /** "₱3,000.00", or null while no target amount is set. */
        amount: string | null;
        condition: string;
    }[];
};

export type AddendumSignatory = {
    party: string;
    title: string;
    printName: string;
    signedOn: string | null;
};

export type AddendumPayment = {
    id: number;
    milestone: 1 | 2;
    label: string;
    percentage: number;
    /** Centavos. */
    amount: number;
    amountLabel: string;
    status: 'pending' | 'paid';
    statusLabel: string;
    invoiceNumber: string;
    /** The gateway's payment id (pay_…), once paid. */
    providerPaymentId: string | null;
    gateway: string | null;
    method: string | null;
    paidBy: string | null;
    paidAt: string | null;
    isPayable: boolean;
};

/** The Payment & Project Extension Addendum (PresentAddendum). */
export type Addendum = {
    id: number;
    reference: string;
    status:
        'draft' | 'awaiting_signatures' | 'active' | 'completed' | 'cancelled';
    statusLabel: string;
    statusVariant: string;
    agreementId: number;
    agreementReference: string;
    projectTitle: string;
    requestedOn: string | null;
    executedOn: string | null;
    completedOn: string | null;
    /** Section IV's target amount, whole pesos. */
    totalAmount: number | null;
    limits: {
        min: number;
        max: number;
        downPaymentPercent: number;
        threshold: number;
    };
    document: {
        title: string;
        footer: string;
        /** The cover's labels under each filled-in name. */
        cover: { ca1: string; ca2: string; services: string };
        /** CA1, the Student Team Lead. */
        ca1: string;
        /** CA2, the Client Representative. */
        ca2: string;
        /** The project title. */
        services: string;
        sections: AddendumSection[];
        /** Section II's format, shown on screen while it is empty. */
        example: string[];
        entries: AddendumEntry[];
        /** Always masked: 09******297, or null when none is on record. */
        gcash: { student: string | null; client: string | null };
        signatories: { student: AddendumSignatory; client: AddendumSignatory };
    };
    signatures: {
        party: 'client' | 'student';
        partyLabel: string;
        signedName: string;
        signedAt: string;
    }[];
    payments: AddendumPayment[];
    work: { taskCount: number; handedInCount: number; isHandedIn: boolean };
    /** 'paymongo', or 'simulated' while no PayMongo key is set. */
    gateway: string;
    viewer: {
        party: 'client' | 'student' | null;
        canEdit: boolean;
        canSign: boolean;
        hasSigned: boolean;
        canCancel: boolean;
        canPay: boolean;
        signingBlockedBy: string | null;
        /** This viewer's own side still has no GCash account in Settings. */
        needsGcash: boolean;
        clientRepresentativeName: string;
        isClientRepresentative: boolean;
    };
    isOpen: boolean;
    isCancelled: boolean;
};
