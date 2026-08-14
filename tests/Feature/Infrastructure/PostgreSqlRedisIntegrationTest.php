<?php

namespace Tests\Feature\Infrastructure;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PostgreSqlRedisIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        if (! env('RUN_INFRASTRUCTURE_TESTS')) {
            $this->markTestSkipped('Infrastructure integration tests are opt-in.');
        }
    }

    public function test_postgresql_jsonb_foreign_keys_transactions_and_timezone(): void
    {
        $this->assertSame('pgsql', DB::connection()->getDriverName());

        $type = DB::selectOne(<<<'SQL'
            SELECT data_type
            FROM information_schema.columns
            WHERE table_schema = 'public' AND table_name = 'audit_logs' AND column_name = 'metadata'
        SQL);
        $this->assertSame('jsonb', $type->data_type);

        $foreignKeys = DB::selectOne(<<<'SQL'
            SELECT COUNT(*)::int AS aggregate
            FROM pg_constraint
            WHERE contype = 'f' AND conrelid = 'workspace_memberships'::regclass
        SQL);
        $this->assertGreaterThanOrEqual(2, $foreignKeys->aggregate);

        DB::beginTransaction();
        DB::table('languages')->insert([
            'code' => 'tx',
            'name' => 'Transaction',
            'status' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::rollBack();
        $this->assertDatabaseMissing('languages', ['code' => 'tx']);

        $timezone = DB::selectOne('SHOW TIMEZONE');
        $this->assertNotEmpty((array) $timezone);
    }

    public function test_live_redis_service_responds(): void
    {
        $this->assertSame('PONG', $this->redis(['PING']));
    }

    public function test_redis_cache_lock_rate_limit_queue_and_idempotency_primitives(): void
    {
        $prefix = 'hiddenleaf:test:'.bin2hex(random_bytes(6));
        $cache = $prefix.':cache';
        $lock = $prefix.':lock';
        $rate = $prefix.':rate';
        $queue = $prefix.':queue';
        $idempotency = $prefix.':idempotency';

        try {
            $this->assertSame('OK', $this->redis(['SET', $cache, 'cached-value', 'EX', '30']));
            $this->assertSame('cached-value', $this->redis(['GET', $cache]));
            $this->assertSame('OK', $this->redis(['SET', $lock, 'owner-a', 'NX', 'PX', '5000']));
            $this->assertNull($this->redis(['SET', $lock, 'owner-b', 'NX', 'PX', '5000']));
            $this->assertSame(1, $this->redis(['INCR', $rate]));
            $this->assertSame(2, $this->redis(['INCR', $rate]));
            $this->assertSame(1, $this->redis(['RPUSH', $queue, '{"job":"verify"}']));
            $this->assertSame('{"job":"verify"}', $this->redis(['LPOP', $queue]));
            $this->assertSame('OK', $this->redis(['SET', $idempotency, 'delivery-1', 'NX', 'EX', '30']));
            $this->assertNull($this->redis(['SET', $idempotency, 'delivery-2', 'NX', 'EX', '30']));
            $this->assertSame('redis', config('queue.connections.redis.driver'));
        } finally {
            $this->redis(['DEL', $cache, $lock, $rate, $queue, $idempotency]);
        }
    }

    private function redis(array $parts): string|int|null
    {
        $socket = @fsockopen((string) env('REDIS_HOST', '127.0.0.1'), (int) env('REDIS_PORT', 6379), $error, $message, 3);
        $this->assertIsResource($socket, "Redis connection failed: {$error} {$message}");
        $request = '*'.count($parts)."\r\n";
        foreach ($parts as $part) {
            $part = (string) $part;
            $request .= '$'.strlen($part)."\r\n{$part}\r\n";
        }
        fwrite($socket, $request);
        $line = fgets($socket);
        if ($line === false) {
            fclose($socket);
            $this->fail('Redis returned no response.');
        }
        $type = $line[0];
        $value = rtrim(substr($line, 1), "\r\n");
        if ($type === '$') {
            if ((int) $value === -1) {
                fclose($socket);

                return null;
            }
            $payload = stream_get_contents($socket, (int) $value);
            stream_get_contents($socket, 2);
            fclose($socket);

            return $payload;
        }
        fclose($socket);
        if ($type === '-') {
            $this->fail('Redis error: '.$value);
        }

        return match ($type) {
            '+' => $value,
            ':' => (int) $value,
            default => null,
        };
    }
}
