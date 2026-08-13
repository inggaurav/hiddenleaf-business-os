<?php

namespace HiddenLeaf\Http\Controllers\Api\V1;

use HiddenLeaf\Domain\Licensing\Services\LicenseActivationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LicensingController
{
    public function __construct(private LicenseActivationService $licenses) {}

    public function activate(Request $request): JsonResponse
    {
        $result = $this->licenses->activate($request->validate([
            'license_key' => ['required', 'string', 'max:255'],
            'domain' => ['required', 'string', 'max:253'],
            'product_alias' => ['required', 'string', 'max:100'],
            'installation_id' => ['required', 'uuid'],
            'metadata' => ['nullable', 'array'],
        ]), $request);

        return response()->json($this->body($result), $result['status_code']);
    }

    public function deactivate(Request $request): JsonResponse
    {
        $result = $this->licenses->deactivate($request->validate([
            'license_key' => ['required', 'string', 'max:255'],
            'domain' => ['required', 'string', 'max:253'],
            'installation_id' => ['required', 'uuid'],
        ]), $request);

        return response()->json($this->body($result), $result['status_code']);
    }

    public function validateLicense(Request $request): JsonResponse
    {
        $token = $request->input('token') ?? $request->bearerToken();
        if (! $token) {
            return response()->json(['valid' => false, 'error' => 'No token provided'], 400);
        }

        $result = $this->licenses->validateOnline($token, $request->input('domain'), $request);

        return response()->json($this->body($result), $result['status_code']);
    }

    public function entitlements(Request $request): JsonResponse
    {
        $token = $request->bearerToken();
        if (! $token) {
            return response()->json(['error' => 'No token provided'], 400);
        }

        $result = $this->licenses->validateOnline($token, $request->input('domain'), $request);
        if (! $result['ok']) {
            return response()->json($this->body($result), $result['status_code']);
        }

        return response()->json(['entitlements' => $result['payload']['entitlements'] ?? []]);
    }

    private function body(array $result): array
    {
        unset($result['status_code'], $result['ok']);

        return $result;
    }
}
