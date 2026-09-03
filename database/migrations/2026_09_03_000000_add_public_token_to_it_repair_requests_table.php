<?php

use App\Models\ItRepairRequest;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * A per-request secret embedded in the "track your request" link handed
     * to the person who filed it (see ItRepairController::publicStore). The
     * link lets them view status and leave a rating without typing their
     * email — the unguessable token is the credential.
     */
    public function up(): void
    {
        Schema::table('it_repair_requests', function (Blueprint $table) {
            $table->string('public_token', 64)->nullable()->unique()->after('id');
        });

        ItRepairRequest::withoutGlobalScopes()
            ->whereNull('public_token')
            ->get(['id'])
            ->each(fn (ItRepairRequest $r) => $r->forceFill([
                'public_token' => Str::random(48),
            ])->saveQuietly());
    }

    public function down(): void
    {
        Schema::table('it_repair_requests', function (Blueprint $table) {
            $table->dropUnique(['public_token']);
            $table->dropColumn('public_token');
        });
    }
};
