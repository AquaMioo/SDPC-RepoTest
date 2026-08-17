<?php

namespace App\Services\Matching;

use App\Models\Skill;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Turns the words a person actually types into skills the platform knows.
 *
 * A client searching "POS for my store" is describing a problem, not a
 * technology stack. Matching their words directly against skill names finds
 * nothing, because no student lists "POS" as a skill — they list payment
 * integration and MySQL. This is the layer that closes that gap.
 *
 * The map is domain vocabulary, not synonyms: each entry is a kind of system
 * local businesses ask for, pointing at what building one actually takes.
 */
class SkillInference
{
    /**
     * Domain phrases mapped onto the skill slugs building one implies.
     *
     * @var array<string, list<string>>
     */
    protected const DOMAIN_SKILLS = [
        'pos' => ['payment-integration', 'mysql', 'system-analysis', 'php', 'laravel'],
        'point of sale' => ['payment-integration', 'mysql', 'system-analysis', 'php', 'laravel'],
        'inventory' => ['mysql', 'data-analytics', 'forecasting', 'system-analysis'],
        'stock' => ['mysql', 'data-analytics', 'forecasting'],
        'sales' => ['payment-integration', 'data-analytics', 'mysql'],
        'payment' => ['payment-integration', 'api-integration'],
        'billing' => ['payment-integration', 'mysql'],
        'payroll' => ['data-analytics', 'mysql', 'system-analysis'],
        'accounting' => ['data-analytics', 'mysql'],
        'ledger' => ['data-analytics', 'mysql'],
        'booking' => ['api-integration', 'mysql', 'ui-ux-design'],
        'appointment' => ['api-integration', 'mysql', 'ui-ux-design'],
        'reservation' => ['api-integration', 'mysql'],
        'scheduling' => ['system-analysis', 'mysql'],
        'delivery' => ['api-integration', 'mysql', 'system-analysis'],
        'logistics' => ['api-integration', 'data-analytics', 'system-analysis'],
        'dispatch' => ['api-integration', 'system-analysis'],
        'tracking' => ['api-integration', 'mysql'],
        'records' => ['mysql', 'system-analysis'],
        'enrollment' => ['mysql', 'system-analysis'],
        'attendance' => ['mysql', 'system-analysis'],
        'reporting' => ['data-analytics', 'technical-writing'],
        'analytics' => ['data-analytics', 'forecasting'],
        'forecast' => ['forecasting', 'data-analytics'],
        'website' => ['react', 'laravel', 'tailwind-css', 'ui-ux-design'],
        'web app' => ['react', 'laravel', 'tailwind-css'],
        'web based' => ['react', 'laravel', 'tailwind-css'],
        'portal' => ['laravel', 'react', 'mysql'],
        'dashboard' => ['react', 'data-analytics', 'ui-ux-design'],
        'mobile' => ['flutter', 'dart', 'react-native'],
        'android' => ['flutter', 'dart', 'kotlin'],
        'ios' => ['swift', 'flutter'],
        'app' => ['flutter', 'react-native'],
        'chat' => ['api-integration', 'firebase'],
        'messaging' => ['api-integration', 'firebase'],
        'real time' => ['api-integration', 'firebase', 'redis'],
        'real-time' => ['api-integration', 'firebase', 'redis'],
        'sms' => ['api-integration'],
        'notification' => ['api-integration', 'firebase'],
        'ecommerce' => ['payment-integration', 'laravel', 'react'],
        'e-commerce' => ['payment-integration', 'laravel', 'react'],
        'online store' => ['payment-integration', 'laravel', 'react'],
        'ordering' => ['payment-integration', 'mysql', 'ui-ux-design'],
        'menu' => ['ui-ux-design', 'mysql'],
        'map' => ['api-integration'],
        'geolocation' => ['api-integration'],
        'gps' => ['api-integration'],
        'deployment' => ['deployment-devops'],
        'hosting' => ['deployment-devops'],
        'testing' => ['quality-assurance'],
        'documentation' => ['technical-writing'],
        'design' => ['ui-ux-design'],
        'redesign' => ['ui-ux-design'],

        /*
         * Local vocabulary.
         *
         * The entries above are generic software words; these are the kinds of
         * business a San Jose Del Monte client actually runs, in the words
         * they use for them. Without this half, "sari-sari store inventory"
         * only matched on "inventory" and the platform understood the least
         * specific part of the sentence.
         *
         * These mappings are a starting point written from the outside. If one
         * is wrong for how these businesses really work, this is the list to
         * correct — every entry is independent.
         */
        'sari-sari' => ['payment-integration', 'mysql', 'forecasting'],
        'sari sari' => ['payment-integration', 'mysql', 'forecasting'],
        'tindahan' => ['payment-integration', 'mysql', 'forecasting'],
        'utang' => ['mysql', 'data-analytics', 'system-analysis'],
        'lista' => ['mysql', 'system-analysis'],
        'resibo' => ['payment-integration', 'mysql'],
        'receipt' => ['payment-integration', 'mysql'],
        'invoice' => ['payment-integration', 'mysql'],
        'bayad center' => ['payment-integration', 'api-integration', 'mysql'],
        'bayad' => ['payment-integration', 'api-integration'],
        'remittance' => ['payment-integration', 'api-integration', 'mysql'],
        'piso wifi' => ['api-integration', 'mysql', 'deployment-devops'],
        'vendo' => ['api-integration', 'mysql'],
        'boarding house' => ['mysql', 'payment-integration', 'system-analysis'],
        'rental' => ['mysql', 'payment-integration', 'system-analysis'],
        'tenant' => ['mysql', 'system-analysis'],
        'apartment' => ['mysql', 'payment-integration'],
        'poultry' => ['mysql', 'data-analytics', 'forecasting'],
        'farm' => ['mysql', 'data-analytics', 'forecasting'],
        'livestock' => ['mysql', 'data-analytics', 'forecasting'],
        'harvest' => ['data-analytics', 'forecasting'],
        'tricycle' => ['mysql', 'system-analysis'],
        'toda' => ['mysql', 'system-analysis', 'payment-integration'],
        'cooperative' => ['mysql', 'payment-integration', 'data-analytics'],
        'membership' => ['mysql', 'payment-integration'],
        'carinderia' => ['payment-integration', 'mysql', 'ui-ux-design'],
        'eatery' => ['payment-integration', 'mysql', 'ui-ux-design'],
        'canteen' => ['payment-integration', 'mysql', 'ui-ux-design'],
        'laundry' => ['mysql', 'system-analysis'],
        'pharmacy' => ['mysql', 'forecasting', 'data-analytics'],
        'botica' => ['mysql', 'forecasting', 'data-analytics'],
        'clinic' => ['mysql', 'system-analysis', 'api-integration'],
        'water refilling' => ['mysql', 'api-integration', 'system-analysis'],
        'job order' => ['mysql', 'system-analysis'],
        'barangay' => ['mysql', 'system-analysis', 'technical-writing'],
    ];

    /**
     * Infer skill slugs from free text.
     *
     * Matches both the domain vocabulary above and the names of skills the
     * platform already knows, so "we need Laravel and a POS" contributes from
     * both halves of the sentence.
     *
     * @return Collection<int, string>
     */
    public function fromText(string $text): Collection
    {
        $haystack = $this->normalise($text);

        $inferred = collect(self::DOMAIN_SKILLS)
            ->filter(fn (array $skills, string $phrase) => $this->mentions($haystack, $phrase))
            ->flatten()
            ->values();

        return $inferred
            ->merge($this->namedSkills($haystack))
            ->unique()
            ->values();
    }

    /**
     * Get the phrases from the map that this text actually used.
     *
     * Surfaced so the explanation can say which words drove the match rather
     * than presenting a number with no reasoning behind it.
     *
     * @return Collection<int, string>
     */
    public function phrasesIn(string $text): Collection
    {
        $haystack = $this->normalise($text);

        return collect(array_keys(self::DOMAIN_SKILLS))
            ->filter(fn (string $phrase) => $this->mentions($haystack, $phrase))
            ->values();
    }

    /**
     * Match skills the platform stores by name, e.g. "Laravel" or "MySQL".
     *
     * @return Collection<int, string>
     */
    protected function namedSkills(string $haystack): Collection
    {
        return Skill::query()
            ->get(['slug', 'name'])
            ->filter(fn (Skill $skill) => $this->mentions($haystack, $skill->name))
            ->pluck('slug')
            ->values();
    }

    /**
     * Determine if the text uses a phrase as a whole word.
     *
     * Both sides are reduced to a common word form first, so "tracker" reaches
     * the "tracking" entry and "orders" reaches "ordering". Without it the
     * vocabulary would need every inflection of every phrase spelled out, and
     * the one a client happened to type would be the one missing.
     *
     * Still anchored on word boundaries, which is what stops "app" matching
     * "happy" and "map" matching "company".
     */
    protected function mentions(string $haystack, string $needle): bool
    {
        return preg_match(
            '/(?<![\w-])'.preg_quote($this->normalise($needle), '/').'(?![\w-])/u',
            $haystack,
        ) === 1;
    }

    /**
     * Reduce text to the word forms the vocabulary is compared against.
     */
    protected function normalise(string $text): string
    {
        $words = preg_split('/[^\p{L}\p{N}]+/u', Str::lower($text), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        /*
         * No padding. The needle is normalised through here too, and a
         * trailing space would sit inside the pattern where the word-boundary
         * lookahead expects the end of the word — which matched nothing at all.
         */
        return collect($words)->map(fn (string $word) => $this->stem($word))->implode(' ');
    }

    /**
     * Strip the handful of endings that separate one word form from another.
     *
     * Deliberately crude. A real stemmer would earn its keep over a corpus;
     * here the vocabulary is a few dozen short phrases, and the only job is to
     * stop "booking" and "bookings" being treated as different ideas.
     */
    protected function stem(string $word): string
    {
        /*
         * Applied until nothing more comes off, not once. A single pass took
         * "orders" to "ord" (via -ers) but "ordering" only to "order" (via
         * -ing), so the two forms of the same word stopped matching. Running
         * to a fixed point makes the reduction order-independent: both land on
         * "ord". Over-stemming is fine here as long as it is symmetric.
         */
        for ($pass = 0; $pass < 3; $pass++) {
            $before = $word;

            foreach (['ings', 'ing', 'ers', 'er', 's'] as $suffix) {
                if (str_ends_with($word, $suffix) && mb_strlen($word) > mb_strlen($suffix) + 2) {
                    $word = mb_substr($word, 0, -mb_strlen($suffix));

                    break;
                }
            }

            if ($word === $before) {
                break;
            }
        }

        return $word;
    }
}
