<?php

namespace App\Services;

use Illuminate\Support\Str;

/** Shared by Google SSO login and the Google Drive backup connect flow — both gate on the same company email allow-list. */
class GoogleWorkspaceDomain
{
    public static function isAllowed(string $email): bool
    {
        $allowedDomains = config('services.google.allowed_domains', []);

        if (empty($allowedDomains)) {
            return false;
        }

        $domain = Str::lower(Str::after($email, '@'));

        return in_array($domain, array_map(Str::lower(...), $allowedDomains), true);
    }
}
