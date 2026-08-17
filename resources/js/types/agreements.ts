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
