<?php

namespace App\Http\Controllers\Communications;

use App\Domain\Communications\Webhooks\CommunicationWebhookService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class CommunicationWebhookController extends Controller
{
    public function __construct(private CommunicationWebhookService $webhookService) {}

    public function handle(Request $request, string $provider): JsonResponse|Response
    {
        // WhatsApp Hub Challenge Verification (GET request from Meta)
        if ($provider === 'whatsapp' && $request->isMethod('get')) {
            $mode = $request->query('hub_mode');
            $token = $request->query('hub_verify_token');
            $challenge = $request->query('hub_challenge');

            $expectedToken = config('services.whatsapp.verify_token', 'hiddenleaf_wa_verify');
            if ($mode === 'subscribe' && $token === $expectedToken) {
                return response($challenge, 200)->header('Content-Type', 'text/plain');
            }

            return response('Forbidden', 403);
        }

        // Limit payload size to 2MB
        $payload = $request->getContent();
        if (strlen($payload) > 2 * 1024 * 1024) {
            return response()->json(['error' => 'Payload too large'], 413);
        }

        $headers = $request->headers->all();
        $result = $this->webhookService->handleWebhook($provider, $headers, $payload);

        if (! $result['success']) {
            return response()->json(['error' => $result['message']], 400);
        }

        return response()->json(['status' => 'ok', 'processed' => $result['processed_count']]);
    }
}
