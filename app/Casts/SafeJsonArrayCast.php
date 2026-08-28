<?php

namespace App\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;

/**
 * Like the built-in 'array' cast, but defends against a value that's been
 * accidentally JSON-encoded more than once (e.g. system_settings.disabled_pages
 * was found triple-encoded — see the 2026_08_27 repair migration — which
 * makes the plain 'array' cast return a string instead of an array, and
 * that string then corrupts frontend code that assumes a real array, e.g.
 * spreading a string into individual characters). get() keeps decoding
 * while the result is still a JSON string; set() re-decodes an
 * already-encoded string first rather than encoding on top of it, so this
 * can't happen again regardless of what a caller passes in.
 *
 * @implements CastsAttributes<array, array>
 */
class SafeJsonArrayCast implements CastsAttributes
{
    public function get($model, string $key, $value, array $attributes): array
    {
        return $this->normalize($value);
    }

    public function set($model, string $key, $value, array $attributes): string
    {
        return json_encode($this->normalize($value));
    }

    private function normalize(mixed $value): array
    {
        if ($value === null) {
            return [];
        }

        $decoded = $value;
        $guard = 0;

        while (is_string($decoded) && $guard < 5) {
            $next = json_decode($decoded, true);

            if ($next === null && json_last_error() !== JSON_ERROR_NONE) {
                break;
            }

            $decoded = $next;
            $guard++;
        }

        return is_array($decoded) ? $decoded : [];
    }
}
