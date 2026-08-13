<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Models\Organization;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class EmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    protected function createVerifiedTenantUser(bool $verified = false): User
    {
        $user = User::factory()->create([
            'email_verified_at' => $verified ? now() : null,
        ]);
        $org = Organization::factory()->create(['owner_id' => $user->id]);
        $ws = Workspace::factory()->create(['organization_id' => $org->id, 'created_by' => $user->id]);
        $user->organizations()->attach($org->id, ['role' => 'owner']);
        $user->workspaces()->attach($ws->id);
        return $user;
    }

    public function test_email_can_be_verified_with_valid_signed_url(): void
    {
        $user = $this->createVerifiedTenantUser(false);

        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $user->id, 'hash' => sha1($user->getEmailForVerification())]
        );

        $response = $this->actingAs($user)
            ->withSession(['active_organization_id' => $user->organizations()->first()->id])
            ->get($verificationUrl);

        $this->assertTrue($user->fresh()->hasVerifiedEmail());
    }

    public function test_email_verification_fails_without_signature(): void
    {
        $user = $this->createVerifiedTenantUser(false);

        $response = $this->actingAs($user)
            ->withSession(['active_organization_id' => $user->organizations()->first()->id])
            ->get("/verify-email/{$user->id}/" . sha1($user->getEmailForVerification()));

        $response->assertStatus(403);
        $this->assertFalse($user->fresh()->hasVerifiedEmail());
    }
}
