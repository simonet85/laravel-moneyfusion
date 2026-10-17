<?php

namespace Simonet85\LaravelMoneyFusion;

use Simonet85\LaravelMoneyFusion\Models\MoneyFusionPayment;
use Simonet85\LaravelMoneyFusion\Exceptions\MoneyFusionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MoneyFusionService
{
    /**
     * Statuts renvoyés par l'API MoneyFusion (`data.statut`) et leur équivalent local.
     * Documentation : pending, paid, failure, "no paid".
     */
    public const STATUS_MAP = [
        'pending' => 'pending',
        'paid' => 'paid',
        'failure' => 'failed',
        'failed' => 'failed',
        'no paid' => 'cancelled',
        'cancelled' => 'cancelled',
    ];

    public const DEFAULT_CHECK_PAYMENT_URL = 'https://pay.moneyfusion.net/paiementNotif';

    protected string $apiUrl;
    protected string $appKey;
    protected int $timeout;
    protected bool $verifySSL;
    protected ?string $checkPaymentUrl;

    public function __construct()
    {
        $this->apiUrl = (string) config('moneyfusion.api_url');
        $this->appKey = (string) config('moneyfusion.app_key');
        $this->timeout = config('moneyfusion.timeout', 30);
        $this->verifySSL = config('moneyfusion.verify_ssl', true);
        $this->checkPaymentUrl = config('moneyfusion.check_payment_url');

        if (empty($this->apiUrl) || empty($this->appKey)) {
            throw new MoneyFusionException('MoneyFusion configuration is missing.');
        }
    }

    /**
     * Convertit un statut API en statut local (pending, paid, failed, cancelled).
     * Un statut inconnu est traité comme "pending" : il ne doit jamais créditer ni échouer un paiement.
     */
    public static function normalizeStatus(?string $status): string
    {
        return self::STATUS_MAP[strtolower(trim((string) $status))] ?? 'pending';
    }

    /**
     * Créer un paiement
     */
    public function createPayment(array $data): array
    {
        try {
            $payload = $this->preparePayload($data);

            Log::info('MoneyFusion: Creating payment', ['payload' => $payload]);

            $response = Http::timeout($this->timeout)
                ->withOptions(['verify' => $this->verifySSL])
                ->post($this->apiUrl, $payload);

            if (!$response->successful()) {
                throw new MoneyFusionException('API error: ' . $response->body());
            }

            $result = $response->json();

            if (!($result['statut'] ?? false)) {
                throw new MoneyFusionException('Payment creation failed: ' . ($result['message'] ?? 'Unknown error'));
            }

            if (empty($result['token']) || empty($result['url'])) {
                throw new MoneyFusionException('Payment creation failed: token or url missing in API response');
            }

            // Sauvegarder en base
            $this->storePayment($data, $result);

            return $result;

        } catch (\Exception $e) {
            Log::error('MoneyFusion: Payment creation failed', [
                'error' => $e->getMessage(),
                'data' => $data
            ]);
            throw new MoneyFusionException($e->getMessage(), 0, $e);
        }
    }

    /**
     * Vérifier le statut d'un paiement auprès de MoneyFusion.
     *
     * La réponse contient `verified: true` uniquement si elle provient réellement de l'API.
     * En cas d'indisponibilité, la base locale sert de repli (`verified: false`) : ces données
     * ne doivent JAMAIS servir de preuve de paiement (elles peuvent venir d'un webhook non authentifié).
     */
    public function checkPaymentStatus(string $tokenPay): array
    {
        try {
            $url = $this->checkPaymentEndpoint($tokenPay);

            Log::info('MoneyFusion: Checking payment status', [
                'url' => $url,
                'token' => $tokenPay
            ]);

            $response = Http::timeout($this->timeout)
                ->withOptions(['verify' => $this->verifySSL])
                ->get($url);

            if (!$response->successful()) {
                Log::warning('MoneyFusion: Check payment API returned error', [
                    'status' => $response->status(),
                    'body' => $response->body()
                ]);

                $fallback = $this->localFallback($tokenPay, 'local_database', 'API vérification indisponible. Données de la base locale.');

                if ($fallback) {
                    return $fallback;
                }

                throw new MoneyFusionException('API error: ' . $response->body());
            }

            $result = $response->json();

            if (!is_array($result) || !isset($result['data']) || !is_array($result['data'])) {
                throw new MoneyFusionException('Unexpected check-payment response');
            }

            $this->updatePaymentStatus($tokenPay, $result['data']);

            $result['verified'] = true;

            return $result;

        } catch (\Exception $e) {
            Log::error('MoneyFusion: Status check failed', [
                'error' => $e->getMessage(),
                'token' => $tokenPay
            ]);

            $fallback = $this->localFallback($tokenPay, 'local_database_exception', 'Erreur API. Données de la base locale.');

            if ($fallback) {
                return $fallback;
            }

            throw new MoneyFusionException($e->getMessage(), 0, $e);
        }
    }

    /**
     * Obtenir un paiement par token
     */
    public function getPaymentByToken(string $tokenPay): ?MoneyFusionPayment
    {
        return MoneyFusionPayment::where('token_pay', $tokenPay)->first();
    }

    /**
     * URL de vérification du statut d'un paiement.
     */
    protected function checkPaymentEndpoint(string $tokenPay): string
    {
        $base = $this->checkPaymentUrl ?: self::DEFAULT_CHECK_PAYMENT_URL;

        return rtrim($base, '/') . '/' . rawurlencode($tokenPay);
    }

    /**
     * Réponse de repli construite depuis la base locale (jamais vérifiée).
     */
    protected function localFallback(string $tokenPay, string $source, string $message): ?array
    {
        $payment = $this->getPaymentByToken($tokenPay);

        if (!$payment) {
            return null;
        }

        Log::info('MoneyFusion: Using local database fallback', [
            'token' => $tokenPay,
            'status' => $payment->statut,
        ]);

        return [
            'statut' => true,
            'verified' => false,
            'data' => [
                'statut' => $payment->statut,
                'Montant' => $payment->montant,
                'montant' => $payment->montant,
                'tokenPay' => $payment->token_pay,
                'token' => $payment->token_pay,
                'numeroTransaction' => $payment->numero_transaction,
                'moyen' => $payment->moyen,
                'frais' => $payment->frais,
                'source' => $source,
                'message' => $message,
            ],
        ];
    }

    /**
     * Préparer les données pour l'API
     */
    protected function preparePayload(array $data): array
    {
        return [
            'totalPrice' => (string) $data['total_price'],
            'article' => $this->formatArticles($data['articles'] ?? []),
            'personal_Info' => [
                [
                    'userId' => $data['user_id'] ?? null,
                    'orderId' => $data['order_id'] ?? null,
                ]
            ],
            'numeroSend' => $data['numero_send'] ?? '',
            'nomclient' => $data['nom_client'],
            'return_url' => $data['return_url'] ?? config('moneyfusion.return_url'),
            'webhook_url' => $data['webhook_url'] ?? config('moneyfusion.webhook_url'),
        ];
    }

    /**
     * Formater les articles
     */
    protected function formatArticles(array $articles): array
    {
        return array_map(function ($article) {
            return [
                'nom' => $article['name'] ?? $article['nom'] ?? 'Article',
                'montant' => (int) ($article['price'] ?? $article['montant'] ?? 0),
                'quantite' => $article['quantity'] ?? $article['quantite'] ?? 1,
            ];
        }, $articles);
    }

    /**
     * Sauvegarder le paiement
     */
    protected function storePayment(array $data, array $response): MoneyFusionPayment
    {
        return MoneyFusionPayment::create([
            'token_pay' => $response['token'],
            'user_id' => $data['user_id'] ?? null,
            'order_id' => $data['order_id'] ?? null,
            'numero_send' => $data['numero_send'] ?? '',
            'nom_client' => $data['nom_client'],
            'montant' => $data['total_price'],
            'payment_url' => $response['url'],
            'statut' => 'pending',
            'personal_info' => [
                'userId' => $data['user_id'] ?? null,
                'orderId' => $data['order_id'] ?? null,
            ],
            'articles' => $data['articles'] ?? [],
            'raw_response' => $response,
        ]);
    }

    /**
     * Mettre à jour le statut local depuis une réponse API vérifiée.
     * Un paiement déjà payé ne revient jamais en arrière.
     */
    protected function updatePaymentStatus(string $tokenPay, array $data): void
    {
        $payment = MoneyFusionPayment::where('token_pay', $tokenPay)->first();

        if (!$payment) {
            return;
        }

        $status = self::normalizeStatus($data['statut'] ?? null);

        if ($payment->isPaid() && $status !== 'paid') {
            return;
        }

        // Le montant confirmé par l'API doit être celui du paiement créé : sinon on ne crédite pas.
        if ($status === 'paid' && isset($data['Montant']) && (float) $data['Montant'] !== (float) $payment->montant) {
            Log::error('MoneyFusion: amount mismatch, payment not marked as paid', [
                'token' => $tokenPay,
                'expected' => $payment->montant,
                'received' => $data['Montant'],
            ]);

            return;
        }

        $updateData = [
            'statut' => $status,
            'numero_transaction' => $data['numeroTransaction'] ?? $payment->numero_transaction,
            'moyen' => $data['moyen'] ?? $payment->moyen,
            'frais' => $data['frais'] ?? $payment->frais ?? 0,
            'raw_response' => $data,
        ];

        if ($status === 'paid' && !$payment->paid_at) {
            $updateData['paid_at'] = now();
        }

        $payment->update($updateData);
    }
}
