<?php

namespace App\Services;

class OwnerAccess
{
    public static function allows(string $email): bool
    {
        $owner = trim((string) config('owner.email'));

        if ($owner === '') {
            return app()->environment('local', 'testing');
        }

        return strcasecmp($owner, trim($email)) === 0;
    }
}
