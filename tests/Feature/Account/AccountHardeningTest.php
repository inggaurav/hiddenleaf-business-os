<?php

namespace Tests\Feature\Account;

use App\Models\AccountBankTransfer;
use App\Models\AccountTransactionCategory;
use App\Models\AccountType;
use App\Models\LedgerAccount;
use App\Models\Organization;
use App\Models\Permission;
use App\Models\Plan;
use App\Models\Role;
use App\Models\User;
use App\Models\UserActiveModule;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountHardeningTest extends TestCase
{
    use RefreshDatabase;

    private function tenant(bool $ownerMembership = true): array
    {
        $this->seed();

        $owner = User::factory()->create(['role' => 'company_admin']);
        $plan = Plan::create([
            'name' => 'Account Hardening',
            'status' => true,
            'modules' => ['account'],
            'created_by' => $owner->id,
        ]);
        $organization = Organization::factory()->create([
            'owner_id' => $owner->id,
            'plan_id' => $plan->id,
        ]);
        $workspace = Workspace::factory()->create([
            'organization_id' => $organization->id,
            'created_by' => $owner->id,
        ]);
        $organization->members()->attach($owner, ['role' => 'owner']);
        if ($ownerMembership) {
            $workspace->members()->attach($owner);
        }
        UserActiveModule::firstOrCreate([
            'workspace_id' => $workspace->id,
            'module_name' => 'account',
        ]);

        $asset = AccountType::firstOrCreate(
            ['workspace_id' => $workspace->id, 'name' => 'Assets'],
            [
                'organization_id' => $organization->id,
                'classification' => 'asset',
                'normal_balance' => 'debit',
            ]
        );

        return compact('owner', 'organization', 'workspace', 'asset');
    }

    private function asTenant(User $user, Organization $organization, Workspace $workspace): self
    {
        return $this->actingAs($user)->withSession([
            'active_organization_id' => $organization->id,
            'active_workspace_id' => $workspace->id,
        ]);
    }

    public function test_bank_transfer_charges_are_balanced_and_insufficient_source_balance_is_rejected(): void
    {
        $tenant = $this->tenant();

        foreach ([
            ['code' => '1010', 'name' => 'Source Bank', 'opening_balance' => '1000.00'],
            ['code' => '1020', 'name' => 'Destination Bank', 'opening_balance' => '100.00'],
        ] as $bank) {
            $this->asTenant($tenant['owner'], $tenant['organization'], $tenant['workspace'])
                ->post('/accounting/bank-accounts', [
                    'account_type_id' => $tenant['asset']->id,
                    'code' => $bank['code'],
                    'name' => $bank['name'],
                    'currency' => 'USD',
                    'bank_name' => 'HiddenLeaf Bank',
                    'account_number' => $bank['code'].'-A',
                    'opening_balance' => $bank['opening_balance'],
                    'is_active' => true,
                ])->assertRedirect();
        }

        $source = LedgerAccount::where('workspace_id', $tenant['workspace']->id)->where('code', '1010')->firstOrFail();
        $destination = LedgerAccount::where('workspace_id', $tenant['workspace']->id)->where('code', '1020')->firstOrFail();

        $this->asTenant($tenant['owner'], $tenant['organization'], $tenant['workspace'])
            ->post('/accounting/bank-transfer-drafts', [
                'from_account_id' => $source->id,
                'to_account_id' => $destination->id,
                'amount' => '200.00',
                'transfer_charges' => '10.00',
                'transfer_date' => now()->toDateString(),
                'reference' => 'TRF-HARDEN-1',
            ])->assertRedirect();

        $transfer = AccountBankTransfer::where('workspace_id', $tenant['workspace']->id)->firstOrFail();
        $this->assertSame('pending', $transfer->status);
        $this->assertSame('10.00', $transfer->transfer_charges);

        $this->asTenant($tenant['owner'], $tenant['organization'], $tenant['workspace'])
            ->post("/accounting/bank-transfers/{$transfer->id}/process")
            ->assertRedirect();

        $this->assertSame('completed', $transfer->fresh()->status);
        $entry = $transfer->fresh()->journalEntry()->with('lines.account')->firstOrFail();
        $this->assertSame('posted', $entry->status);
        $this->assertSame('210.00', number_format((float) $entry->lines->sum('credit'), 2, '.', ''));
        $this->assertSame('210.00', number_format((float) $entry->lines->sum('debit'), 2, '.', ''));

        $this->asTenant($tenant['owner'], $tenant['organization'], $tenant['workspace'])
            ->post('/accounting/bank-transfer-drafts', [
                'from_account_id' => $source->id,
                'to_account_id' => $destination->id,
                'amount' => '5000.00',
                'transfer_charges' => '0.00',
                'transfer_date' => now()->toDateString(),
            ])->assertStatus(422);
    }

    public function test_legacy_direct_revenue_rejects_foreign_workspace_category(): void
    {
        $a = $this->tenant();
        $b = $this->tenant();

        $foreignCategory = AccountTransactionCategory::create([
            'organization_id' => $b['organization']->id,
            'workspace_id' => $b['workspace']->id,
            'type' => 'revenue',
            'name' => 'Foreign Revenue',
            'is_active' => true,
            'created_by' => $b['owner']->id,
        ]);

        $this->asTenant($a['owner'], $a['organization'], $a['workspace'])
            ->post('/accounting/revenues', [
                'category_id' => $foreignCategory->id,
                'amount' => '100.00',
                'date' => now()->toDateString(),
                'payment_method' => 'cash',
                'reference' => 'FOREIGN-CAT',
            ])->assertStatus(422);

        $this->assertDatabaseMissing('account_revenues', [
            'workspace_id' => $a['workspace']->id,
            'category_id' => $foreignCategory->id,
        ]);
    }

    public function test_granular_only_account_role_can_view_bank_accounts_but_cannot_create_them(): void
    {
        $tenant = $this->tenant(false);
        $member = User::factory()->create(['role' => 'member']);

        $role = Role::create([
            'name' => 'bank-viewer-'.uniqid(),
            'display_name' => 'Bank Viewer',
            'organization_id' => $tenant['organization']->id,
            'is_system' => false,
        ]);
        $permission = Permission::where('name', 'account.bank_account.view')->firstOrFail();
        $role->permissions()->sync([$permission->id]);

        $tenant['organization']->members()->attach($member, ['role' => 'member']);
        $tenant['workspace']->members()->attach($member, ['role_id' => $role->id]);

        $this->asTenant($member, $tenant['organization'], $tenant['workspace'])
            ->get('/accounting/bank-accounts')
            ->assertOk();

        $this->asTenant($member, $tenant['organization'], $tenant['workspace'])
            ->post('/accounting/bank-accounts', [
                'account_type_id' => $tenant['asset']->id,
                'code' => 'NOPE',
                'name' => 'Unauthorized Bank',
                'currency' => 'USD',
                'opening_balance' => '0.00',
            ])->assertForbidden();
    }
}
