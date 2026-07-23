<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class CompanySetting extends Model
{
    protected $fillable = [
        'ceo_summary_time',
        'ceo_summary_last_sent_on',
        'mail_mailer',
        'mail_host',
        'mail_port',
        'mail_username',
        'mail_password',
        'mail_encryption',
        'mail_from_address',
        'mail_from_name',
        'epe_api_url',
        'epe_site_key',
    ];

    protected $hidden = [
        'mail_password',
    ];

    protected function casts(): array
    {
        return [
            'ceo_summary_last_sent_on' => 'date',
            // Laravel's built-in encrypt-on-write/decrypt-on-read cast — this is
            // an SMTP credential at rest in the database, not a plaintext column.
            'mail_password' => 'encrypted',
        ];
    }

    public static function current(): self
    {
        return static::query()->firstOrCreate(['id' => 1]);
    }

    /**
     * Layers DB-stored mail/push credentials on top of whatever .env already
     * provided, so an admin can change them from Settings without touching the
     * server. Guarded on the table existing since this runs on every artisan
     * boot, including `migrate` on a fresh install before this table exists.
     */
    public static function applyRuntimeConfig(): void
    {
        if (! Schema::hasTable('company_settings')) {
            return;
        }

        $settings = static::current();

        if ($settings->mail_mailer) {
            config(['mail.default' => $settings->mail_mailer]);
        }

        if ($settings->mail_host) {
            config([
                'mail.mailers.smtp.host' => $settings->mail_host,
                'mail.mailers.smtp.port' => $settings->mail_port,
                'mail.mailers.smtp.username' => $settings->mail_username,
                'mail.mailers.smtp.password' => $settings->mail_password,
                'mail.mailers.smtp.encryption' => $settings->mail_encryption,
            ]);
        }

        if ($settings->mail_from_address) {
            config([
                'mail.from.address' => $settings->mail_from_address,
                'mail.from.name' => $settings->mail_from_name ?? config('mail.from.name'),
            ]);
        }

        if ($settings->epe_api_url) {
            config(['services.epe.api_url' => $settings->epe_api_url]);
        }

        if ($settings->epe_site_key) {
            config(['services.epe.site_key' => $settings->epe_site_key]);
        }
    }
}
