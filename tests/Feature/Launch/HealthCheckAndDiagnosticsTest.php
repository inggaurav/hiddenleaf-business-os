<?php

namespace Tests\Feature\Launch;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HealthCheckAndDiagnosticsTest extends TestCase
{
    use RefreshDatabase;

    public function test_health_check_endpoints_and_cli_diagnostics(): void
    {
        // 1. GET /health/live
        $resLive = $this->get(route('health.live'));
        $resLive->assertStatus(200);
        $resLive->assertJson(['status' => 'live']);

        // 2. GET /health/ready
        $resReady = $this->get(route('health.ready'));
        $resReady->assertStatus(200);
        $resReady->assertJson(['status' => 'ready']);
        $resReady->assertJsonStructure(['checks' => ['database', 'storage', 'cache']]);

        // 3. CLI Command: php artisan hiddenleaf:check
        $this->artisan('hiddenleaf:check')
            ->assertExitCode(0);
    }
}
