<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class SystemSetting extends Model
{
    protected const CACHE_KEY = 'system_settings';

    protected $fillable = [
        'maintenance_mode_enabled',
        'maintenance_message',
        'favicon_path',
        'timezone',
        'footer_text',
        'cpu_warning_pct',
        'cpu_critical_pct',
        'mem_warning_pct',
        'mem_critical_pct',
        'datastore_warning_pct',
        'datastore_critical_pct',
        'session_timeout_minutes',
        'disabled_pages',
        'room_temp_min_c',
        'room_temp_max_c',
        'room_humidity_min_pct',
        'room_humidity_max_pct',
    ];

    protected $casts = [
        'maintenance_mode_enabled' => 'boolean',
        'cpu_warning_pct' => 'integer',
        'cpu_critical_pct' => 'integer',
        'mem_warning_pct' => 'integer',
        'mem_critical_pct' => 'integer',
        'datastore_warning_pct' => 'integer',
        'datastore_critical_pct' => 'integer',
        'session_timeout_minutes' => 'integer',
        'disabled_pages' => 'array',
        'room_temp_min_c' => 'float',
        'room_temp_max_c' => 'float',
        'room_humidity_min_pct' => 'float',
        'room_humidity_max_pct' => 'float',
    ];

    /**
     * The single settings row, created with defaults on first use. Cached
     * indefinitely — this is read on every request (see
     * ApplySystemSettings) so it must not cost a query each time. Callers
     * that change settings MUST call forgetCache() afterward.
     *
     * Caches the plain attribute array, not the Eloquent instance itself —
     * Laravel's cache stores restrict unserialize() to an allow-listed set
     * of classes, so a raw cached model silently comes back as
     * __PHP_Incomplete_Class. Rehydrating via newFromBuilder() (the same
     * method the query builder itself uses) avoids that entirely.
     */
    public static function current(): self
    {
        $attributes = Cache::rememberForever(self::CACHE_KEY, function () {
            $row = static::query()->first();

            // Deliberately not firstOrCreate(['id' => 1], [...]) — 'id'
            // isn't mass-assignable (not in $fillable), so it would be
            // silently dropped from the insert and a fresh row would get
            // whatever the next auto-increment value is instead of a
            // predictable id. There's only ever one row, so "first, or
            // create one with defaults" is all that actually matters.
            $row ??= static::query()->create([
                'maintenance_mode_enabled' => false,
                'timezone' => 'UTC',
                'cpu_warning_pct' => 70,
                'cpu_critical_pct' => 85,
                'mem_warning_pct' => 70,
                'mem_critical_pct' => 85,
                'datastore_warning_pct' => 70,
                'datastore_critical_pct' => 85,
                'session_timeout_minutes' => 120,
                'disabled_pages' => ['modsecurity'],
                'room_temp_min_c' => 18,
                'room_temp_max_c' => 27,
                'room_humidity_min_pct' => 40,
                'room_humidity_max_pct' => 60,
            ]);

            return $row->getAttributes();
        });

        return (new static)->newFromBuilder($attributes);
    }

    public static function forgetCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}
