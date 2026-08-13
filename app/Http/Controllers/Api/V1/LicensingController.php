<?php

namespace HiddenLeaf\Http\Controllers\Api\V1;

use HiddenLeaf\Domain\Licensing\Services\LicenseManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LicensingController
{
    protected LicenseManager $licenseManager;

    public function __construct(LicenseManager $licenseManager)
    {
        $this->licenseManager = $licenseManager;
    }

    public function activate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'license_key' => 'required|string',
            'domain' => 'required|string',
            'product_alias' => 'required|string',
        ]);

        $payload = [
            'license_key' => $validated['license_key'],
            'domain' => $validated['domain'],
            'product_alias' => $validated['product_alias'],
            'type' => 'SAAS',
            'entitlements' => ['core', 'multi_workspace', 'saas_billing'],
            'exp' => time() + (365 * 86400),
        ];

        $token = $this->licenseManager->createSignedToken($payload);

        return response()->json([
            'status' => 'activated',
            'message' => 'License successfully activated for domain: ' . $validated['domain'],
            'signed_token' => $token,
            'entitlements' => $payload['entitlements'],
        ]);
    }

    public function deactivate(Request $request): JsonResponse
    {
        $request->validate(['license_key' => 'required|string']);

        return response()->json([
            'status' => 'deactivated',
            'message' => 'License successfully deactivated.',
        ]);
    }

    public function validateLicense(Request $request): JsonResponse
    {
        $token = $request->input('token') ?? $request->bearerToken();
        if (!$token) {
            return response()->json(['valid' => false, 'error' => 'No token provided'], 400);
        }

        $result = $this->licenseManager->verifySignedToken($token);

        return response()->json($result);
    }

    public function entitlements(Request $request): JsonResponse
    {
        return response()->json([
            'modules' => ['core', 'multi_workspace', 'saas_billing', 'whitelabel'],
            'limits' => [
                'max_users' => -1,
                'max_workspaces' => -1,
                'storage_mb' => 102400,
            ]
        ]);
    }
}
