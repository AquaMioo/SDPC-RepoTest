<?php

namespace Tests\Feature;

use App\Enums\LegalDocument;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * The four legal documents, and the links that reach them.
 *
 * RefreshDatabase because one of these requests the landing page, which counts
 * rows — see the rule in .ai/rules/feature.md.
 */
class LegalPagesTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return list<array{0: LegalDocument}>
     */
    public static function documents(): array
    {
        return array_map(
            fn (LegalDocument $document): array => [$document],
            LegalDocument::cases(),
        );
    }

    #[DataProvider('documents')]
    public function test_a_signed_out_visitor_can_read_every_document(LegalDocument $document): void
    {
        /*
         * Signed out on purpose: the registration form asks people to agree to
         * these before they have an account, so they cannot sit behind auth.
         */
        $this->get(route('legal', ['document' => $document->value]))
            ->assertOk()
            ->assertInertia(
                fn (AssertableInertia $page) => $page
                    ->component('legal')
                    ->where('document.title', $document->title())
                    ->where('document.slug', $document->value)
                    ->has('document.sections')
                    ->has('documents', count(LegalDocument::cases()))
            );
    }

    public function test_every_document_carries_its_clauses(): void
    {
        // A document that renders with an empty body would still pass a status
        // check, and an empty legal page is worse than a missing one.
        foreach (LegalDocument::cases() as $document) {
            $this->assertNotSame('', trim($document->intro()), $document->value);
            $this->assertNotEmpty($document->sections(), $document->value);

            foreach ($document->sections() as $section) {
                $this->assertNotSame('', trim($section['heading']));
                $this->assertNotSame('', trim($section['body']));
            }
        }
    }

    public function test_an_unknown_document_is_not_found(): void
    {
        // Resolved through the enum, so the router refuses it rather than the
        // page rendering blank.
        $this->get('/legal/cookie-policy')->assertNotFound();
    }

    public function test_a_signed_in_person_can_still_read_them(): void
    {
        // They are outside the verification gate as well as outside auth.
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('legal', ['document' => LegalDocument::PrivacyPolicy->value]))
            ->assertOk();
    }

    public function test_every_slug_the_frontend_links_to_is_a_real_document(): void
    {
        /*
         * The footer and the registration consent build their URLs with
         * legal.url('some-slug'), which no PHP test can follow. A typo there
         * would 404 the visitor at the exact moment they tried to read what
         * they were agreeing to, and nothing else would notice.
         *
         * So the slugs are read back out of the two files that hold them and
         * checked against the enum. Server rendering cannot help here: the
         * pages are React, and the links exist only after the bundle runs.
         */
        $slugs = [];

        foreach (['resources/js/pages/welcome.tsx', 'resources/js/pages/auth/register.tsx'] as $file) {
            preg_match_all(
                "/legal\.url\(\s*'([a-z-]+)'/",
                (string) file_get_contents(base_path($file)),
                $matches,
            );

            $slugs = [...$slugs, ...$matches[1]];
        }

        $this->assertNotEmpty($slugs, 'No legal links were found in the pages that should carry them.');

        foreach ($slugs as $slug) {
            $this->assertNotNull(
                LegalDocument::tryFrom($slug),
                "The frontend links to /legal/{$slug}, which is not a document.",
            );
        }
    }

    public function test_no_team_can_take_the_legal_slug(): void
    {
        // Client routes mount on a bare {current_team} prefix, so a team named
        // "Legal" would otherwise shadow these pages.
        $this->assertContains('legal', Team::RESERVED_SLUGS);
    }
}
