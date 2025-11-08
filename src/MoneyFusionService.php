<?php

namespace Simonet85\LaravelMoneyFusion;

use Simonet85\LaravelMoneyFusion\Models\MoneyFusionPayment;
use Simonet85\LaravelMoneyFusion\Exceptions\MoneyFusionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MoneyFusionService
{
    protected string $apiUrl;
    protected string $appKey;
    protected int $timeout;
    protected bool $verifySSL;
    protected ?string $checkPaymentUrl;

    public function __construct()
    {
        $this->apiUrl = config('moneyfusion.api_url');
        $this->appKey = config('moneyfusion.app_key');
        $this->timeout = config('moneyfusion.timeout', 30);
        $this->verifySSL = config('moneyfusion.verify_ssl', true);
        $this->checkPaymentUrl = config('moneyfusion.check_payment_url');

        if (empty($this->apiUrl) || empty($this->appKey)) {
            throw new MoneyFusionException('MoneyFusion configuration is missing.');
        }
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
     * Vérifier le statut d'un paiement
     */
    public function checkPaymentStatus(string $tokenPay): array
    {
        try {
            // Utiliser l'URL configurée si disponible, sinon construire depuis api_url
            if ($this->checkPaymentUrl) {
                $url = rtrim($this->checkPaymentUrl, '/') . '/' . $tokenPay;
            } else {
                $url = str_replace('/create-payment', "/check-payment/{$tokenPay}", $this->apiUrl);
            }

            Log::info('MoneyFusion: Checking payment status', [
                'url' => $url,
                'token' => $tokenPay
            ]);

            $response = Http::timeout($this->timeout)
                ->withOptions(['verify' => $this->verifySSL])
                ->get($url);

            // Fallback à la base de données locale si l'API échoue
            if (!$response->successful()) {
                Log::warning('MoneyFusion: Check payment API returned error', [
                    'status' => $response->status(),
                    'body' => $response->body()
                ]);

                // Tenter de récupérer depuis la base de données locale
                $payment = $this->getPaymentByToken($tokenPay);

                if ($payment) {
                    Log::info('MoneyFusion: Using local database fallback', [
                        'token' => $tokenPay,
                        'status' => $payment->statut
                    ]);

                    return [
                        'statut' => true,
                        'data' => [
                            'statut' => $payment->statut,
                            'montant' => $payment->montant,
                            'token' => $payment->token_pay,
                            'numeroTransaction' => $payment->numero_transaction,
                            'moyen' => $payment->moyen,
                            'frais' => $payment->frais,
                            'source' => 'local_database',
                            'message' => 'API vérification indisponible. Données de la base locale.'
                        ]
                    ];
                }

                throw new MoneyFusionException('API error: ' . $response->body());
            }

            $result = $response->json();

            // Mettre à jour en base
            if (isset($result['data'])) {
                $this->updatePaymentStatus($tokenPay, $result['data']);
            }

            return $result;

        } catch (\Exception $e) {
            Log::error('MoneyFusion: Status check failed', [
                'error' => $e->getMessage(),
                'token' => $tokenPay
            ]);

            // Dernier recours: essayer la base de données
            $payment = $this->getPaymentByToken($tokenPay);

            if ($payment) {
                return [
                    'statut' => true,
                    'data' => [
                        'statut' => $payment->statut,
                        'montant' => $payment->montant,
                        'token' => $payment->token_pay,
                        'source' => 'local_database_exception',
                        'message' => 'Erreur API. Données de la base locale.'
                    ]
                ];
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
     * Mettre à jour le statut
     */
    protected function updatePaymentStatus(string $tokenPay, array $data): void
    {
        $payment = MoneyFusionPayment::where('token_pay', $tokenPay)->first();

        if (!$payment) {
            return;
        }

        $updateData = [
            'statut' => $data['statut'] ?? 'pending',
            'numero_transaction' => $data['numeroTransaction'] ?? null,
            'moyen' => $data['moyen'] ?? null,
            'frais' => $data['frais'] ?? 0,
            'raw_response' => $data,
        ];

        if (($data['statut'] ?? '') === 'paid' && !$payment->paid_at) {
            $updateData['paid_at'] = now();
        }

        $payment->update($updateData);
    }
}