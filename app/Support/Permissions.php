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
        'appliance' => 'Appliance Health',
        'daily-reports' => 'Daily Report',
        'alarms' => 'Alarm Notification',
        'datastores' => 'Datastore',
        'network-infrastructure' => 'Network Infrastructure',
        'network-map' => 'Map Network',
        'performance' => 'Performance',
        'smart-detection' => 'Smart Detection',
        'modsecurity' => 'Mod Security',
    ];

    /**
     * @return array<int, string>
     */
    public static function keys(): array
    {
        return array_keys(self::PAGES);
    }
}
