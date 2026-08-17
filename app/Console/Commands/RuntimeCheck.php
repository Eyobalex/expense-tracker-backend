<?php

namespace App\Console\Commands;

use App\Support\RuntimeConfigurationValidator;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

#[Signature('runtime:check {--deep : Verify PostgreSQL, Redis, and MinIO connectivity} {--production : Verify production release configuration gates}')]
#[Description('Validate the required PostgreSQL, Redis, and MinIO runtime configuration')]
class RuntimeCheck extends Command
{
    public function __construct(private RuntimeConfigurationValidator $runtimeConfigurationValidator)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $configuration = $this->configuration();
        $errors = $this->runtimeConfigurationValidator->validate($configuration);
        if ($this->option('production')) {
            $errors = [...$errors, ...$this->runtimeConfigurationValidator->validateProductionRelease($configuration)];
        }

        if ($errors !== []) {
            foreach ($errors as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        if ($this->option('deep')) {
            try {
                DB::connection('pgsql')->getPdo();
                Redis::connection()->command('ping');
                $key = 'runtime-checks/'.Str::uuid();
                $disk = Storage::disk('minio');

                try {
                    $disk->put($key, '');

                    if (! $disk->exists($key)) {
                        throw new \RuntimeException('MinIO did not persist the runtime check object.');
                    }
                } finally {
                    $disk->delete($key);
                }
            } catch (Throwable $throwable) {
                $this->error('Runtime connectivity check failed: '.$throwable->getMessage());

                return self::FAILURE;
            }
        }

        $this->info('Runtime configuration is valid.');

        return self::SUCCESS;
    }

    /**
     * @return array{app_key: mixed, app_debug: mixed, api_docs_enabled: mixed, database_default: mixed, cache_default: mixed, queue_default: mixed, filesystem_default: mixed, minio: array<string, mixed>, release: array<string, mixed>, logging: array<string, mixed>}
     */
    private function configuration(): array
    {
        return [
            'app_key' => config('app.key'),
            'app_debug' => config('app.debug'),
            'api_docs_enabled' => config('api_docs.enabled'),
            'database_default' => config('database.default'),
            'cache_default' => config('cache.default'),
            'queue_default' => config('queue.default'),
            'filesystem_default' => config('filesystems.default'),
            'minio' => config('filesystems.disks.minio', []),
            'release' => config('release', []),
            'logging' => config('logging', []),
        ];
    }
}
