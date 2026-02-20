<?php

namespace App\Http\Controllers\Webhook;

use App\Http\Controllers\Controller;
use App\Services\WebhookService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * F-151: Payment Webhook Handling
 *
 * Receives and processes Flutterwave webhook callbacks.
 * This endpoint is excluded from CSRF verification since it receives
 * external POST requests from Flutterwave's servers.
 *
 * BR-364: Verifies webhook signature for authenticity
 * BR-373: Invalid signatures result in 401 rejection with logging
 * BR-375: Returns 200 OK promptly to prevent Flutterwave retries
 */
class FlutterwaveWebhookController extends Controller
{
    public function __construct(
        private WebhookService $webhookService,
    ) {}

    /**
     * Handle incoming Flutterwave webhook.
     *
     * BR-364: Verify the Flutterwave webhook signature/hash
     * BR-373: Invalid webhook signatures result in 401 rejection with logging
     * BR-375: Return 200 OK promptly to prevent Flutterwave retries
     */
    public function handle(Request $request): JsonResponse
    {
        // BR-364: Verify webhook signature
        $signature = $request->header('verif-hash');

        if (! $this->webhookService->verifySignature($signature)) {
            // BR-373: Log invalid signature attempt
            Log::warning('Flutterwave webhook: invalid signature', [
                'ip' => $request->ip(),
                'signature_present' => ! empty($signature),
                'user_agent' => $request->userAgent(),
            ]);

            activity('webhooks')
                ->withProperties([
                    'ip' => $request->ip(),
                    'signature_present' => ! empty($signature),
                ])
                ->log('Webhook rejected: invalid signature');

            return response()->json(['status' => 'error', 'message' => 'Invalid signature'], 401);
        }

        // Process the webhook payload
        $payload = $request->all();

        $result = $this->webhookService->processWebhook($payload);

        // BR-375: Always return 200 OK for valid signatures to prevent retries
        // (except malformed payloads that return success: false — still 200)
        return response()->json([
            'status' => 'ok',
            'message' => $result['message'],
        ], 200);
    }
}
