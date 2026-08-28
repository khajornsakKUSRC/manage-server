<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * system_settings.disabled_pages was found encoded up to 3 levels deep
     * (a JSON string whose decoded content was itself a JSON string, and so
     * on) instead of once — likely from some past write assigning an
     * already-encoded string to the cast attribute, which the plain
     * 'array' cast then encoded again on top of. That left the app reading
     * a string instead of an array wherever disabled_pages was consumed,
     * which corrupted frontend code that assumed a real array (e.g.
     * spreading a string into individual characters — see
     * App\Casts\SafeJsonArrayCast, now used for this column instead of
     * 'array' to make this self-healing going forward). This repairs the
     * existing row(s) by decoding however many layers deep they actually
     * are and writing back a single clean encoding.
     */
    public function up(): void
    {
        DB::table('system_settings')
            ->whereNotNull('disabled_pages')
            ->get(['id', 'disabled_pages'])
            ->each(function ($row) {
                $decoded = $row->disabled_pages;
                $guard = 0;

                while (is_string($decoded) && $guard < 5) {
                    $next = json_decode($decoded, true);

                    if ($next === null && json_last_error() !== JSON_ERROR_NONE) {
                        break;
                    }

                    $decoded = $next;
                    $guard++;
                }

                if (! is_array($decoded)) {
                    $decoded = [];
                }

                DB::table('system_settings')
                    ->where('id', $row->id)
                    ->update(['disabled_pages' => json_encode($decoded)]);
            });
    }

    public function down(): void
    {
        // The corrupted multi-encoded formatting isn't worth recreating —
        // no-op, matching the existing certificate_exp normalization
        // migration's precedent for this kind of one-time data repair.
    }
};
