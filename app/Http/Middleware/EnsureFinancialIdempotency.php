<?php

namespace App\Http\Middleware;

use App\Domain\Accounting\Money;
use App\Models\CustomerPayment;
use App\Models\VendorPayment;
use Closure;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Serializes financial submissions by idempotency key and rejects key reuse
 * with a different normalized payload before controller business logic runs.
 *
 * This middleware is intentionally narrow: it only handles accounting payment
 * settlement endpoints. Other financial endpoints should opt in when they
 * expose an equivalent idempotency contract.
 */
class EnsureFinancialIdempotency
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->isMethod('POST')) {
            return $next($request);
        }

        $type = match (true) {
            $request->is('accounting/customer-payments') => 'customer',
            $request->is('accounting/vendor-payments') => 'vendor',
            default => null,
        };

        if ($type === null) {
            return $next($request);
        }

        // Let the controller's normal Laravel validator handle malformed input.
        // Fingerprinting only begins once the minimum deterministic fields exist.
        if (! $this->hasFingerprintablePayload($request)) {
            return $next($request);
        }

        $workspaceId = (int) $request->session()->get('active_workspace_id', 0);
        $organizationId = (int) $request->session()->get('active_organization_id', 0);

        abort_if($workspaceId <= 0 || $organizationId <= 0, 409, 'Active financial workspace context is required.');

        $key = (string) ($request->input('idempotency_key') ?: $request->header('Idempotency-Key', ''));

        if ($key === '') {
            if ($request->expectsJson()) {
                abort(422, 'Idempotency-Key is required for financial API submissions.');
            }

            // The shipped React forms provide a stable operation UUID. This is a
            // defensive fallback for non-JS browser submissions.
            $key = (string) Str::uuid();
            $request->merge(['idempotency_key' => $key]);
        }

        abort_if(strlen($key) > 64, 422, 'Idempotency key may not exceed 64 characters.');

        $fingerprint = $this->fingerprint($request, $type);
        $lockName = sprintf('financial-idempotency:%d:%s:%s', $workspaceId, $type, hash('sha256', $key));

        try {
            return Cache::lock($lockName, 15)->block(5, function () use ($request, $next, $type, $workspaceId, $organizationId, $key, $fingerprint) {
                $existing = $type === 'customer'
                    ? CustomerPayment::forWorkspace($organizationId, $workspaceId)->where('idempotency_key', $key)->first()
                    : VendorPayment::forWorkspace($organizationId, $workspaceId)->where('idempotency_key', $key)->first();

                if ($existing) {
                    if (is_string($existing->request_fingerprint) && hash_equals($existing->request_fingerprint, $fingerprint)) {
                        return back()->with('success', $type === 'customer' ? 'Customer payment recorded.' : 'Vendor payment recorded.');
                    }

                    abort(409, 'Duplicate payment: this idempotency key has already been used with different parameters.');
                }

                return $next($request);
            });
        } catch (LockTimeoutException) {
            abort(409, 'A payment with this idempotency key is already being processed. Retry the same request shortly.');
        }
    }

    private function hasFingerprintablePayload(Request $request): bool
    {
        return is_numeric($request->input('amount'))
            && filled($request->input('payment_date'))
            && filled($request->input('payment_method'));
    }

    private function fingerprint(Request $request, string $type): string
    {
        $payload = $type === 'customer'
            ? [
                'customer_id' => $request->input('customer_id') ?: null,
                'invoice_id' => $request->input('invoice_id') ?: null,
                'account_id' => $request->input('account_id') ?: null,
                'amount' => Money::of((string) $request->input('amount'))->toStorageString(),
                'payment_date' => (string) $request->input('payment_date'),
                'payment_method' => (string) $request->input('payment_method'),
            ]
            : [
                'vendor_id' => $request->input('vendor_id') ?: null,
                'purchase_invoice_id' => $request->input('purchase_invoice_id') ?: null,
                'account_id' => $request->input('account_id') ?: null,
                'amount' => Money::of((string) $request->input('amount'))->toStorageString(),
                'payment_date' => (string) $request->input('payment_date'),
                'payment_method' => (string) $request->input('payment_method'),
            ];

        return hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR));
    }
}
