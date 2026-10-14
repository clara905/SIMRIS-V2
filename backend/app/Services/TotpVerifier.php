<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Cache;

class TotpVerifier
{
    public function verify(User $user, ?string $code): bool
    {
        $secret = config('security.totp_secrets.'.$user->id);
        if (! is_string($secret) || ! preg_match('/^[A-Z2-7]{16,}$/', $secret) || ! is_string($code) || ! preg_match('/^[0-9]{6}$/', $code)) {
            return false;
        }
        $bits = '';
        foreach (str_split($secret) as $char) {
            $bits .= str_pad(decbin(strpos('ABCDEFGHIJKLMNOPQRSTUVWXYZ234567', $char)), 5, '0', STR_PAD_LEFT);
        }
        $key = '';
        foreach (str_split($bits, 8) as $byte) {
            if (strlen($byte) === 8) {
                $key .= chr(bindec($byte));
            }
        }
        $counter = intdiv(now()->timestamp, 30);
        foreach ([-1, 0, 1] as $offset) {
            $step = $counter + $offset;
            $digest = hash_hmac('sha1', pack('N2', 0, $step), $key, true);
            $index = ord($digest[19]) & 15;
            $number = (unpack('N', substr($digest, $index, 4))[1] & 0x7FFFFFFF) % 1000000;
            if (hash_equals(str_pad((string) $number, 6, '0', STR_PAD_LEFT), $code)) {
                return Cache::add('simris:totp:'.$user->id.':'.$step, true, 90);
            }
        }

        return false;
    }
}
