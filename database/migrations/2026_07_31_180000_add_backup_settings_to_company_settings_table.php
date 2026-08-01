<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('company_settings', function (Blueprint $table) {
            // Null frequency means backups are disabled — nothing runs until an
            // admin both connects Google Drive and picks a schedule.
            $table->string('backup_frequency')->nullable();
            $table->time('backup_time')->nullable();
            $table->unsignedInteger('backup_retention_count')->default(7);

            // Google Drive OAuth (drive.file scope — the app only ever sees the
            // single "EWMS Backups" folder it creates for itself, not the
            // connected account's whole Drive). Tokens are encrypted at rest,
            // same treatment as mail_password.
            $table->string('google_drive_connected_email')->nullable();
            $table->text('google_drive_access_token')->nullable();
            $table->text('google_drive_refresh_token')->nullable();
            $table->timestamp('google_drive_token_expires_at')->nullable();
            $table->string('google_drive_folder_id')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('company_settings', function (Blueprint $table) {
            $table->dropColumn([
                'backup_frequency',
                'backup_time',
                'backup_retention_count',
                'google_drive_connected_email',
                'google_drive_access_token',
                'google_drive_refresh_token',
                'google_drive_token_expires_at',
                'google_drive_folder_id',
            ]);
        });
    }
};
