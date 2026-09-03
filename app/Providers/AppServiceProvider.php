<?php

namespace App\Providers;

use App\Database\WindowsResilientSQLiteConnection;
use Carbon\CarbonImmutable;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        Connection::resolverFor('sqlite', function ($connection, $database, $prefix, $config) {
            return new WindowsResilientSQLiteConnection($connection, $database, $prefix, $config);
        });
    }

    public function boot(): void
    {
        $this->configureDefaults();

        // High-Performance SQLite Tuning (WAL Mode, in-memory temp store, 64MB page cache, 256MB mmap)
        if (config('database.default') === 'sqlite') {
            try {
                DB::statement('PRAGMA journal_mode=WAL;');
                DB::statement('PRAGMA synchronous=NORMAL;');
                DB::statement('PRAGMA cache_size=-64000;');
                DB::statement('PRAGMA temp_store=MEMORY;');
                DB::statement('PRAGMA mmap_size=268435456;');
                DB::statement('PRAGMA busy_timeout=10000;');
            } catch (\Throwable $e) {
            }
        }
    }

    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
