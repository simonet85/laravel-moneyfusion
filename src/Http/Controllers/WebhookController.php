<?php

namespace Simonet85\LaravelMoneyFusion\Http\Controllers;

use Simonet85\LaravelMoneyFusion\MoneyFusionService;
use Simonet85\LaravelMoneyFusion\Models\MoneyFusionPayment;
use Simonet85\LaravelMoneyFusion\Events\PaymentStatusUpdated;
use Simonet85\LaravelMoneyFusion\Events\PaymentReceived;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;

class WebhookController extends Controller
{
    /**
     * Gérer les notifications MoneyFusion.
     *
     * Événements documentés : payin.session.pending, payin.session.completed,
     * payin.session.cancelled (les anciens payment.* restent acceptés).
     *
     * SÉCURITÉ : MoneyFusion ne signe pas ses webhooks. Le contenu reçu n'est donc
     * qu'un signal : seul `tokenPay` est utilisé, et le statut réel est relu auprès
     * de l'API (paiementNotif) avant toute modification.
     *
     * @endpoint POST /api/moneyfusion/webhook
     */
    public function handle(Request $request, MoneyFusionService $moneyFusion): JsonResponse
    {
        $tokenPay = $request->input('tokenPay');

        Log::info('MoneyFusion Webhook received', [
            'event' => $request->input('event'),
            'token' => is_string($tokenPay) ? $tokenPay : null,
            'ip' => $request->ip(),
        ]);

        if (!is_string($tokenPay) || $tokenPay === '') {
            return $this->errorResponse('Missing tokenPay', 400);
        }

        $payment = MoneyFusionPayment::where('token_pay', $tokenPay)->first();

        if (!$payment) {
            Log::warning('MoneyFusion Webhook: Payment not found in database', ['token' => $tokenPay]);

            return $this->errorResponse('Payment not found', 404);
        }

        // Un paiement déjà payé est définitif : notification en double, rien à faire.
        if ($payment->isPaid()) {
            return $this->successResponse('Payment already processed');
        }

        $oldStatus = $payment->statut;

        try {
            $verification = $moneyFusion->checkPaymentStatus($tokenPay);
        } catch (\Exception $e) {
            Log::error('MoneyFusion Webhook: verification failed', ['token' => $tokenPay, 'error' => $e->getMessage()]);

            return $this->errorResponse('Verification unavailable', 503);
        }

        if (!($verification['verified'] ?? false)) {
            Log::warning('MoneyFusion Webhook: could not verify with API, ignoring notification', ['token' => $tokenPay]);

            return $this->errorResponse('Verification unavailable', 503);
        }

        $data = $verification['data'] ?? [];

        $payment->refresh();

        $this->dispatchEvents($payment, $oldStatus, $data);

        return $this->successResponse('Webhook processed successfully');
    }

    /**
     * Déclenche événements et actions métier si le statut vérifié a changé.
     */
    protected function dispatchEvents(MoneyFusionPayment $payment, string $oldStatus, array $data): void
    {
        if ($oldStatus === $payment->statut) {
            return;
        }

        event(new PaymentStatusUpdated($payment, $oldStatus, $payment->statut));

        if ($payment->statut === 'paid') {
            event(new PaymentReceived($payment));
            $this->postPaymentSuccess($payment, $data);
        } elseif ($payment->statut === 'failed') {
            $this->postPaymentFailure($payment, $data);
        }
    }

    /**
     * Actions après un paiement réussi (point d'extension : surchargez cette méthode).
     */
    protected function postPaymentSuccess(MoneyFusionPayment $payment, array $data): void
    {
        if ($payment->order_id) {
            try {
                $this->updateOrderStatus($payment, 'paid');
            } catch (\Exception $e) {
                Log::error('Failed to update order after payment success', [
                    'payment_id' => $payment->id,
                    'order_id' => $payment->order_id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Actions après un paiement échoué (point d'extension : surchargez cette méthode).
     */
    protected function postPaymentFailure(MoneyFusionPayment $payment, array $data): void
    {
        if ($payment->order_id) {
            try {
                $this->updateOrderStatus($payment, 'payment_failed');
            } catch (\Exception $e) {
                Log::error('Failed to update order after payment failure', [
                    'payment_id' => $payment->id,
                    'order_id' => $payment->order_id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Mettre à jour le statut de la commande
     */
    protected function updateOrderStatus(MoneyFusionPayment $payment, string $status): void
    {
        if (!class_exists('App\Models\Order')) {
            Log::debug('Order model not found, skipping order update');
            return;
        }

        $order = $payment->order;

        if (!$order) {
            Log::warning('Order not found for payment', [
                'payment_id' => $payment->id,
                'order_id' => $payment->order_id,
            ]);
            return;
        }

        $order->update([
            'status' => $status,
            'paid_at' => $status === 'paid' ? now() : null,
        ]);
    }

    protected function successResponse(string $message): JsonResponse
    {
        return response()->json([
            'status' => 'ok',
            'message' => $message,
            'timestamp' => now()->toIso8601String(),
        ], 200);
    }

    protected function errorResponse(string $message, int $code = 400): JsonResponse
    {
        return response()->json([
            'status' => 'error',
            'message' => $message,
            'timestamp' => now()->toIso8601String(),
        ], $code);
    }
}
