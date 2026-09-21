<?php

namespace App\Support;

use Illuminate\Validation\Rules\Password;

/**
 * Laravel 13 added Password::toPasswordRulesString() (the browser
 * `passwordrules` attribute format, https://developer.apple.com/password-rules/)
 * but it doesn't exist on Laravel 12 — this reimplements it from
 * appliedRules(), which both versions have. Ported verbatim from
 * laravel/framework's Illuminate\Validation\Rules\Password::toPasswordRulesString().
 * Drop this once the app is back on a Laravel version that has it natively.
 */
class PasswordRules
{
    public static function toPasswordRulesString(Password $password): string
    {
        $applied = $password->appliedRules();

        $rules = ['minlength: '.$applied['min']];

        if ($applied['max']) {
            $rules[] = 'maxlength: '.$applied['max'];
        }

        if ($applied['mixedCase']) {
            $rules[] = 'required: lower';
            $rules[] = 'required: upper';
        } elseif ($applied['letters']) {
            $rules[] = 'required: lower';
        }

        if ($applied['numbers']) {
            $rules[] = 'required: digit';
        }

        if ($applied['symbols']) {
            $rules[] = 'required: special';
        }

        return implode('; ', $rules).';';
    }
}
