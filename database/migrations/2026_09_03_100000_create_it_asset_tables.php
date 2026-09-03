<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * IT Asset Management ("ครุภัณฑ์ไอที") — the asset master plus its
     * inspection history. The master is entered once (it_assets); every
     * check the person on the floor does, whether from the login-free
     * public page or a counting round, appends one it_asset_inspections
     * row. Photos hang off the inspection, not the asset.
     */
    public function up(): void
    {
        Schema::create('it_asset_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();          // e.g. "คอมพิวเตอร์ตั้งโต๊ะ"
            $table->string('code_prefix', 20)->nullable(); // e.g. "PC" -> PC-0001
            $table->timestamps();
        });

        Schema::create('it_assets', function (Blueprint $table) {
            $table->id();
            // Human asset number printed on the physical label.
            $table->string('asset_code')->unique();
            // Unguessable id embedded in the QR / public URL (/asset/{token}).
            $table->string('public_token', 64)->unique();
            $table->string('name');
            $table->foreignId('it_asset_category_id')->nullable()->constrained()->nullOnDelete();
            $table->string('brand')->nullable();
            $table->string('model')->nullable();
            $table->string('serial_number')->nullable();
            // in_use | in_storage | repair | retired | lost
            $table->string('status', 20)->default('in_use');
            $table->string('department')->nullable();
            $table->string('location')->nullable();
            // Free-text holder name — the authoritative history is in
            // it_asset_assignments; this is just the current label.
            $table->string('assigned_to')->nullable();
            $table->date('purchased_at')->nullable();
            $table->decimal('price', 12, 2)->nullable();
            $table->date('warranty_until')->nullable();
            $table->string('photo_path')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('last_inspected_at')->nullable();
            $table->string('last_inspection_status', 20)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index('status');
            $table->index('it_asset_category_id');
            $table->index('location');
        });

        Schema::create('it_asset_inspections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('it_asset_id')->constrained()->cascadeOnDelete();
            // Set when the check happened inside a counting round.
            $table->foreignId('inventory_session_id')->nullable();
            // normal (พบ/ปกติ) | damaged (ชำรุด) | moved (ย้าย) | missing (ไม่พบ)
            $table->string('status', 20);
            $table->text('note')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            // staff | public | counting
            $table->string('source', 20)->default('staff');
            $table->foreignId('inspected_by')->nullable()->constrained('users')->nullOnDelete();
            // Free-text name for a login-free public check.
            $table->string('inspector_name')->nullable();
            $table->timestamps();

            $table->index(['it_asset_id', 'created_at']);
            $table->index('inventory_session_id');
        });

        Schema::create('it_asset_inspection_photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('it_asset_inspection_id')->constrained()->cascadeOnDelete();
            $table->string('path');
            $table->timestamps();
        });

        DB::table('it_asset_categories')->insert([
            ['name' => 'คอมพิวเตอร์ตั้งโต๊ะ', 'code_prefix' => 'PC', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'โน้ตบุ๊ก', 'code_prefix' => 'NB', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'จอภาพ', 'code_prefix' => 'MON', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'เครื่องพิมพ์', 'code_prefix' => 'PRN', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'อุปกรณ์เครือข่าย', 'code_prefix' => 'NET', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'เซิร์ฟเวอร์', 'code_prefix' => 'SRV', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'อุปกรณ์ต่อพ่วง', 'code_prefix' => 'PER', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('it_asset_inspection_photos');
        Schema::dropIfExists('it_asset_inspections');
        Schema::dropIfExists('it_assets');
        Schema::dropIfExists('it_asset_categories');
    }
};
