<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Extends the asset register to the fields on the paper ทะเบียนครุภัณฑ์
     * card that weren't captured yet: extra code numbers, a free-text spec
     * field distinct from brand/model, quantity/unit, and who in Supplies
     * handles the record. it_asset_assignments — already the "who/where/
     * when" history table — grows the document/action fields needed to log
     * each ซื้อ/จ้าง/ย้าย/จำหน่าย transaction, since these belong to one
     * historical entry, not the asset's current snapshot; assigned_at
     * doubles as "วันที่ดำเนินการ" rather than adding a redundant column.
     */
    public function up(): void
    {
        Schema::table('it_assets', function (Blueprint $table) {
            $table->string('erp_asset_code')->nullable()->after('asset_code');
            $table->string('asset_code_3d')->nullable()->after('erp_asset_code');
            $table->string('old_asset_code')->nullable()->after('asset_code_3d');
            $table->text('specifications')->nullable()->after('model');
            $table->unsignedInteger('quantity')->nullable()->after('specifications');
            $table->string('unit')->nullable()->after('quantity');
            $table->string('supply_officer_name')->nullable()->after('assigned_to');
        });

        Schema::table('it_asset_assignments', function (Blueprint $table) {
            // purchase (ซื้อ) | hire (จ้าง) | move (ย้าย) | dispose (จำหน่าย)
            $table->string('document_type', 20)->nullable()->after('it_asset_id');
            $table->string('document_number')->nullable()->after('document_type');
            $table->date('document_date')->nullable()->after('document_number');
            // Free-text — the officer who actually processed this entry,
            // not necessarily the logged-in user recorded in created_by.
            $table->string('performed_by_name')->nullable()->after('note');
        });
    }

    public function down(): void
    {
        Schema::table('it_asset_assignments', function (Blueprint $table) {
            $table->dropColumn(['document_type', 'document_number', 'document_date', 'performed_by_name']);
        });

        Schema::table('it_assets', function (Blueprint $table) {
            $table->dropColumn([
                'erp_asset_code', 'asset_code_3d', 'old_asset_code',
                'specifications', 'quantity', 'unit', 'supply_officer_name',
            ]);
        });
    }
};
