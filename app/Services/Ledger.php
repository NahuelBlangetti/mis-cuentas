<?php

namespace App\Services;

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
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

// La base está lejos del servidor: cada consulta paga un viaje de red. Estas tablas son chicas,
// así que se leen de una sola vez y se reutilizan hasta que algo se guarda.
class Ledger
{
    private const VERSION_KEY = 'ledger-version';

    /** @var array<string, list<string>> */
    public const COLUMNS = [
        'clients' => ['id', 'user_id', 'name', 'phone', 'alias', 'systems', 'monthly_fee', 'due_day', 'active', 'notes', 'created_at', 'updated_at'],
        'charges' => ['id', 'client_id', 'billing_month', 'amount', 'created_at', 'updated_at'],
        'transactions' => ['id', 'user_id', 'client_id', 'client_name', 'type', 'area', 'description', 'category', 'amount', 'date', 'billing_month', 'created_at', 'updated_at'],
        'fixed_expenses' => ['id', 'user_id', 'infrastructure_resource_id', 'name', 'category', 'currency', 'notes', 'created_at', 'updated_at'],
        'fixed_expense_amounts' => ['id', 'fixed_expense_id', 'effective_from', 'amount', 'created_at', 'updated_at'],
        'exchange_rates' => ['id', 'user_id', 'currency', 'month', 'rate', 'source', 'quoted_at', 'created_at', 'updated_at'],
        'infrastructure_resources' => ['id', 'user_id', 'name', 'provider', 'kind', 'external_id', 'active', 'notes', 'created_at', 'updated_at'],
        'infrastructure_resource_shares' => ['id', 'infrastructure_resource_id', 'effective_from', 'share_percent', 'notes', 'created_at', 'updated_at'],
        'client_installations' => ['id', 'client_id', 'infrastructure_resource_id', 'name', 'url', 'created_at', 'updated_at'],
        'installation_databases' => ['id', 'client_installation_id', 'infrastructure_resource_id', 'name', 'connection', 'created_at', 'updated_at'],
        'personal_expenses' => ['id', 'user_id', 'description', 'category', 'amount', 'date', 'created_at', 'updated_at'],
        'personal_recurring_expenses' => ['id', 'user_id', 'name', 'category', 'currency', 'notes', 'created_at', 'updated_at'],
        'personal_recurring_amounts' => ['id', 'recurring_expense_id', 'effective_from', 'amount', 'created_at', 'updated_at'],
    ];

    /** @var array<string, string> */
    public const SOURCES = [
        'clients' => 'user',
        'charges' => 'clients',
        'transactions' => 'user',
        'fixed_expenses' => 'user',
        'fixed_expense_amounts' => 'fixed_expenses',
        'exchange_rates' => 'user',
        'infrastructure_resources' => 'user',
        'infrastructure_resource_shares' => 'resources',
        'client_installations' => 'clients',
        'installation_databases' => 'installations',
        'personal_expenses' => 'user',
        'personal_recurring_expenses' => 'user',
        'personal_recurring_amounts' => 'recurring',
    ];

    private static ?int $userId = null;

    private static ?array $graph = null;

    public static function reset(): void
    {
        self::$userId = null;
        self::$graph = null;
    }

    // Descarta la copia. Dentro de una transacción el número de versión cambia recién al confirmar,
    // para no guardar datos que después se revierten.
    public static function flush(): void
    {
        self::reset();
        if (app()->runningUnitTests()) {
            return;
        }

        $bump = function () {
            Cache::forever(self::VERSION_KEY, ((int) Cache::get(self::VERSION_KEY, 1)) + 1);
        };
        if (DB::transactionLevel() > 0) {
            DB::afterCommit($bump);

            return;
        }
        $bump();
    }

    public static function clients(User $user): Collection
    {
        return self::graph($user)['clients'];
    }

    public static function transactions(User $user): Collection
    {
        return self::graph($user)['transactions'];
    }

    public static function resources(User $user): Collection
    {
        return self::graph($user)['resources'];
    }

    public static function installations(User $user): Collection
    {
        return self::graph($user)['installations'];
    }

    public static function fixedExpenses(User $user): Collection
    {
        return self::graph($user)['fixed'];
    }

    public static function personalExpenses(User $user): Collection
    {
        return self::graph($user)['personal'];
    }

    public static function recurringExpenses(User $user): Collection
    {
        return self::graph($user)['recurring'];
    }

    public static function exchangeRate(User $user, string $month): ?ExchangeRate
    {
        return self::graph($user)['rates']->first(fn (ExchangeRate $rate) => $rate->currency === 'USD' && $rate->month === $month);
    }

    private static function graph(User $user): array
    {
        if (self::$userId === $user->id && self::$graph !== null) {
            return self::$graph;
        }

        self::$userId = $user->id;

        return self::$graph = self::hydrate(self::rows($user->id));
    }

    private static function rows(int $userId): array
    {
        // En un test, o con una transacción abierta, hay que leer lo que acaba de escribirse.
        if (app()->runningUnitTests() || DB::transactionLevel() > 0) {
            return self::fetch($userId);
        }

        $version = (int) Cache::get(self::VERSION_KEY, 1);

        return Cache::remember("ledger:{$userId}:{$version}", now()->addMinutes(10), fn () => self::fetch($userId));
    }

    private static function fetch(int $userId): array
    {
        return DB::connection()->getDriverName() === 'mysql' ? self::fetchMysql($userId) : self::fetchSeparate($userId);
    }

    private static function fetchMysql(int $userId): array
    {
        $parts = [];
        $bindings = [];
        foreach (self::SOURCES as $table => $scope) {
            $where = match ($scope) {
                'user' => "`$table`.user_id = ?",
                'clients' => "`$table`.client_id in (select id from clients where user_id = ?)",
                'fixed_expenses' => "`$table`.fixed_expense_id in (select id from fixed_expenses where user_id = ?)",
                'resources' => "`$table`.infrastructure_resource_id in (select id from infrastructure_resources where user_id = ?)",
                'installations' => "`$table`.client_installation_id in (select client_installations.id from client_installations inner join clients on clients.id = client_installations.client_id where clients.user_id = ?)",
                'recurring' => "`$table`.recurring_expense_id in (select id from personal_recurring_expenses where user_id = ?)",
            };
            $parts[] = "'$table', (select coalesce(json_arrayagg(row_json), json_array()) from (select ".self::jsonObject($table)." as row_json from `$table` where $where) as `{$table}_rows`)";
            $bindings[] = $userId;
        }

        $payload = DB::selectOne('select json_object('.implode(', ', $parts).') as payload', $bindings)->payload ?? '[]';
        $decoded = json_decode($payload, true);

        return is_array($decoded) ? $decoded : [];
    }

    private static function jsonObject(string $table): string
    {
        $fields = [];
        foreach (self::COLUMNS[$table] as $column) {
            $fields[] = "'$column', cast(`$table`.`$column` as char)";
        }

        return 'json_object('.implode(', ', $fields).')';
    }

    private static function fetchSeparate(int $userId): array
    {
        $clients = DB::table('clients')->where('user_id', $userId)->get();
        $clientIds = $clients->pluck('id');
        $installations = DB::table('client_installations')->whereIn('client_id', $clientIds)->get();
        $resources = DB::table('infrastructure_resources')->where('user_id', $userId)->get();
        $fixed = DB::table('fixed_expenses')->where('user_id', $userId)->get();
        $recurring = DB::table('personal_recurring_expenses')->where('user_id', $userId)->get();
        $plain = fn (Collection $rows) => $rows->map(fn ($row) => (array) $row)->all();

        return [
            'clients' => $plain($clients),
            'charges' => $plain(DB::table('charges')->whereIn('client_id', $clientIds)->get()),
            'transactions' => $plain(DB::table('transactions')->where('user_id', $userId)->get()),
            'fixed_expenses' => $plain($fixed),
            'fixed_expense_amounts' => $plain(DB::table('fixed_expense_amounts')->whereIn('fixed_expense_id', $fixed->pluck('id'))->get()),
            'exchange_rates' => $plain(DB::table('exchange_rates')->where('user_id', $userId)->get()),
            'infrastructure_resources' => $plain($resources),
            'infrastructure_resource_shares' => $plain(DB::table('infrastructure_resource_shares')->whereIn('infrastructure_resource_id', $resources->pluck('id'))->get()),
            'client_installations' => $plain($installations),
            'installation_databases' => $plain(DB::table('installation_databases')->whereIn('client_installation_id', $installations->pluck('id'))->get()),
            'personal_expenses' => $plain(DB::table('personal_expenses')->where('user_id', $userId)->get()),
            'personal_recurring_expenses' => $plain($recurring),
            'personal_recurring_amounts' => $plain(DB::table('personal_recurring_amounts')->whereIn('recurring_expense_id', $recurring->pluck('id'))->get()),
        ];
    }

    private static function hydrate(array $tables): array
    {
        $clients = self::models(Client::class, $tables['clients'] ?? []);
        $charges = self::models(Charge::class, $tables['charges'] ?? []);
        $transactions = self::models(Transaction::class, $tables['transactions'] ?? []);
        $resources = self::models(InfrastructureResource::class, $tables['infrastructure_resources'] ?? []);
        $shares = self::models(InfrastructureResourceShare::class, $tables['infrastructure_resource_shares'] ?? []);
        $installations = self::models(ClientInstallation::class, $tables['client_installations'] ?? []);
        $databases = self::models(InstallationDatabase::class, $tables['installation_databases'] ?? []);
        $fixed = self::models(FixedExpense::class, $tables['fixed_expenses'] ?? []);
        $fixedAmounts = self::models(FixedExpenseAmount::class, $tables['fixed_expense_amounts'] ?? []);
        $rates = self::models(ExchangeRate::class, $tables['exchange_rates'] ?? []);
        $personal = self::models(PersonalExpense::class, $tables['personal_expenses'] ?? []);
        $recurring = self::models(PersonalRecurringExpense::class, $tables['personal_recurring_expenses'] ?? []);
        $recurringAmounts = self::models(PersonalRecurringAmount::class, $tables['personal_recurring_amounts'] ?? []);

        $clientsById = $clients->keyBy('id');
        $resourcesById = $resources->keyBy('id');
        $installationsById = $installations->keyBy('id');

        foreach ($transactions as $transaction) {
            $transaction->setRelation('client', $transaction->client_id ? $clientsById->get($transaction->client_id) : null);
        }
        foreach ($clients as $client) {
            $client->setRelation('charges', self::bucket($charges, 'client_id', $client->id));
            $client->setRelation('transactions', self::bucket($transactions, 'client_id', $client->id));
            $client->setRelation('installations', self::bucket($installations, 'client_id', $client->id)->sortBy('name')->values());
        }
        foreach ($databases as $database) {
            $database->setRelation('resource', $database->infrastructure_resource_id ? $resourcesById->get($database->infrastructure_resource_id) : null);
            $database->setRelation('installation', $installationsById->get($database->client_installation_id));
        }
        foreach ($installations as $installation) {
            $installation->setRelation('client', $clientsById->get($installation->client_id));
            $installation->setRelation('resource', $installation->infrastructure_resource_id ? $resourcesById->get($installation->infrastructure_resource_id) : null);
            $installation->setRelation('databases', self::bucket($databases, 'client_installation_id', $installation->id)->sortBy('name')->values());
        }
        foreach ($resources as $resource) {
            $resource->setRelation('shares', self::bucket($shares, 'infrastructure_resource_id', $resource->id)->sortByDesc('effective_from')->values());
            $resource->setRelation('installations', self::bucket($installations, 'infrastructure_resource_id', $resource->id));
            $resource->setRelation('databases', self::bucket($databases, 'infrastructure_resource_id', $resource->id));
            $resource->setAttribute('fixed_expenses_count', self::bucket($fixed, 'infrastructure_resource_id', $resource->id)->count());
        }
        foreach ($fixed as $expense) {
            $expense->setRelation('amounts', self::bucket($fixedAmounts, 'fixed_expense_id', $expense->id)->sortByDesc('effective_from')->values());
            $expense->setRelation('resource', $expense->infrastructure_resource_id ? $resourcesById->get($expense->infrastructure_resource_id) : null);
        }
        foreach ($recurring as $expense) {
            $expense->setRelation('amounts', self::bucket($recurringAmounts, 'recurring_expense_id', $expense->id)->sortByDesc('effective_from')->values());
        }

        return [
            'clients' => $clients, 'transactions' => $transactions, 'resources' => $resources, 'installations' => $installations,
            'fixed' => $fixed, 'personal' => $personal, 'recurring' => $recurring, 'rates' => $rates,
        ];
    }

    private static function bucket(Collection $models, string $key, int|string|null $id): Collection
    {
        if ($id === null) {
            return new Collection;
        }

        return $models->where($key, $id)->values();
    }

    /** @param  class-string<Model>  $class */
    private static function models(string $class, array $rows): Collection
    {
        $model = new $class;

        return $model->newCollection(array_map(function ($row) use ($class) {
            return (new $class)->newFromBuilder(self::normalize((array) $row));
        }, array_is_list($rows) ? $rows : array_values($rows)));
    }

    private static function normalize(array $row): array
    {
        foreach ($row as $key => $value) {
            if (is_string($value) && ($key === 'id' || str_ends_with($key, '_id'))) {
                $row[$key] = (int) $value;
            }
        }

        return $row;
    }
}
