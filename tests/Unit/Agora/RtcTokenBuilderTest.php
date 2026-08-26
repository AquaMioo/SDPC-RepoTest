<?php

namespace Tests\Unit\Agora;

use App\Services\Agora\RtcTokenBuilder;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/**
 * Agora rejects a malformed token without saying why, so these decode the
 * token back and assert the byte layout rather than trusting that it looks
 * plausible. Between them they pin every field the reference implementation
 * writes, and re-derive the signature so the two-step HMAC chain cannot
 * silently drift.
 */
class RtcTokenBuilderTest extends TestCase
{
    private const APP_ID = '0123456789abcdef0123456789abcdef';

    private const APP_CERT = 'fedcba9876543210fedcba9876543210';

    private const ISSUED_AT = 1_780_000_000;

    private const SALT = 12_345_678;

    private const TTL = 3600;

    public function test_the_token_decodes_to_the_documented_layout(): void
    {
        $token = $this->builder()->build('meeting-42', 7, self::TTL, self::ISSUED_AT, self::SALT);

        $this->assertStringStartsWith('007', $token);

        $payload = gzuncompress(base64_decode(substr($token, 3)));
        $this->assertNotFalse($payload, 'The token body must be zlib-compressed.');

        $cursor = 0;
        $signature = $this->readString($payload, $cursor);
        $this->assertSame(32, strlen($signature), 'The signature is a raw SHA-256 digest.');

        /* Everything after the signature is the signed material, in order. */
        $signingInfo = substr($payload, $cursor);

        $this->assertSame(self::APP_ID, $this->readString($payload, $cursor));
        $this->assertSame(self::ISSUED_AT, $this->readUint32($payload, $cursor));
        $this->assertSame(self::TTL, $this->readUint32($payload, $cursor));
        $this->assertSame(self::SALT, $this->readUint32($payload, $cursor));
        $this->assertSame(1, $this->readUint16($payload, $cursor), 'Exactly one service is issued.');

        $this->assertSame(1, $this->readUint16($payload, $cursor), 'Service type 1 is RTC.');

        /* Join, publish audio, publish video, publish data — each at the TTL. */
        $this->assertSame(4, $this->readUint16($payload, $cursor));

        foreach ([1, 2, 3, 4] as $expectedPrivilege) {
            $this->assertSame($expectedPrivilege, $this->readUint16($payload, $cursor));
            $this->assertSame(self::TTL, $this->readUint32($payload, $cursor));
        }

        $this->assertSame('meeting-42', $this->readString($payload, $cursor));
        $this->assertSame('7', $this->readString($payload, $cursor));
        $this->assertSame(strlen($payload), $cursor, 'Nothing unread should be left over.');

        /* Re-derive the signature, which is what actually proves the chain. */
        $key = hash_hmac('sha256', self::APP_CERT, pack('V', self::ISSUED_AT), true);
        $key = hash_hmac('sha256', $key, pack('V', self::SALT), true);

        $this->assertSame(hash_hmac('sha256', $signingInfo, $key, true), $signature);
    }

    public function test_an_unpinned_uid_is_written_as_an_empty_string(): void
    {
        $token = $this->builder()->build('meeting-42', 0, self::TTL, self::ISSUED_AT, self::SALT);

        $payload = gzuncompress(base64_decode(substr($token, 3)));

        /*
         * Zero means "let Agora assign one" and must be the empty string, not
         * the digit. Written as "0" the SDK joins as user 0 and every guest
         * collides on the same id.
         */
        $this->assertStringEndsWith(pack('v', 0), $payload);
    }

    public function test_the_same_inputs_produce_the_same_token(): void
    {
        $first = $this->builder()->build('meeting-42', 7, self::TTL, self::ISSUED_AT, self::SALT);
        $second = $this->builder()->build('meeting-42', 7, self::TTL, self::ISSUED_AT, self::SALT);

        $this->assertSame($first, $second);
    }

    public function test_a_different_channel_produces_a_different_signature(): void
    {
        $mine = $this->builder()->build('meeting-42', 7, self::TTL, self::ISSUED_AT, self::SALT);
        $theirs = $this->builder()->build('meeting-43', 7, self::TTL, self::ISSUED_AT, self::SALT);

        $this->assertNotSame($mine, $theirs, 'A token must not travel between channels.');
    }

    public function test_it_refuses_credentials_that_cannot_be_agoras(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new RtcTokenBuilder('too-short', self::APP_CERT);
    }

    public function test_it_refuses_an_empty_channel(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->builder()->build('', 7, self::TTL);
    }

    private function builder(): RtcTokenBuilder
    {
        return new RtcTokenBuilder(self::APP_ID, self::APP_CERT);
    }

    private function readUint16(string $payload, int &$cursor): int
    {
        $value = unpack('v', substr($payload, $cursor, 2))[1];
        $cursor += 2;

        return $value;
    }

    private function readUint32(string $payload, int &$cursor): int
    {
        $value = unpack('V', substr($payload, $cursor, 4))[1];
        $cursor += 4;

        return $value;
    }

    private function readString(string $payload, int &$cursor): string
    {
        $length = $this->readUint16($payload, $cursor);
        $value = substr($payload, $cursor, $length);
        $cursor += $length;

        return $value;
    }
}
