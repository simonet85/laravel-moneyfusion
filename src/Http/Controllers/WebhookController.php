<?php

namespace Simonet85\LaravelMoneyFusion\Http\Controllers;

use Simonet85\LaravelMoneyFusion\Models\MoneyFusionPayment;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class WebhookController extends Controller
{
    /**
     * Gérer les webhooks MoneyFusion
     * 
     * @endpoint POST /api/moneyfusion/webhook
     * @no-auth (MoneyFusion ne peut pas s'authentifier)
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function handle(Request $request): JsonResponse
    {
        // Logger TOUT ce qui est reçu
        Log::info('MoneyFusion Webhook received', [
            'method' => $request->method(),
            'data' => $request->all(),
            'headers' => $request->headers->all(),
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'url' => $request->fullUrl(),
        ]);

        try {
            $data = $request->all();

            // Validation des données requises
            if (!isset($data['tokenPay'])) {
                Log::warning('MoneyFusion Webhook: Missing tokenPay', [
                    'data' => $data,
                ]);

                return $this->errorResponse('Missing tokenPay', 400);
            }

            $tokenPay = $data['tokenPay'];
            $event = $data['event'] ?? 'payment.update';

            // Chercher le paiement dans la base de données
            $payment = MoneyFusionPayment::where('token_pay', $tokenPay)->first();

            if (!$payment) {
                Log::warning('MoneyFusion Webhook: Payment not found in database', [
                    'token' => $tokenPay,
                    'event' => $event,
                    'data' => $data,
                ]);

                return $this->errorResponse('Payment not found', 404);
            }

            // IMPORTANT: Prévention des duplications
            // Si le paiement est déjà payé et on reçoit un événement success
            if ($payment->isPaid() && $event === 'payment.success') {
                Log::info('MoneyFusion Webhook: Payment already processed (duplicate)', [
                    'token' => $tokenPay,
                    'current_status' => $payment->statut,
                    'paid_at' => $payment->paid_at,
                ]);

                return $this->successResponse('Payment already processed');
            }

            // Traiter selon l'événement
            $this->processWebhookEvent($payment, $event, $data);

            return $this->successResponse('Webhook processed successfully');

        } catch (\Exception $e) {
            Log::error('MoneyFusion Webhook processing error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'data' => $request->all(),
            ]);

            return $this->errorResponse('Internal server error', 500);
        }
    }

    /**
     * Traiter l'événement webhook selon le type
     * 
     * @param MoneyFusionPayment $payment
     * @param string $event
     * @param array $data
     * @return void
     */
    protected function processWebhookEvent(MoneyFusionPayment $payment, string $event, array $data): void
    {
        switch ($event) {
            case 'payment.success':
                $this->handlePaymentSuccess($payment, $data);
                break;

            case 'payment.failed':
                $this->handlePaymentFailed($payment, $data);
                break;

            case 'payment.pending':
                $this->handlePaymentPending($payment, $data);
                break;

            case 'payment.cancelled':
                $this->handlePaymentCancelled($payment, $data);
                break;

            default:
                Log::info('MoneyFusion Webhook: Unknown event type', [
                    'event' => $event,
                    'token' => $payment->token_pay,
                    'data' => $data,
                ]);
                $this->handleUnknownEvent($payment, $data);
        }
    }

    /**
     * Traiter un paiement réussi
     * 
     * @param MoneyFusionPayment $payment
     * @param array $data
     * @return void
     */
    protected function handlePaymentSuccess(MoneyFusionPayment $payment, array $data): void
    {
        Log::info('MoneyFusion Webhook: Processing payment success', [
            'token' => $payment->token_pay,
            'old_status' => $payment->statut,
            'amount' => $payment->montant,
            'transaction' => $data['numeroTransaction'] ?? null,
        ]);

        // Utiliser une transaction DB pour garantir l'intégrité
        DB::transaction(function () use ($payment, $data) {
            // Marquer comme payé
            $payment->markAsPaid([
                'numeroTransaction' => $data['numeroTransaction'] ?? null,
                'moyen' => $data['moyen'] ?? null,
                'frais' => $data['frais'] ?? 0,
            ]);

            // Mettre à jour les données brutes
            $payment->update([
                'raw_response' => array_merge($payment->raw_response ?? [], $data),
            ]);
        });

        // Logique métier après paiement réussi
        $this->postPaymentSuccess($payment, $data);

        Log::info('MoneyFusion Webhook: Payment success processed', [
            'token' => $payment->token_pay,
            'new_status' => $payment->fresh()->statut,
            'paid_at' => $payment->fresh()->paid_at,
        ]);
    }

    /**
     * Traiter un paiement échoué
     * 
     * @param MoneyFusionPayment $payment
     * @param array $data
     * @return void
     */
    protected function handlePaymentFailed(MoneyFusionPayment $payment, array $data): void
    {
        Log::info('MoneyFusion Webhook: Processing payment failure', [
            'token' => $payment->token_pay,
            'old_status' => $payment->statut,
            'reason' => $data['reason'] ?? 'Unknown',
        ]);

        // Marquer comme échoué
        $payment->markAsFailed();

        // Mettre à jour les données brutes
        $payment->update([
            'raw_response' => array_merge($payment->raw_response ?? [], $data),
        ]);

        // Logique métier après échec
        $this->postPaymentFailure($payment, $data);

        Log::info('MoneyFusion Webhook: Payment failure processed', [
            'token' => $payment->token_pay,
            'new_status' => $payment->fresh()->statut,
        ]);
    }

    /**
     * Traiter un paiement en attente
     * 
     * @param MoneyFusionPayment $payment
     * @param array $data
     * @return void
     */
    protected function handlePaymentPending(MoneyFusionPayment $payment, array $data): void
    {
        Log::info('MoneyFusion Webhook: Payment still pending', [
            'token' => $payment->token_pay,
            'status' => $payment->statut,
        ]);

        // Mettre à jour les données brutes
        $payment->update([
            'raw_response' => array_merge($payment->raw_response ?? [], $data),
        ]);
    }

    /**
     * Traiter un paiement annulé
     * 
     * @param MoneyFusionPayment $payment
     * @param array $data
     * @return void
     */
    protected function handlePaymentCancelled(MoneyFusionPayment $payment, array $data): void
    {
        Log::info('MoneyFusion Webhook: Processing payment cancellation', [
            'token' => $payment->token_pay,
            'old_status' => $payment->statut,
        ]);

        $payment->markAsCancelled();

        $payment->update([
            'raw_response' => array_merge($payment->raw_response ?? [], $data),
        ]);

        Log::info('MoneyFusion Webhook: Payment cancellation processed', [
            'token' => $payment->token_pay,
        ]);
    }

    /**
     * Traiter un événement inconnu
     * 
     * @param MoneyFusionPayment $payment
     * @param array $data
     * @return void
     */
    protected function handleUnknownEvent(MoneyFusionPayment $payment, array $data): void
    {
        // Juste mettre à jour les données brutes pour référence
        $payment->update([
            'raw_response' => array_merge($payment->raw_response ?? [], [
                'unknown_event' => $data,
                'received_at' => now()->toDateTimeString(),
            ]),
        ]);
    }

    /**
     * Actions après un paiement réussi
     * 
     * @param MoneyFusionPayment $payment
     * @param array $data
     * @return void
     */
    protected function postPaymentSuccess(MoneyFusionPayment $payment, array $data): void
    {
        // 1. Mettre à jour la commande associée
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

        // 2. Envoyer des notifications (si implémenté)
        // if ($payment->user) {
        //     try {
        //         $payment->user->notify(new PaymentSuccessNotification($payment));
        //     } catch (\Exception $e) {
        //         Log::error('Failed to send payment success notification', [
        //             'payment_id' => $payment->id,
        //             'error' => $e->getMessage(),
        //         ]);
        //     }
        // }

        // 3. Déclencher un événement Laravel (si implémenté)
        // try {
        //     event(new PaymentSuccessful($payment));
        // } catch (\Exception $e) {
        //     Log::error('Failed to dispatch PaymentSuccessful event', [
        //         'payment_id' => $payment->id,
        //         'error' => $e->getMessage(),
        //     ]);
        // }

        // 4. Envoyer un email de confirmation (si implémenté)
        // if ($payment->user && $payment->user->email) {
        //     try {
        //         Mail::to($payment->user->email)->send(new PaymentReceivedMail($payment));
        //     } catch (\Exception $e) {
        //         Log::error('Failed to send payment confirmation email', [
        //             'payment_id' => $payment->id,
        //             'error' => $e->getMessage(),
        //         ]);
        //     }
        // }

        Log::info('Post-payment success actions completed', [
            'payment_id' => $payment->id,
            'token' => $payment->token_pay,
        ]);
    }

    /**
     * Actions après un paiement échoué
     * 
     * @param MoneyFusionPayment $payment
     * @param array $data
     * @return void
     */
    protected function postPaymentFailure(MoneyFusionPayment $payment, array $data): void
    {
        // 1. Mettre à jour la commande associée
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

        // 2. Envoyer une notification d'échec (si implémenté)
        // if ($payment->user) {
        //     try {
        //         $payment->user->notify(new PaymentFailedNotification($payment));
        //     } catch (\Exception $e) {
        //         Log::error('Failed to send payment failure notification', [
        //             'payment_id' => $payment->id,
        //             'error' => $e->getMessage(),
        //         ]);
        //     }
        // }

        Log::info('Post-payment failure actions completed', [
            'payment_id' => $payment->id,
            'token' => $payment->token_pay,
        ]);
    }

    /**
     * Mettre à jour le statut de la commande
     * 
     * @param MoneyFusionPayment $payment
     * @param string $status
     * @return void
     */
    protected function updateOrderStatus(MoneyFusionPayment $payment, string $status): void
    {
        // Vérifier si la classe Order existe
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

        Log::info('Order status updated', [
            'order_id' => $order->id,
            'new_status' => $status,
        ]);
    }

    /**
     * Réponse de succès standardisée
     * 
     * @param string $message
     * @return JsonResponse
     */
    protected function successResponse(string $message): JsonResponse
    {
        return response()->json([
            'status' => 'ok',
            'message' => $message,
            'timestamp' => now()->toIso8601String(),
        ], 200);
    }

    /**
     * Réponse d'erreur standardisée
     * 
     * @param string $message
     * @param int $code
     * @return JsonResponse
     */
    protected function errorResponse(string $message, int $code = 400): JsonResponse
    {
        return response()->json([
            'status' => 'error',
            'message' => $message,
            'timestamp' => now()->toIso8601String(),
        ], $code);
    }
}