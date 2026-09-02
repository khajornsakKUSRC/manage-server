<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Presentation controls for the IT Repair "Send Email" template — the
     * visual shell that used to be hard-coded in
     * resources/views/emails/it-repair-notification.blade.php. With these,
     * the Settings → IT Repair Notification Email section can customise the
     * whole template: its logo, colours and layout, not just the text.
     */
    public function up(): void
    {
        Schema::table('system_settings', function (Blueprint $table) {
            // Uploaded logo (public disk). Null = fall back to the bundled
            // KU logo at public/image/it-repair-email-logo-ku.png.
            $table->string('it_repair_email_logo_path')->nullable()->after('it_repair_email_footer');
            $table->boolean('it_repair_email_show_logo')->default(true)->after('it_repair_email_logo_path');
            $table->unsignedSmallInteger('it_repair_email_logo_width')->default(64)->after('it_repair_email_show_logo');
            $table->string('it_repair_email_heading_color', 7)->default('#18181b')->after('it_repair_email_logo_width');
            $table->string('it_repair_email_text_color', 7)->default('#18181b')->after('it_repair_email_heading_color');
            $table->string('it_repair_email_background_color', 7)->default('#ffffff')->after('it_repair_email_text_color');
            // 'full'  = edge-to-edge, like a normal email in the reading pane
            // 'centered' = a fixed-width column centred on the background
            $table->string('it_repair_email_layout', 16)->default('full')->after('it_repair_email_background_color');
            $table->unsignedSmallInteger('it_repair_email_content_width')->default(600)->after('it_repair_email_layout');
        });

        // SystemSetting::current() caches its row forever — see that model
        // and the disabled_pages migration for the same note.
        Cache::forget('system_settings');
    }

    public function down(): void
    {
        Schema::table('system_settings', function (Blueprint $table) {
            $table->dropColumn([
                'it_repair_email_logo_path',
                'it_repair_email_show_logo',
                'it_repair_email_logo_width',
                'it_repair_email_heading_color',
                'it_repair_email_text_color',
                'it_repair_email_background_color',
                'it_repair_email_layout',
                'it_repair_email_content_width',
            ]);
        });

        Cache::forget('system_settings');
    }
};
