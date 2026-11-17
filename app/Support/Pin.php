<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\Hash;

class Pin
{
    public static function matches(User $user, string $pin): bool
    {
        $stored = (string) $user->pin;

        if (self::isHashed($stored)) {
            return Hash::check($pin, $stored);
        }

        return strlen($stored) === 4 && ctype_digit($stored) && hash_equals($stored, $pin);
    }

    public static function hash(string $pin): string
    {
        return Hash::make($pin);
    }

    public static function isHashed(string $value): bool
    {
        return (password_get_info($value)['algoName'] ?? 'unknown') !== 'unknown';
    }
}
