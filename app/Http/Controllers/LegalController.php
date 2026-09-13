<?php

namespace App\Http\Controllers;

use App\Enums\LegalDocument;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The public legal documents.
 *
 * Deliberately open to signed-out visitors and outside the verification gate:
 * somebody has to be able to read what they are agreeing to before they have
 * an account, and the registration form asks them to agree to it.
 *
 * The document is resolved from the URL by the LegalDocument enum, so an
 * unknown slug is a 404 from the router rather than an empty page.
 */
class LegalController extends Controller
{
    /**
     * Show one legal document, with the others listed alongside it.
     */
    public function __invoke(LegalDocument $document): Response
    {
        return Inertia::render('legal', [
            'document' => [
                'slug' => $document->value,
                'title' => $document->title(),
                'intro' => $document->intro(),
                'sections' => $document->sections(),
            ],
            'documents' => LegalDocument::index(),
        ]);
    }
}
