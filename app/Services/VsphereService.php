<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class VsphereService
{
    protected const SESSION_CACHE_KEY = 'vsphere.session_id';

    protected const SESSION_TTL_SECONDS = 1800;

    protected ?string $baseUrl;

    protected ?string $username;

    protected ?string $password;

    public function __construct()
    {
        $this->baseUrl = rtrim((string) config('services.vsphere.url'), '/');
        $this->username = config('services.vsphere.username');
        $this->password = config('services.vsphere.password');
    }

    public function getVms(): array
    {
        return $this->get('/api/vcenter/vm');
    }

    public function getHosts(): array
    {
        return $this->get('/api/vcenter/host');
    }

    public function getClusters(): array
    {
        return $this->get('/api/vcenter/cluster');
    }

    public function getDatastores(): array
    {
        return $this->get('/api/vcenter/datastore');
    }

    /**
     * Maps each VM id to the name of the host it runs on. The basic VM list
     * endpoint doesn't include host assignment, and this vCenter's `/api`
     * surface rejects all `filter.*` query params (HTTP 400 on every filter
     * tried, including documented ones like filter.power_states) — so this
     * falls back to the legacy `/rest/vcenter/vm` surface, which still
     * supports `filter.hosts` and wraps results in a `{"value": [...]}`
     * envelope, queried once per host.
     *
     * @return array<string, string> vm id => host name
     */
    public function getVmHostMap(): array
    {
        $map = [];

        foreach ($this->getHosts() as $host) {
            $vms = $this->getLegacyFiltered('/rest/vcenter/vm?filter.hosts='.urlencode($host['host']));

            foreach ($vms as $vm) {
                $map[$vm['vm']] = $host['name'];
            }
        }

        return $map;
    }

    protected function get(string $path): array
    {
        return $this->request($path)->json() ?? [];
    }

    /**
     * Same as get(), but for the legacy `/rest/...` API surface, whose
     * responses are wrapped as {"value": [...]} instead of a bare array.
     */
    protected function getLegacyFiltered(string $path): array
    {
        return $this->request($path)->json('value') ?? [];
    }

    /**
     * Sends an authenticated request to vCenter. On a 401 (expired/invalid
     * session), clears the cached session, re-authenticates, and retries once.
     */
    protected function request(string $path, bool $isRetry = false): Response
    {
        $sessionId = $this->getSessionId();

        $response = $this->client()
            ->withHeaders(['vmware-api-session-id' => $sessionId])
            ->get($this->baseUrl.$path);

        if ($response->status() === 401 && ! $isRetry) {
            Cache::forget(self::SESSION_CACHE_KEY);

            return $this->request($path, isRetry: true);
        }

        if ($response->failed()) {
            throw new RuntimeException("vCenter API error [{$path}]: HTTP {$response->status()}");
        }

        return $response;
    }

    protected function getSessionId(): string
    {
        return Cache::remember(
            self::SESSION_CACHE_KEY,
            self::SESSION_TTL_SECONDS,
            fn () => $this->authenticate(),
        );
    }

    protected function authenticate(): string
    {
        if (! $this->baseUrl || ! $this->username || ! $this->password) {
            throw new RuntimeException('กรุณาตั้งค่า VSPHERE_URL, VSPHERE_USERNAME, VSPHERE_PASSWORD ในไฟล์ .env');
        }

        $response = $this->client()
            ->withBasicAuth($this->username, $this->password)
            ->post($this->baseUrl.'/api/session');

        if ($response->failed()) {
            throw new RuntimeException("ไม่สามารถเข้าสู่ระบบ vCenter ได้ (HTTP {$response->status()})");
        }

        return trim($response->body(), '"');
    }

    protected function client(): PendingRequest
    {
        $http = Http::timeout(30);

        // Self-signed certs are common on internal vCenter appliances;
        // only skip verification outside production.
        if (! app()->environment('production')) {
            $http = $http->withoutVerifying();
        }

        return $http;
    }
}
