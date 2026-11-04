<?php

namespace App\Auth;

use Illuminate\Auth\EloquentUserProvider;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Cache;

// La sesión ya no va a la base. El usuario se recuerda unos minutos para no buscarlo en cada página.
class CachedUserProvider extends EloquentUserProvider
{
    public function retrieveById($identifier): ?Authenticatable
    {
        if ($identifier === null || app()->runningUnitTests()) {
            return parent::retrieveById($identifier);
        }

        $key = self::cacheKey($identifier);
        $cached = Cache::get($key);
        if (is_array($cached)) {
            return $this->createModel()->newFromBuilder($cached);
        }

        $user = parent::retrieveById($identifier);
        if ($user) {
            Cache::put($key, $user->getRawOriginal(), now()->addMinutes(30));
        }

        return $user;
    }

    public static function forget(int|string $id): void
    {
        Cache::forget(self::cacheKey($id));
    }

    private static function cacheKey(int|string $id): string
    {
        return 'auth-user:'.$id;
    }
}
