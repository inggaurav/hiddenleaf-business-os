<?php

namespace Tests\Feature\Parity;

use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class ParityEvidenceValidationTest extends TestCase
{
    public function test_parity_registry_only_references_live_hiddenleaf_evidence(): void
    {
        $exitCode = Artisan::call('parity:validate');

        $this->assertSame(0, $exitCode, Artisan::output());
    }
}
