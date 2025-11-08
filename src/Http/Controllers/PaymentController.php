<?php

namespace Simonet85\LaravelMoneyFusion\Http\Controllers;

use Simonet85\LaravelMoneyFusion\MoneyFusionService;
use Simonet85\LaravelMoneyFusion\Models\MoneyFusionPayment;
use Simonet85\LaravelMoneyFusion\Exceptions\MoneyFusionException;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;

class PaymentController extends Controller
{
    protected MoneyFusionService $moneyFusion;

    public function __construct(MoneyFusionService $moneyFusion)
    {
        $this->moneyFusion = $moneyFusion;
    }

    /**
     * Initier un paiement (API Endpoint)
     * 
     * @endpoint POST /api/moneyfusion/payments/initiate
     * @authenticated
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function initiatePayment(Request $request): JsonResponse
    {
        // Validation des données
        $validator = Validator::make($request->all(), [
            'total_price' => 'required|numeric|min:100',
            'articles' => 'required|array|min:1',
            'articles.*.name' => 'required|string|max:255',
            'articles.*.price' => 'required|numeric|min:0',
            'articles.*.quantity' => 'nullable|integer|min:1',
            'numero_send' => 'nullable|string|max:20',
            'nom_client' => 'required|string|max:255',
            'order_id' => 'nullable|integer',
            'return_url' => 'nullable|url',
            'webhook_url' => 'nullable|url',
        ], [
            'total_price.required' => 'Le montant total est requis',
            'total_price.min' => 'Le montant minimum est de 100 FCFA',
            'articles.required' => 'Au moins un article est requis',
            'articles.*.name.required' => 'Le nom de l\'article est requis',
            'nom_client.required' => 'Le nom du client est requis',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur de validation',
                'errors' => $validator->errors()
            ], 422);
        }

        $validated = $validator->validated();

        try {
            // Ajouter l'utilisateur connecté si disponible
            if (Auth::check()) {
                $validated['user_id'] = Auth::id();
            }

            // Logger la tentative de création
            Log::info('Initiating MoneyFusion payment', [
                'user_id' => $validated['user_id'] ?? null,
                'amount' => $validated['total_price'],
                'client' => $validated['nom_client'],
            ]);

            // Créer le paiement via le service
            $result = $this->moneyFusion->createPayment($validated);

            // Vérifier le succès
            if (!($result['statut'] ?? false)) {
                throw new MoneyFusionException(
                    $result['message'] ?? 'Échec de création du paiement'
                );
            }

            // Logger le succès
            Log::info('MoneyFusion payment created successfully', [
                'token' => $result['token'],
                'url' => $result['url'],
            ]);

            return response()->json([
                'success' => true,
                'token' => $result['token'],
                'payment_url' => $result['url'],
                'message' => $result['message'] ?? 'Paiement créé avec succès',
                'data' => [
                    'token' => $result['token'],
                    'url' => $result['url'],
                    'amount' => $validated['total_price'],
                    'client' => $validated['nom_client'],
                ]
            ], 201);

        } catch (MoneyFusionException $e) {
            Log::error('MoneyFusion payment creation failed', [
                'error' => $e->getMessage(),
                'data' => $validated,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la création du paiement',
                'error' => $e->getMessage(),
            ], 500);

        } catch (\Exception $e) {
            Log::error('Unexpected error during payment creation', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Une erreur inattendue s\'est produite',
                'error' => config('app.debug') ? $e->getMessage() : 'Erreur serveur',
            ], 500);
        }
    }

    /**
     * Vérifier le statut d'un paiement (API Endpoint)
     * 
     * @endpoint GET /api/moneyfusion/payments/{token}/status
     * @authenticated
     * 
     * @param string $token
     * @return JsonResponse
     */
    public function checkStatus(string $token): JsonResponse
    {
        try {
            // Vérifier que le token existe localement
            $localPayment = MoneyFusionPayment::where('token_pay', $token)->first();

            if (!$localPayment) {
                return response()->json([
                    'success' => false,
                    'message' => 'Paiement introuvable',
                ], 404);
            }

            // Vérifier les permissions (si utilisateur connecté)
            if (Auth::check() && $localPayment->user_id && $localPayment->user_id !== Auth::id()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Accès non autorisé',
                ], 403);
            }

            // Récupérer le statut depuis MoneyFusion
            $result = $this->moneyFusion->checkPaymentStatus($token);

            return response()->json([
                'success' => true,
                'data' => [
                    'token' => $token,
                    'statut' => $localPayment->fresh()->statut,
                    'montant' => $localPayment->montant,
                    'nom_client' => $localPayment->nom_client,
                    'numero_transaction' => $localPayment->numero_transaction,
                    'moyen' => $localPayment->moyen,
                    'paid_at' => $localPayment->paid_at?->format('Y-m-d H:i:s'),
                    'created_at' => $localPayment->created_at->format('Y-m-d H:i:s'),
                    'is_paid' => $localPayment->isPaid(),
                    'is_pending' => $localPayment->isPending(),
                    'is_failed' => $localPayment->isFailed(),
                ],
                'api_response' => $result,
            ]);

        } catch (MoneyFusionException $e) {
            Log::error('MoneyFusion status check failed', [
                'token' => $token,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la vérification du statut',
                'error' => $e->getMessage(),
            ], 500);

        } catch (\Exception $e) {
            Log::error('Unexpected error during status check', [
                'token' => $token,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Une erreur inattendue s\'est produite',
            ], 500);
        }
    }

    /**
     * Page de callback après paiement
     * 
     * @endpoint GET /payment/callback
     * 
     * @param Request $request
     * @return RedirectResponse
     */
    public function callback(Request $request): RedirectResponse
    {
        $token = $request->query('token') ?? $request->query('tokenPay');

        // Vérifier la présence du token
        if (!$token) {
            Log::warning('Payment callback called without token', [
                'url' => $request->fullUrl(),
                'ip' => $request->ip(),
            ]);

            return redirect()->route('home')
                ->with('error', 'Token de paiement manquant');
        }

        try {
            // Vérifier le statut du paiement via l'API
            Log::info('Payment callback received', [
                'token' => $token,
                'ip' => $request->ip(),
            ]);

            // Vérifier le statut auprès de MoneyFusion
            $this->moneyFusion->checkPaymentStatus($token);

            // Récupérer le paiement depuis la base de données
            $payment = MoneyFusionPayment::where('token_pay', $token)->first();

            if (!$payment) {
                Log::error('Payment not found in database during callback', [
                    'token' => $token,
                ]);

                return redirect()->route('home')
                    ->with('error', 'Paiement introuvable');
            }

            // Rediriger selon le statut
            if ($payment->isPaid()) {
                Log::info('Payment callback - redirecting to success', [
                    'token' => $token,
                    'amount' => $payment->montant,
                ]);

                return redirect()->route('payment.success', ['token' => $token])
                    ->with('success', 'Paiement effectué avec succès');
            }

            if ($payment->isFailed()) {
                Log::info('Payment callback - redirecting to failed', [
                    'token' => $token,
                ]);

                return redirect()->route('payment.failed')
                    ->with('error', 'Le paiement a échoué');
            }

            // Si toujours en attente
            Log::info('Payment callback - redirecting to pending', [
                'token' => $token,
            ]);

            return redirect()->route('payment.pending', ['token' => $token])
                ->with('info', 'Paiement en cours de traitement');

        } catch (\Exception $e) {
            Log::error('Error during payment callback', [
                'token' => $token,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return redirect()->route('payment.failed')
                ->with('error', 'Erreur lors de la vérification du paiement');
        }
    }

    /**
     * Afficher la page de succès
     * 
     * @endpoint GET /payment/success/{token}
     * 
     * @param string $token
     * @return View|RedirectResponse
     */
    public function success(string $token): View|RedirectResponse
    {
        try {
            $payment = MoneyFusionPayment::where('token_pay', $token)->first();

            if (!$payment) {
                Log::warning('Success page accessed with invalid token', [
                    'token' => $token,
                ]);

                return redirect()->route('home')
                    ->with('error', 'Paiement introuvable');
            }

            // Vérifier les permissions
            if (Auth::check() && $payment->user_id && $payment->user_id !== Auth::id()) {
                Log::warning('Unauthorized access to success page', [
                    'token' => $token,
                    'user_id' => Auth::id(),
                    'payment_user_id' => $payment->user_id,
                ]);

                abort(403, 'Accès non autorisé');
            }

            // Si le paiement n'est pas encore payé, rediriger vers pending
            if (!$payment->isPaid()) {
                return redirect()->route('payment.pending', ['token' => $token]);
            }

            Log::info('Success page displayed', [
                'token' => $token,
                'amount' => $payment->montant,
            ]);

            return view('moneyfusion::success', [
                'payment' => $payment,
                'user' => $payment->user,
            ]);

        } catch (\Exception $e) {
            Log::error('Error displaying success page', [
                'token' => $token,
                'error' => $e->getMessage(),
            ]);

            return redirect()->route('home')
                ->with('error', 'Une erreur s\'est produite');
        }
    }

    /**
     * Afficher la page d'échec
     * 
     * @endpoint GET /payment/failed
     * 
     * @return View
     */
    public function failed(): View
    {
        Log::info('Failed page displayed', [
            'ip' => request()->ip(),
        ]);

        return view('moneyfusion::failed');
    }

    /**
     * Afficher la page en attente
     * 
     * @endpoint GET /payment/pending/{token}
     * 
     * @param string $token
     * @return View|RedirectResponse
     */
    public function pending(string $token): View|RedirectResponse
    {
        try {
            $payment = MoneyFusionPayment::where('token_pay', $token)->first();

            if (!$payment) {
                return redirect()->route('home')
                    ->with('error', 'Paiement introuvable');
            }

            // Vérifier les permissions
            if (Auth::check() && $payment->user_id && $payment->user_id !== Auth::id()) {
                abort(403, 'Accès non autorisé');
            }

            // Si le paiement est déjà payé, rediriger vers success
            if ($payment->isPaid()) {
                return redirect()->route('payment.success', ['token' => $token]);
            }

            // Si le paiement a échoué, rediriger vers failed
            if ($payment->isFailed()) {
                return redirect()->route('payment.failed');
            }

            Log::info('Pending page displayed', [
                'token' => $token,
            ]);

            return view('moneyfusion::pending', [
                'payment' => $payment,
            ]);

        } catch (\Exception $e) {
            Log::error('Error displaying pending page', [
                'token' => $token,
                'error' => $e->getMessage(),
            ]);

            return redirect()->route('home')
                ->with('error', 'Une erreur s\'est produite');
        }
    }

    /**
     * Annuler un paiement (optionnel)
     * 
     * @endpoint POST /api/moneyfusion/payments/{token}/cancel
     * @authenticated
     * 
     * @param string $token
     * @return JsonResponse
     */
    public function cancel(string $token): JsonResponse
    {
        try {
            $payment = MoneyFusionPayment::where('token_pay', $token)->first();

            if (!$payment) {
                return response()->json([
                    'success' => false,
                    'message' => 'Paiement introuvable',
                ], 404);
            }

            // Vérifier les permissions
            if (Auth::check() && $payment->user_id && $payment->user_id !== Auth::id()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Accès non autorisé',
                ], 403);
            }

            // On ne peut annuler que les paiements en attente
            if (!$payment->isPending()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ce paiement ne peut pas être annulé',
                ], 400);
            }

            // Marquer comme annulé
            $payment->markAsCancelled();

            Log::info('Payment cancelled', [
                'token' => $token,
                'user_id' => Auth::id(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Paiement annulé avec succès',
                'data' => [
                    'token' => $token,
                    'statut' => $payment->statut,
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Error cancelling payment', [
                'token' => $token,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de l\'annulation',
            ], 500);
        }
    }
}