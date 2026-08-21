<?php

namespace App\Services;

use Anthropic\Client;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Throwable;

class AlarmHintService
{
    protected const CACHE_TTL_SECONDS = 86400;

    protected const MODEL = 'claude-opus-5';

    public function __construct(
        protected ?Client $client = null,
    ) {}

    /**
     * Given a list of triggered alarms (as they appear per object — the
     * same alarm definition commonly fires on many objects), returns one
     * AI-generated remediation hint per unique alarm (deduped by
     * name+description, since the fix is identical each time), keyed by a
     * stable hash of that pair so the caller can look a hint up per alarm
     * instance. Results are cached for a day per unique alarm so repeated
     * page loads/refreshes don't re-call the API for alarms already
     * explained. Returns an empty array if no API key is configured or the
     * request fails — the alarm list itself still renders without hints.
     *
     * @param  array<int, array{name: string, description: string}>  $alarms
     * @return array<string, string> cacheKey(alarm) => hint text
     */
    public function hints(array $alarms): array
    {
        $apiKey = config('services.anthropic.api_key');

        if (empty($alarms) || ! $apiKey) {
            return [];
        }

        $unique = collect($alarms)
            ->unique(fn (array $alarm) => $alarm['name'].'|'.$alarm['description'])
            ->mapWithKeys(fn (array $alarm) => [$this->cacheKey($alarm) => $alarm])
            ->all();

        $cached = [];
        $uncached = [];

        foreach ($unique as $key => $alarm) {
            $hit = Cache::get($key);

            if ($hit !== null) {
                $cached[$key] = $hit;
            } else {
                $uncached[$key] = $alarm;
            }
        }

        if (empty($uncached)) {
            return $cached;
        }

        $fresh = $this->requestHints($uncached);

        foreach ($fresh as $key => $hint) {
            Cache::put($key, $hint, self::CACHE_TTL_SECONDS);
        }

        return $cached + $fresh;
    }

    /**
     * @param  array{name: string, description: string}  $alarm
     */
    public function cacheKey(array $alarm): string
    {
        return 'alarm_hint:'.md5($alarm['name'].'|'.$alarm['description']);
    }

    /**
     * @param  array<string, array{name: string, description: string}>  $alarms
     * @return array<string, string>
     */
    protected function requestHints(array $alarms): array
    {
        $list = collect($alarms)
            ->map(fn (array $alarm, string $key) => [
                'id' => $key,
                'name' => $alarm['name'],
                'description' => $alarm['description'],
            ])
            ->values()
            ->all();

        $prompt = 'You are a VMware vSphere infrastructure expert. For each triggered alarm below, '.
            "give a short, actionable hint (1-3 sentences) on how to diagnose and resolve it.\n".
            "Respond with ONLY a JSON object mapping each alarm's \"id\" to its hint string — no other text, no markdown fence.\n\n".
            json_encode($list, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        try {
            $client = $this->client ??= new Client(apiKey: config('services.anthropic.api_key'));

            $message = $client->messages->create(
                maxTokens: 2000,
                model: self::MODEL,
                outputConfig: ['effort' => 'low'],
                messages: [
                    ['role' => 'user', 'content' => $prompt],
                ],
            );

            $text = null;

            foreach ($message->content as $block) {
                if ($block->type === 'text') {
                    $text = $block->text;
                    break;
                }
            }

            if ($text === null) {
                return [];
            }

            $parsed = json_decode($this->extractJson($text), true);

            if (! is_array($parsed)) {
                return [];
            }

            return collect($parsed)
                ->filter(fn ($hint, $key) => is_string($hint) && array_key_exists($key, $alarms))
                ->all();
        } catch (Throwable $e) {
            report($e);

            return [];
        }
    }

    /**
     * Claude sometimes wraps JSON in a ```json fence even when asked not
     * to — strip it before decoding.
     */
    protected function extractJson(string $text): string
    {
        $text = trim($text);

        if (Str::startsWith($text, '```')) {
            $text = preg_replace('/^```(?:json)?\s*/', '', $text);
            $text = preg_replace('/\s*```$/', '', (string) $text);
        }

        return trim((string) $text);
    }
}
