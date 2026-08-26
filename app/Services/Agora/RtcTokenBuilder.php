<?php

namespace App\Services\Agora;

use InvalidArgumentException;

/**
 * Builds an Agora AccessToken2 ("007") for joining an RTC channel.
 *
 * Hand-written rather than pulled from a package, the same call this project
 * makes for SheerID and for Brevo: the format is small, stable and published,
 * and one less dependency is one less thing to install cleanly during a
 * Railway build.
 *
 * The layout, little-endian throughout:
 *
 *   "007" + base64( zlib( string(signature) + signingInfo ) )
 *
 *   signingInfo = string(appId) + u32(issuedAt) + u32(ttl) + u32(salt)
 *                 + u16(serviceCount) + service…
 *   service     = u16(type=1) + map<u16,u32>(privileges)
 *                 + string(channel) + string(uid)
 *
 * The signing key is derived by chaining two HMACs over the certificate —
 * first keyed by the issue timestamp, then by the salt — and the signature is
 * an HMAC of signingInfo under that derived key. Getting the argument order
 * backwards is the classic way to produce a token Agora rejects with no
 * explanation, so note that PHP's hash_hmac() takes (data, key) while the
 * reference implementations take (key, data).
 *
 * A token is scoped to one channel and one uid. Whoever mints one must have
 * already checked that this user belongs in that meeting — Agora has no idea
 * who is allowed where.
 */
class RtcTokenBuilder
{
    /** The only service type this application issues. */
    private const SERVICE_RTC = 1;

    /** Privileges, as numbered by Agora. */
    private const PRIVILEGE_JOIN_CHANNEL = 1;

    private const PRIVILEGE_PUBLISH_AUDIO = 2;

    private const PRIVILEGE_PUBLISH_VIDEO = 3;

    private const PRIVILEGE_PUBLISH_DATA = 4;

    private const VERSION = '007';

    public function __construct(
        private readonly string $appId,
        private readonly string $appCertificate,
    ) {
        /*
         * Both are fixed-length hex strings from the Agora console. Checking
         * here turns a silently rejected token into a clear failure at the
         * point the configuration is wrong.
         */
        if (strlen($this->appId) !== 32 || strlen($this->appCertificate) !== 32) {
            throw new InvalidArgumentException(
                'The Agora app id and certificate must each be 32 characters; check config/agora.php.',
            );
        }
    }

    /**
     * Mint a token letting one user publish and subscribe on one channel.
     *
     * `$uid` of 0 means the token is not pinned to a numeric user id, which is
     * what the web SDK uses when it lets Agora assign one.
     *
     * `$issuedAt` and `$salt` are injectable only so a test can pin the exact
     * bytes; leave them alone in application code.
     */
    public function build(
        string $channel,
        int $uid,
        int $ttlSeconds,
        ?int $issuedAt = null,
        ?int $salt = null,
    ): string {
        if ($channel === '') {
            throw new InvalidArgumentException('An Agora channel name cannot be empty.');
        }

        $issuedAt ??= time();
        $salt ??= random_int(1, 99_999_999);

        /* Every privilege expires with the token itself. */
        $privileges = [
            self::PRIVILEGE_JOIN_CHANNEL => $ttlSeconds,
            self::PRIVILEGE_PUBLISH_AUDIO => $ttlSeconds,
            self::PRIVILEGE_PUBLISH_VIDEO => $ttlSeconds,
            self::PRIVILEGE_PUBLISH_DATA => $ttlSeconds,
        ];

        $service = $this->packUint16(self::SERVICE_RTC)
            .$this->packPrivileges($privileges)
            .$this->packString($channel)
            /* An unpinned uid is the empty string, not the digit zero. */
            .$this->packString($uid === 0 ? '' : (string) $uid);

        $signingInfo = $this->packString($this->appId)
            .$this->packUint32($issuedAt)
            .$this->packUint32($ttlSeconds)
            .$this->packUint32($salt)
            .$this->packUint16(1)
            .$service;

        $signature = $this->sign($signingInfo, $issuedAt, $salt);

        return self::VERSION.base64_encode(
            gzcompress($this->packString($signature).$signingInfo),
        );
    }

    /**
     * Derive the signing key and sign the payload with it.
     */
    private function sign(string $signingInfo, int $issuedAt, int $salt): string
    {
        // hash_hmac() is (data, key); the reference code is (key, data).
        $key = hash_hmac('sha256', $this->appCertificate, $this->packUint32($issuedAt), true);
        $key = hash_hmac('sha256', $key, $this->packUint32($salt), true);

        return hash_hmac('sha256', $signingInfo, $key, true);
    }

    /**
     * @param  array<int, int>  $privileges
     */
    private function packPrivileges(array $privileges): string
    {
        /* Sorted by key: the reference implementation signs them in order. */
        ksort($privileges);

        $packed = $this->packUint16(count($privileges));

        foreach ($privileges as $privilege => $expiresAfter) {
            $packed .= $this->packUint16($privilege).$this->packUint32($expiresAfter);
        }

        return $packed;
    }

    private function packString(string $value): string
    {
        return $this->packUint16(strlen($value)).$value;
    }

    private function packUint16(int $value): string
    {
        return pack('v', $value);
    }

    private function packUint32(int $value): string
    {
        return pack('V', $value);
    }
}
