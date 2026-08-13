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
        $socket = @fsockopen((string) env('REDIS_HOST', '127.0.0.1'), (int) env('REDIS_PORT', 6379), $error, $message, 3);
        $this->assertIsResource($socket, "Redis connection failed: {$error} {$message}");
        fwrite($socket, "*1\r\n$4\r\nPING\r\n");
        $response = fgets($socket);
        fclose($socket);

        $this->assertSame("+PONG\r\n", $response);
    }
}
