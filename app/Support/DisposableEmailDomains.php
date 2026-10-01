<?php

namespace App\Support;

/**
 * The known temporary / disposable email domains.
 *
 * The list is the community-maintained blocklist from
 * github.com/disposable-email-domains/disposable-email-domains (CC0), kept in
 * the repository at resources/data/disposable-email-domains.txt, one domain per
 * line. Refresh it with `php artisan email:update-disposable-domains`. Domains
 * listed in resources/data/disposable-email-domains.local.txt are added on top,
 * for any the upstream list has not caught yet.
 *
 * A subdomain of a listed domain counts too: "x.mailinator.com" is refused
 * because "mailinator.com" is listed.
 */
class DisposableEmailDomains
{
    public const LIST_PATH = 'data/disposable-email-domains.txt';

    public const LOCAL_LIST_PATH = 'data/disposable-email-domains.local.txt';

    /** @var array<string, true>|null */
    protected ?array $domains = null;

    /**
     * Whether the address belongs to a disposable email service.
     *
     * The address is normalised first — trimmed, and its domain lowercased —
     * so " Someone@MAILINATOR.COM " is caught like the plain spelling.
     */
    public function isDisposable(string $email): bool
    {
        $domain = self::domainOf($email);

        if ($domain === null) {
            return false;
        }

        $domains = $this->domains();
        $labels = explode('.', $domain);

        /* The domain itself, then each parent: a.b.mailinator.com → b.mailinator.com → mailinator.com. */
        while (count($labels) >= 2) {
            if (isset($domains[implode('.', $labels)])) {
                return true;
            }

            array_shift($labels);
        }

        return false;
    }

    /**
     * The lowercased domain of an address, or null when there is none.
     */
    public static function domainOf(string $email): ?string
    {
        $email = trim($email);
        $at = strrpos($email, '@');

        if ($at === false) {
            return null;
        }

        $domain = rtrim(mb_strtolower(substr($email, $at + 1)), '.');

        return $domain === '' ? null : $domain;
    }

    /**
     * @return array<string, true>
     */
    protected function domains(): array
    {
        return $this->domains ??= collect([self::LIST_PATH, self::LOCAL_LIST_PATH])
            ->map(fn (string $path): string => resource_path($path))
            ->filter(fn (string $path): bool => is_file($path))
            ->flatMap(fn (string $path): array => file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [])
            ->map(fn (string $line): string => mb_strtolower(trim($line)))
            ->reject(fn (string $line): bool => $line === '' || str_starts_with($line, '#'))
            ->mapWithKeys(fn (string $domain): array => [$domain => true])
            ->all();
    }
}
