<?php

namespace App\Providers;

use App\Auth\CachedUserProvider;
use App\Models\Charge;
use App\Models\Client;
use App\Models\ClientInstallation;
use App\Models\ExchangeRate;
use App\Models\FixedExpense;
use App\Models\FixedExpenseAmount;
use App\Models\InfrastructureResource;
use App\Models\InfrastructureResourceShare;
use App\Models\InstallationDatabase;
use App\Models\PersonalExpense;
use App\Models\PersonalRecurringAmount;
use App\Models\PersonalRecurringExpense;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Ledger;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        Auth::provider('cached-eloquent', function ($app, array $config) {
            return new CachedUserProvider($app['hash'], $config['model']);
        });
    }

    public function boot(): void
    {
        foreach ([
            Client::class, Charge::class, Transaction::class, FixedExpense::class, FixedExpenseAmount::class, ExchangeRate::class,
            PersonalExpense::class, PersonalRecurringExpense::class, PersonalRecurringAmount::class, InfrastructureResource::class,
            InfrastructureResourceShare::class, ClientInstallation::class, InstallationDatabase::class,
        ] as $model) {
            $model::saved(fn () => Ledger::flush());
            $model::deleted(fn () => Ledger::flush());
        }
        User::saved(fn (User $user) => CachedUserProvider::forget($user->getKey()));
        $this->app->terminating(fn () => Ledger::reset());
    }
}
