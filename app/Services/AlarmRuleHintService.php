<?php

namespace App\Services;

use Illuminate\Support\Str;

class AlarmRuleHintService
{
    /**
     * Local, zero-configuration fallback for AlarmHintService: a
     * keyword → hint lookup covering vCenter's common built-in alarm
     * types, so "AI Suggested Fix" always has *something* useful to show
     * even with no ANTHROPIC_API_KEY configured (or if that call fails).
     * Matched case-insensitively against the alarm's name + description
     * combined; the first rule with a matching keyword wins, so more
     * specific rules are listed before broader ones covering the same
     * area (e.g. "cannot connect" before the generic "connection" rule).
     *
     * @var array<int, array{keywords: array<int, string>, hint: string}>
     */
    protected const RULES = [
        [
            'keywords' => ['cannot connect to host', 'host connection and power state', 'not responding'],
            'hint' => 'Check that the host is powered on and reachable on the network (ping its management IP), then confirm the management agents are running — from the DCUI/console: Restart Management Agents. If it stays disconnected, check for a recent password/certificate change on the host.',
        ],
        [
            'keywords' => ['host cpu usage'],
            'hint' => 'Check which VMs are consuming the most CPU on this host (Performance charts, sorted by CPU) and consider migrating some of them via vMotion to a less-loaded host, or adding vCPU limits/shares if one VM is monopolizing the host.',
        ],
        [
            'keywords' => ['host memory usage'],
            'hint' => 'Check for memory overcommitment and ballooning/swapping on this host\'s VMs (Performance charts). Migrate some VMs to a less-loaded host via vMotion, or add physical RAM if this is a sustained trend rather than a spike.',
        ],
        [
            'keywords' => ['virtual machine cpu usage', 'vm cpu usage'],
            'hint' => 'Check what\'s running inside the guest OS (a runaway process, malware, or genuine load increase). If it\'s legitimate sustained demand, consider adding vCPUs or moving the VM to a less-contended host.',
        ],
        [
            'keywords' => ['virtual machine memory usage', 'vm memory usage'],
            'hint' => 'Check the guest OS for memory leaks or an undersized memory allocation. If usage is consistently high and swapping is occurring, increase the VM\'s memory allocation.',
        ],
        [
            'keywords' => ['datastore usage on disk', 'datastore cluster', 'disk usage', 'space utilization'],
            'hint' => 'Free up space by deleting old snapshots, orphaned/unregistered VM files, and stale ISOs/templates on this datastore. If it\'s genuinely full, migrate some VMs to a datastore with more free space (Storage vMotion) — see the Datastore page for a fill-up projection.',
        ],
        [
            'keywords' => ['snapshot'],
            'hint' => 'Old snapshots consume growing amounts of space and slow the VM down. Consolidate or delete snapshots that are no longer needed (Snapshot Manager → Delete/Delete All) once you\'ve confirmed they\'re not required for rollback.',
        ],
        [
            'keywords' => ['network uplink redundancy', 'network connectivity', 'nic', 'link status', 'lost network'],
            'hint' => 'Check the physical NIC/switch port and cabling for this host\'s uplinks. If only one of several redundant uplinks is down, the host stays reachable but loses failover protection — fix the failed link before the remaining one also fails.',
        ],
        [
            'keywords' => ['storage path redundancy', 'path redundancy', 'lost storage path'],
            'hint' => 'One of the redundant paths to this storage device has failed — check HBA/FC switch/iSCSI network connectivity and cabling. The datastore is still reachable via the remaining path(s), but resolve this before it becomes a full outage.',
        ],
        [
            'keywords' => ['health status changed', 'sensor', 'hardware health', 'fan', 'power supply', 'temperature', 'voltage'],
            'hint' => 'A hardware sensor on this host (fan, power supply, temperature, or voltage) reported a non-normal state — check the host\'s hardware health status in vCenter (Monitor → Hardware Health) and the physical server\'s own management console (iLO/iDRAC/etc.) for details.',
        ],
        [
            'keywords' => ['license'],
            'hint' => 'A license is missing, expired, or over its assigned capacity. Check Administration → Licensing in vCenter and renew or reassign licenses as needed.',
        ],
        [
            'keywords' => ['time', 'ntp', 'clock'],
            'hint' => 'Clock drift between this host/VM and vCenter can cause authentication and cluster issues. Verify NTP is configured and synced on the host (Configure → Time Configuration) and check the guest OS\'s own time sync if this is a VM alarm.',
        ],
        [
            'keywords' => ['vsphere ha host status', 'ha agent', 'failover'],
            'hint' => 'This host is having trouble with the vSphere HA agent — check network connectivity to the other hosts in the cluster and that management network heartbeats are getting through. Reconfigure HA on the host if the agent won\'t reconnect on its own.',
        ],
        [
            'keywords' => ['certificate'],
            'hint' => 'A certificate (host, vCenter, or a monitored service) is expired or expiring soon. Renew it before expiry to avoid an authentication/connectivity outage — see the VM/Host\'s Certificate Exp field if this is one this app tracks.',
        ],
        [
            'keywords' => ['vmware tools'],
            'hint' => 'VMware Tools is out of date, not running, or not installed in this VM\'s guest OS. Update/install it (right-click the VM → Guest OS → Install/Upgrade VMware Tools) — several monitoring and management features depend on it.',
        ],
        [
            'keywords' => ['cpu ready', 'ready time'],
            'hint' => 'High CPU Ready time means this VM is waiting for physical CPU time the host can\'t immediately give it — usually host CPU overcommitment. Reduce the VM\'s vCPU count if it\'s oversized, or move it to a less-contended host.',
        ],
    ];

    protected const DEFAULT_HINT = 'No specific rule matched this alarm — check the vCenter/ESXi logs for this object and search VMware\'s Knowledge Base for the exact alarm name for detailed remediation steps.';

    /**
     * @return array{hint: string, source: 'rule'}
     */
    public function hint(string $name, ?string $description): array
    {
        $haystack = Str::lower($name.' '.($description ?? ''));

        foreach (self::RULES as $rule) {
            foreach ($rule['keywords'] as $keyword) {
                if (str_contains($haystack, Str::lower($keyword))) {
                    return ['hint' => $rule['hint'], 'source' => 'rule'];
                }
            }
        }

        return ['hint' => self::DEFAULT_HINT, 'source' => 'rule'];
    }
}
