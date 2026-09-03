<?php

namespace App\Support;

final class Permissions
{
    /**
     * Every page that can be individually granted to a user, keyed by the
     * permission stored in users.permissions. Admins bypass this entirely.
     *
     * @var array<string, string>
     */
    public const PAGES = [
        'dashboard' => 'Dashboard',
        'hosts' => 'Manage Hosts',
        'vms' => 'Manage VMs',
        'certificate-expiration' => 'Certificate Expiration',
        'appliance' => 'Appliance Health',
        'daily-reports' => 'Daily Report',
        'calendar-notice' => 'Calendar Notice',
        'it-repair' => 'IT Repair',
        'it-repair-evaluation' => 'Service Evaluation',
        'alarms' => 'Alarm Notification',
        'datastores' => 'Datastore',
        'network-infrastructure' => 'Network Infrastructure',
        'network-map' => 'Map Network',
        'performance' => 'Performance',
        'smart-detection' => 'Smart Detection',
        'modsecurity' => 'Mod Security',
        'services' => 'Services',
        'it-assets' => 'ครุภัณฑ์ไอที',
    ];

    /**
     * What each page actually exposes — shown under the checkbox on the
     * Add/Edit User screens so whoever grants access knows what information
     * that menu opens up. Keys match PAGES.
     *
     * @var array<string, string>
     */
    public const DESCRIPTIONS = [
        'dashboard' => 'Summary tiles: host and VM health, active alarms, datastore usage and recent activity.',
        'hosts' => 'ESXi / physical host inventory — power state, CPU and memory load, uptime and hardware detail.',
        'vms' => 'Virtual machine inventory — guest OS, resource usage, IP addresses and power controls.',
        'certificate-expiration' => 'Tracked SSL/TLS certificates, their issuers and days remaining until expiry.',
        'appliance' => 'Health checks for appliance services and components, with pass/warn/fail status.',
        'daily-reports' => 'Generated daily PDF reports and the history of who they were sent to.',
        'calendar-notice' => 'Scheduled maintenance notices and reminders shown before login and on the calendar.',
        'it-repair' => 'Repair requests from staff — details, status changes and the notification-email action.',
        'it-repair-evaluation' => 'Recipient satisfaction ratings for completed repairs and the scoring criteria.',
        'alarms' => 'vCenter alarm notifications, their severity and acknowledgement state.',
        'datastores' => 'Datastore capacity, free space, provisioned space and usage trends.',
        'network-infrastructure' => 'Monitored network devices and links with their latest up/down check results.',
        'network-map' => 'Interactive topology map of network nodes and the connections between them.',
        'performance' => 'Historical CPU, memory, disk and network I/O performance charts.',
        'smart-detection' => 'Smart-detection events and anomaly notifications raised by the monitoring stack.',
        'modsecurity' => 'ModSecurity WAF activity — rule hits, blocked requests and their source addresses.',
        'services' => 'Monitored system services and whether each is currently reachable.',
        'it-assets' => 'ทะเบียนครุภัณฑ์ไอที การตรวจสอบ ประวัติ รูปถ่าย QR Code การซ่อมบำรุง และรอบตรวจนับ.',
    ];

    /**
     * @return array<int, string>
     */
    public static function keys(): array
    {
        return array_keys(self::PAGES);
    }

    /**
     * PAGES merged with DESCRIPTIONS as `key => {label, description}`, for
     * the front end.
     *
     * @return array<string, array{label: string, description: string}>
     */
    public static function withDescriptions(): array
    {
        $out = [];

        foreach (self::PAGES as $key => $label) {
            $out[$key] = [
                'label' => $label,
                'description' => self::DESCRIPTIONS[$key] ?? '',
            ];
        }

        return $out;
    }
}
