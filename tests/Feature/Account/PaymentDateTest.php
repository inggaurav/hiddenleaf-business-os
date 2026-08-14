<?php

namespace Tests\Feature\Account;

use App\Domain\Accounting\AccountDashboardService;
use App\Models\AccountCustomer;
use App\Models\CustomerPayment;
use App\Models\Organization;
use App\Models\SalesInvoice;
use App\Models\User;
use App\Models\Workspace;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentDateTest extends TestCase
{
    use RefreshDatabase;

    public function test_monthly_trend_uses_payment_date_not_invoice_date(): void
    {
        $user = User::factory()->create();
        $org = Organization::factory()->create(['owner_id' => $user->id]);
        $ws = Workspace::factory()->create(['organization_id' => $org->id, 'created_by' => $user->id]);

        $thisMonth = Carbon::now();
        $lastMonth = Carbon::now()->subMonth();

        $customer = AccountCustomer::create([
            'organization_id' => $org->id,
            'workspace_id' => $ws->id,
            'name' => 'Date Test Customer',
        ]);

        // Invoice issued last month, payment made this month
        $invoice = SalesInvoice::create([
            'organization_id' => $org->id,
            'workspace_id' => $ws->id,
            'invoice_id' => 'SI-DATE-1',
            'customer_id' => $customer->id,
            'issue_date' => $lastMonth->toDateString(),
            'total_amount' => 1000,
            'status' => 'paid',
            'created_by' => $user->id,
        ]);

        CustomerPayment::create([
            'organization_id' => $org->id,
            'workspace_id' => $ws->id,
            'customer_id' => $customer->id,
            'invoice_id' => $invoice->id,
            'amount' => 1000,
            'payment_date' => $thisMonth->toDateString(),
            'payment_method' => 'cash',
        ]);

        $service = app(AccountDashboardService::class);
        $metrics = $service->getMetrics($ws);

        $trends = collect($metrics['monthlyCustomerPayments'])->keyBy('month');
        $thisMonthLabel = $thisMonth->format('M');
        $lastMonthLabel = $lastMonth->format('M');

        $this->assertEquals(1000, $trends->get($thisMonthLabel)['customer_payments'] ?? 0);
        $this->assertEquals(0, $trends->get($lastMonthLabel)['customer_payments'] ?? 0);
    }
}
