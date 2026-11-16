<?php

namespace App\Console\Commands;

use App\Models\User;
use Database\Seeders\LocalAdminSeeder;
use Dotenv\Dotenv;
use Faker\Factory as FakerFactory;
use Faker\Generator;
use Illuminate\Console\Command;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Copies the production data into the local database, so local development
 * looks like the real site without ever touching production.
 *
 * Production is only read, with the DB_* credentials from .env.prod.
 * Personal data that isn't needed for development is left out or replaced:
 * production users are not copied (the only local account is admin@gouwwaas.be,
 * who takes over every activity), and registrants get fake names, e-mail
 * addresses and answers. Uploaded files (photos) are not copied; the site falls
 * back to initials.
 */
class PullProductionData extends Command
{
    protected $signature = 'db:pull-production
        {--force : Don\'t ask for confirmation before wiping the local database}';

    protected $description = 'Replace the local database with an anonymised copy of the production data';

    /** Runtime state and accounts: never copied. */
    private const SKIP = [
        'migrations', 'sessions', 'cache', 'cache_locks', 'jobs', 'job_batches',
        'failed_jobs', 'password_reset_tokens', 'users',
    ];

    private const PRODUCTION_ENV_FILE = '.env.prod';

    private const CHUNK = 500;

    private Generator $faker;

    /** @var array<int, string> activity_fields.id => type, to fake answers by type */
    private array $fieldTypes = [];

    public function handle(): int
    {
        if (app()->isProduction()) {
            $this->error('This command never runs in production.');

            return self::FAILURE;
        }

        if (! $this->configureProductionConnection()) {
            $this->error('Copy .env.prod.example to .env.prod and fill in the production database credentials first.');

            return self::FAILURE;
        }

        $local = DB::connection();
        $production = DB::connection('production');

        if ($this->isSameDatabase($local, $production)) {
            $this->error('The local database points at production. Run this with the local environment (APP_ENV=local, .env.local).');

            return self::FAILURE;
        }

        $target = $local->getConfig('host').':'.$local->getConfig('port').'/'.$local->getDatabaseName();
        if (! $this->option('force') && ! $this->confirm("This wipes the local database {$target} and fills it with production data. Continue?")) {
            return self::FAILURE;
        }

        $this->faker = FakerFactory::create('nl_BE');

        $this->info('Rebuilding the local schema…');
        $this->call('migrate:fresh', ['--force' => true]);

        $productionTables = collect(Schema::connection('production')->getTableListing(schemaQualified: false))->flip();
        $this->fieldTypes = $productionTables->has('activity_fields')
            ? $production->table('activity_fields')->pluck('type', 'id')->all()
            : [];

        Schema::disableForeignKeyConstraints();

        try {
            foreach (Schema::getTableListing(schemaQualified: false) as $table) {
                if (in_array($table, self::SKIP, true)) {
                    continue;
                }

                if (! $productionTables->has($table)) {
                    $this->line("  <comment>{$table}</comment>: not in production yet, kept as created by the migrations");

                    continue;
                }

                $this->copyTable($production, $local, $table);
            }

            // The one local account takes over everything production users owned.
            $this->callSilently('db:seed', ['--class' => LocalAdminSeeder::class, '--force' => true]);
            $adminId = User::where('email', LocalAdminSeeder::EMAIL)->value('id');
            $local->table('activities')->whereNotNull('user_id')->update(['user_id' => $adminId]);
        } finally {
            Schema::enableForeignKeyConstraints();
        }

        $this->newLine();
        $this->info('Done. Log in as '.LocalAdminSeeder::EMAIL.' with the password "'.LocalAdminSeeder::PASSWORD.'".');
        $this->line('Photos are not copied; people without a local photo show their initials.');

        return self::SUCCESS;
    }

    /**
     * The "production" connection, from DB_* in .env.prod (unless it is configured already, e.g. in tests).
     */
    private function configureProductionConnection(): bool
    {
        if (config('database.connections.production.database')) {
            return true;
        }

        $file = base_path(self::PRODUCTION_ENV_FILE);
        if (! is_file($file)) {
            return false;
        }

        $env = Dotenv::parse((string) file_get_contents($file));
        if (blank($env['DB_DATABASE'] ?? null)) {
            return false;
        }

        config(['database.connections.production' => [
            'driver' => $env['DB_CONNECTION'] ?? 'mysql',
            'host' => $env['DB_HOST'] ?? '127.0.0.1',
            'port' => $env['DB_PORT'] ?? '3306',
            'database' => $env['DB_DATABASE'],
            'username' => $env['DB_USERNAME'] ?? '',
            'password' => $env['DB_PASSWORD'] ?? '',
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'prefix_indexes' => true,
            'strict' => true,
            'engine' => null,
        ]]);

        return true;
    }

    private function copyTable(Connection $production, Connection $local, string $table): void
    {
        // Only columns both sides know, so a newer local schema still works.
        $columns = array_values(array_intersect(
            Schema::connection('production')->getColumnListing($table),
            Schema::getColumnListing($table),
        ));

        $local->table($table)->truncate();

        $query = $production->table($table)->select($columns);
        $count = 0;

        $insert = function ($rows) use ($local, $table, &$count) {
            $rows = $rows->map(fn ($row) => $this->anonymise($table, (array) $row))->all();
            $local->table($table)->insert($rows);
            $count += count($rows);
        };

        if (in_array('id', $columns, true)) {
            $query->chunkById(self::CHUNK, $insert);
        } else {
            $insert($query->get());
        }

        $this->line("  <info>{$table}</info>: {$count} rows");
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function anonymise(string $table, array $row): array
    {
        return match ($table) {
            'registrations' => array_merge($row, [
                'name' => $this->faker->name(),
                'email' => $this->faker->unique()->safeEmail(),
                'answers' => $this->fakeAnswers($row['answers'] ?? null),
            ]),
            default => $row,
        };
    }

    /**
     * Keep the shape (and choices like dropdowns and checkboxes), replace what people typed.
     */
    private function fakeAnswers(?string $json): ?string
    {
        $answers = json_decode((string) $json, true);

        if (! is_array($answers)) {
            return $json;
        }

        foreach ($answers as &$answer) {
            if (! is_array($answer) || blank($answer['value'] ?? null)) {
                continue;
            }

            $answer['value'] = match ($this->fieldTypes[$answer['field_id'] ?? 0] ?? 'text') {
                'select', 'checkbox', 'number' => $answer['value'],
                'email' => $this->faker->safeEmail(),
                'tel' => $this->faker->phoneNumber(),
                'date' => $this->faker->date(),
                'textarea' => $this->faker->sentence(12),
                default => $this->faker->words(2, true),
            };
        }

        return json_encode($answers, JSON_UNESCAPED_UNICODE);
    }

    private function isSameDatabase(Connection $a, Connection $b): bool
    {
        return strtolower((string) $a->getConfig('host')) === strtolower((string) $b->getConfig('host'))
            && (string) $a->getConfig('port') === (string) $b->getConfig('port')
            && $a->getDatabaseName() === $b->getDatabaseName();
    }
}
