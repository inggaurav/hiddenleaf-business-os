<?php

namespace App\Http\Controllers;

use App\Models\AccountCustomer;
use App\Models\AccountVendor;
use App\Models\SalesProposal;
use App\Models\TasklyProject;
use App\Models\Workspace;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class PortalController extends Controller
{
    public function dashboard(Request $request)
    {
        $workspace = $this->workspace($request);
        $role = $request->user()->role;
        abort_unless(in_array($role, ['client', 'customer', 'vendor'], true), 403);

        return $role === 'vendor'
            ? $this->vendorDashboard($request, $workspace)
            : $this->clientDashboard($request, $workspace);
    }

    public function decideProposal(Request $request, SalesProposal $proposal)
    {
        $workspace = $this->workspace($request);
        $customer = $this->customer($request, $workspace);
        abort_unless((int) $proposal->organization_id === (int) $workspace->organization_id
            && (int) $proposal->workspace_id === (int) $workspace->id
            && (int) $proposal->customer_id === (int) $customer->id, 404);
        abort_unless(in_array((string) $proposal->status, ['draft', 'sent', 'pending'], true), 422, 'Proposal has already been decided.');
        $data = $request->validate(['decision' => ['required', Rule::in(['accepted', 'rejected'])]]);
        $proposal->update(['status' => $data['decision']]);

        return back()->with('success', 'Proposal '.$data['decision'].'.');
    }

    public function storeProjectPayment(Request $request)
    {
        $workspace = $this->workspace($request);
        $customer = $this->customer($request, $workspace);
        $data = $request->validate([
            'project_id' => ['required', 'integer'],
            'payment_date' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'payment_method' => ['required', Rule::in(['cash', 'bank', 'card', 'online', 'other'])],
            'reference' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
        $project = TasklyProject::forWorkspace($workspace->organization_id, $workspace->id)->findOrFail($data['project_id']);
        abort_unless($project->members()->where('users.id', $request->user()->id)->exists(), 404);

        DB::table('taskly_project_payments')->insert($data + [
            'organization_id' => $workspace->organization_id,
            'workspace_id' => $workspace->id,
            'customer_id' => $customer->id,
            'status' => 'submitted',
            'created_by' => $request->user()->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return back()->with('success', 'Project payment submitted.');
    }

    private function clientDashboard(Request $request, Workspace $workspace)
    {
        $customer = $this->customer($request, $workspace);
        $projects = TasklyProject::forWorkspace($workspace->organization_id, $workspace->id)
            ->whereHas('members', fn ($query) => $query->where('users.id', $request->user()->id))
            ->withCount(['stages'])
            ->latest()
            ->get();
        $invoices = DB::table('sales_invoices')->where('organization_id', $workspace->organization_id)->where('workspace_id', $workspace->id)->where('customer_id', $customer->id)->latest()->get();
        $proposals = SalesProposal::where('organization_id', $workspace->organization_id)->where('workspace_id', $workspace->id)->where('customer_id', $customer->id)->latest()->get();
        $returns = DB::table('sales_invoice_returns')->where('organization_id', $workspace->organization_id)->where('workspace_id', $workspace->id)->where('customer_id', $customer->id)->latest()->get();
        $payments = DB::table('customer_payments')->where('organization_id', $workspace->organization_id)->where('workspace_id', $workspace->id)->where('customer_id', $customer->id)->latest()->get();
        $creditNotes = DB::table('account_credit_notes')->where('organization_id', $workspace->organization_id)->where('workspace_id', $workspace->id)->where('customer_id', $customer->id)->latest()->get();
        $projectPayments = DB::table('taskly_project_payments')->where('workspace_id', $workspace->id)->where('customer_id', $customer->id)->latest()->get();

        return Inertia::render('Portal/Dashboard', [
            'portalRole' => 'client',
            'party' => $customer,
            'metrics' => [
                'outstanding' => (float) $customer->balance,
                'invoices' => $invoices->count(),
                'proposals' => $proposals->count(),
                'projects' => $projects->count(),
            ],
            'invoices' => $invoices,
            'proposals' => $proposals,
            'returns' => $returns,
            'payments' => $payments,
            'notes' => $creditNotes,
            'projects' => $projects,
            'projectPayments' => $projectPayments,
        ]);
    }

    private function vendorDashboard(Request $request, Workspace $workspace)
    {
        $vendor = AccountVendor::forWorkspace($workspace->organization_id, $workspace->id)->where('user_id', $request->user()->id)->firstOrFail();
        $invoices = DB::table('purchase_invoices')->where('organization_id', $workspace->organization_id)->where('workspace_id', $workspace->id)->where('vendor_id', $vendor->id)->latest()->get();
        $returns = DB::table('purchase_returns')->where('organization_id', $workspace->organization_id)->where('workspace_id', $workspace->id)->where('vendor_id', $vendor->id)->latest()->get();
        $payments = DB::table('vendor_payments')->where('organization_id', $workspace->organization_id)->where('workspace_id', $workspace->id)->where('vendor_id', $vendor->id)->latest()->get();
        $debitNotes = DB::table('account_debit_notes')->where('organization_id', $workspace->organization_id)->where('workspace_id', $workspace->id)->where('vendor_id', $vendor->id)->latest()->get();

        return Inertia::render('Portal/Dashboard', [
            'portalRole' => 'vendor',
            'party' => $vendor,
            'metrics' => ['outstanding' => (float) $vendor->balance, 'invoices' => $invoices->count(), 'returns' => $returns->count(), 'payments' => $payments->count()],
            'invoices' => $invoices,
            'returns' => $returns,
            'payments' => $payments,
            'notes' => $debitNotes,
            'proposals' => [],
            'projects' => [],
            'projectPayments' => [],
        ]);
    }

    private function customer(Request $request, Workspace $workspace): AccountCustomer
    {
        return AccountCustomer::forWorkspace($workspace->organization_id, $workspace->id)->where('user_id', $request->user()->id)->firstOrFail();
    }

    private function workspace(Request $request): Workspace
    {
        $workspace = Workspace::find($request->session()->get('active_workspace_id'));
        abort_unless($workspace && $request->user()->workspaces()->where('workspaces.id', $workspace->id)->exists(), 403);

        return $workspace;
    }
}
