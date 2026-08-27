<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Throwable;

class GoogleMapsLinkParser
{
    /**
     * Best-effort extraction of a lat/lng pair from whatever the user
     * pasted — a full Google Maps URL, a shortened maps.app.goo.gl link, or
     * just plain "lat,lng" coordinates. Returns null (rather than
     * throwing) when nothing usable is found, so the caller can save the
     * record anyway with an unplaced pin.
     *
     * @return array{lat: float, lng: float}|null
     */
    public function parse(string $input): ?array
    {
        $input = trim($input);

        if ($coords = $this->extractFromText($input)) {
            return $coords;
        }

        if ($resolved = $this->resolveShortLink($input)) {
            return $this->extractFromText($resolved);
        }

        return null;
    }

    /**
     * @return array{lat: float, lng: float}|null
     */
    private function extractFromText(string $text): ?array
    {
        $patterns = [
            // Google's place URLs encode the exact pin as !3d{lat}!4d{lng}
            // inside the data param — more precise than the @ centroid
            // below, so tried first.
            '/!3d(-?\d+\.\d+)!4d(-?\d+\.\d+)/',
            // .../@13.7563,100.5018,17z/...
            '/@(-?\d+\.\d+),(-?\d+\.\d+)/',
            // ?q=13.7563,100.5018 or &q=13.7563,100.5018
            '/[?&]q=(-?\d+\.\d+),(-?\d+\.\d+)/',
            // ?ll=13.7563,100.5018
            '/[?&]ll=(-?\d+\.\d+),(-?\d+\.\d+)/',
            // Plain "13.7563, 100.5018" pasted with nothing else around it
            '/^(-?\d+\.\d+)\s*,\s*(-?\d+\.\d+)$/',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $text, $matches)) {
                $lat = (float) $matches[1];
                $lng = (float) $matches[2];

                if (abs($lat) <= 90 && abs($lng) <= 180) {
                    return ['lat' => $lat, 'lng' => $lng];
                }
            }
        }

        return null;
    }

    /**
     * Short links (maps.app.goo.gl, goo.gl/maps, g.co/...) don't carry
     * coordinates in the URL itself — only the page they redirect to does
     * — so this follows the redirect and returns the final URL for
     * re-parsing. Returns null on anything but a recognized short-link
     * host, or if the request fails.
     */
    private function resolveShortLink(string $url): ?string
    {
        $host = parse_url($url, PHP_URL_HOST);

        if (! $host || ! preg_match('/(^|\.)(goo\.gl|g\.co)$/i', $host)) {
            return null;
        }

        try {
            $response = Http::timeout(5)->get($url);

            return (string) $response->effectiveUri();
        } catch (Throwable $e) {
            report($e);

            return null;
        }
    }
}
