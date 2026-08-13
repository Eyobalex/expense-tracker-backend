<?php

use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

test('postgresql redis queue lock and private minio are available', function (): void {
    $this->assertSame('pgsql', DB::connection()->getDriverName());
    $this->assertNotFalse(Redis::connection()->command('ping'));

    $lock = Cache::store('redis')->lock('infrastructure-test-lock', 5);

    $this->assertTrue($lock->get());
    $this->assertThrows(fn (): mixed => Cache::store('redis')->lock('infrastructure-test-lock', 5)->block(0), LockTimeoutException::class);
    $lock->release();

    Queue::pushRaw('{"uuid":"'.Str::uuid().'","displayName":"InfrastructureSmoke","job":"Illuminate\\Queue\\CallQueuedHandler@call","data":{"commandName":"","command":""}}', 'infrastructure-smoke');

    $this->assertSame(1, Queue::connection('redis')->size('infrastructure-smoke'));
    Queue::connection('redis')->clear('infrastructure-smoke');

    $key = 'infrastructure-tests/'.Str::uuid();
    $disk = Storage::disk('minio');

    $disk->put($key, 'smoke');

    $this->assertSame('smoke', $disk->get($key));
    $this->assertTrue($disk->delete($key));
});
