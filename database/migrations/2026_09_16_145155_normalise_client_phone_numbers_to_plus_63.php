<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Store every business phone number the one way the contacts form now takes
 * it: +63 and the national number, e.g. +639171234567.
 *
 * Earlier rules saved whatever digits were typed — 09171234567, 639171234567,
 * or "+63 917 555 0142" from before that — so the profile showed some numbers
 * with +63 and some without. This rewrites the ones that are recognisably
 * Philippine, using the same steps as UpdateClientProfileRequest, and leaves
 * anything else as it is for its owner to correct on their next save.
 *
 * The steps are written out here rather than borrowed from the request, so a
 * later change to the form cannot change what this migration did.
 */
return new class extends Migration
{
    public function up(): void
    {
        $left = 0;

        DB::table('client_profiles')
            ->whereNotNull('phone_number')
            ->where('phone_number', '!=', '')
            ->orderBy('id')
            ->select(['id', 'phone_number'])
            ->each(function (object $profile) use (&$left): void {
                $normalised = $this->normalise($profile->phone_number);

                if ($normalised === null) {
                    $left++;

                    return;
                }

                if ($normalised !== $profile->phone_number) {
                    DB::table('client_profiles')
                        ->where('id', $profile->id)
                        ->update(['phone_number' => $normalised]);
                }
            });

        if ($left > 0) {
            Log::info('Business phone numbers that are not Philippine numbers were left as they are.', [
                'profiles' => $left,
            ]);
        }
    }

    /**
     * Nothing to put back: the old values were the same numbers, written
     * differently.
     */
    public function down(): void
    {
        //
    }

    private function normalise(string $raw): ?string
    {
        if (preg_match('/^[\d\s().+-]+$/', $raw) !== 1) {
            return null;
        }

        $digits = preg_replace('/\D/', '', $raw);

        if (str_starts_with($digits, '63') && strlen($digits) > 10) {
            $digits = substr($digits, 2);
        }

        if (str_starts_with($digits, '0')) {
            $digits = substr($digits, 1);
        }

        $number = '+63'.$digits;

        return preg_match('/^\+63(9\d{9}|[2-8]\d{8})$/', $number) === 1 ? $number : null;
    }
};
