<?php

use Carbon\Carbon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * certificate_exp was free text (e.g. "Friday, October 23, 2026") from
     * the old plain-text input — the VM Edit page and the new bulk import
     * modal both switched to a native <input type="date">, which requires
     * exactly Y-m-d to display or validate. Normalizes every existing
     * value to that format so existing VMs remain editable; a value Carbon
     * can't parse is left as-is (shows as a blank date picker rather than
     * being destroyed, and the user can just pick a new one).
     */
    public function up(): void
    {
        DB::table('vms')
            ->whereNotNull('certificate_exp')
            ->orderBy('id')
            ->select('id', 'certificate_exp')
            ->each(function ($vm) {
                try {
                    $normalized = Carbon::parse($vm->certificate_exp)->format('Y-m-d');
                } catch (Throwable) {
                    return;
                }

                if ($normalized !== $vm->certificate_exp) {
                    DB::table('vms')->where('id', $vm->id)->update(['certificate_exp' => $normalized]);
                }
            });
    }

    public function down(): void
    {
        // The original free-text formatting isn't recoverable — no-op.
    }
};
