<?php

namespace App\Enums;

/**
 * The four legal documents a visitor can read, and their text.
 *
 * The copy lives here rather than in the database because it is not something
 * an administrator edits from the console: changing what people agreed to is a
 * deliberate act that should arrive as a commit with a date on it. SiteContent
 * is the editable half of the platform's writing; this is the fixed half.
 *
 * Section headings are stored without their numbers. The page numbers them as
 * it renders, so inserting a clause in the middle does not mean renumbering
 * every one below it by hand and getting one wrong.
 *
 * The drafts spelled the platform "SDPCC". That is a typo for SDPC — what the
 * wordmark, every screen and the agreement references all say — and it was
 * normalised here on the team's instruction rather than left to confuse
 * somebody reading what they had agreed to.
 *
 * ONE PLACEHOLDER REMAINS, carried over verbatim and deliberately not invented
 * away: the contact address reads "(sdpc@email)" and must be a real, monitored
 * mailbox before this is shown to anybody outside the team.
 */
enum LegalDocument: string
{
    case TermsOfService = 'terms-of-service';
    case UserAgreement = 'user-agreement';
    case TermsOfUse = 'terms-of-use';
    case PrivacyPolicy = 'privacy-policy';

    /**
     * The document's title, as it heads the page and appears in the footer.
     */
    public function title(): string
    {
        return match ($this) {
            self::TermsOfService => 'Terms of Service',
            self::UserAgreement => 'User Agreement',
            self::TermsOfUse => 'Terms of Use',
            self::PrivacyPolicy => 'Privacy Policy',
        };
    }

    /**
     * The sentence that opens the document, before the numbered clauses.
     */
    public function intro(): string
    {
        return match ($this) {
            self::TermsOfService => 'These Terms of Service explain the conditions for using SDPC services. By accessing SDPC, you agree to follow these terms.',
            self::UserAgreement => 'This agreement describes the shared expectations that help SDPC remain useful, respectful, and dependable for everyone.',
            self::TermsOfUse => 'These Terms of Use explain how members may use the SDPC marketplace and participate in projects.',
            self::PrivacyPolicy => 'This Privacy Policy explains what information SDPC handles, why we handle it, and the choices available to you.',
        };
    }

    /**
     * The numbered clauses, in order.
     *
     * @return list<array{heading: string, body: string}>
     */
    public function sections(): array
    {
        return match ($this) {
            self::TermsOfService => [
                [
                    'heading' => 'Using SDPC',
                    'body' => 'You must provide accurate information, keep your account details secure, and use SDPC only for lawful purposes. You are responsible for activity that occurs through your account.',
                ],
                [
                    'heading' => 'Your content',
                    'body' => 'You retain ownership of content you submit. You give SDPC permission to host, display, and process that content only as needed to operate, improve, and secure the service.',
                ],
                [
                    'heading' => 'Acceptable use',
                    'body' => 'Do not misuse the service, interfere with its operation, access another person’s account, distribute harmful material, or attempt to bypass security controls.',
                ],
                [
                    'heading' => 'Service changes',
                    'body' => 'We may update, suspend, or discontinue parts of the service. When practical, we will provide notice of material changes that affect your use.',
                ],
                [
                    'heading' => 'Ending access',
                    'body' => 'You may stop using SDPC at any time. We may suspend or terminate access when these terms are violated or when necessary to protect the service and its community.',
                ],
                [
                    'heading' => 'Questions',
                    'body' => 'For questions about these terms, contact us at (sdpc@email)',
                ],
            ],
            self::UserAgreement => [
                [
                    'heading' => 'Show respect',
                    'body' => 'Communicate honestly and treat other members with dignity. Harassment, threats, discrimination, impersonation, and targeted abuse are not welcome.',
                ],
                [
                    'heading' => 'Keep it constructive',
                    'body' => 'Share information that is relevant and useful. Do not intentionally mislead others, manipulate engagement, or repeatedly disrupt conversations.',
                ],
                [
                    'heading' => 'Protect the community',
                    'body' => 'Respect privacy and confidential information. Do not publish someone’s personal details or use SDPC to facilitate unsafe or illegal activity.',
                ],
                [
                    'heading' => 'Speak up',
                    'body' => 'If you see conduct that conflicts with this agreement, report it through our support channel. Include enough context for us to understand what happened.',
                ],
                [
                    'heading' => 'Our response',
                    'body' => 'We review reports fairly and may warn, restrict, or remove accounts when needed. Our response depends on the circumstances, severity, and history of the conduct.',
                ],
                [
                    'heading' => 'Your responsibility',
                    'body' => 'You are responsible for understanding and following this agreement whenever you participate in SDPC.',
                ],
            ],
            self::TermsOfUse => [
                [
                    'heading' => 'Marketplace access',
                    'body' => 'SDPC provides a place for clients and independent professionals to discover one another. You are responsible for deciding whether a project or working relationship is right for you.',
                ],
                [
                    'heading' => 'Honest profiles and listings',
                    'body' => 'Keep your profile, proposals, job listings, work history, and payment details accurate. Do not impersonate another person or misrepresent your experience.',
                ],
                [
                    'heading' => 'Working together',
                    'body' => 'Discuss scope, deadlines, deliverables, and payment clearly before starting work. Keep project communications and agreements in the appropriate SDPC channels when possible.',
                ],
                [
                    'heading' => 'Prohibited activity',
                    'body' => 'Do not use SDPC for fraud, spam, harassment, unlawful activity, unauthorized access, or attempts to move other members into unsafe transactions.',
                ],
                [
                    'heading' => 'Enforcement',
                    'body' => 'We may review activity, limit features, remove content, or suspend accounts when necessary to protect the marketplace and its members.',
                ],
                [
                    'heading' => 'Questions',
                    'body' => 'For questions about these terms, contact us at (sdpc@email)',
                ],
            ],
            self::PrivacyPolicy => [
                [
                    'heading' => 'Information we collect',
                    'body' => 'We may collect information you provide, such as your name, email address, active projects.',
                ],
                [
                    'heading' => 'How we use it',
                    'body' => 'We use information to provide and secure SDPC, communicate with you, understand service performance, prevent abuse, and meet legal obligations.',
                ],
                [
                    'heading' => 'When we share it',
                    'body' => 'We share information with service providers who help us operate SDPC, when you direct us to, or when required to comply with law or protect rights and safety.',
                ],
                [
                    'heading' => 'Retention and security',
                    'body' => 'We keep information only as long as reasonably necessary for the purposes described here. We use administrative, technical, and organizational safeguards, but no system is completely secure.',
                ],
                [
                    'heading' => 'Contact privacy',
                    'body' => 'Questions or requests can be sent to (sdpc@email). This policy may be updated from time to time, with the revised date shown above.',
                ],
            ],
        };
    }

    /**
     * Every document, shaped for the page's own index and the site footer.
     *
     * @return list<array{slug: string, title: string}>
     */
    public static function index(): array
    {
        return array_map(
            fn (self $document): array => [
                'slug' => $document->value,
                'title' => $document->title(),
            ],
            self::cases(),
        );
    }
}
