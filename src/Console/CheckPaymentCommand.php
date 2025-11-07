<?php

namespace Simonet85\LaravelMoneyFusion\Console;

use Illuminate\Console\Command;
use Simonet85\LaravelMoneyFusion\MoneyFusionService;
use Simonet85\LaravelMoneyFusion\Models\MoneyFusionPayment;
use Simonet85\LaravelMoneyFusion\Exceptions\MoneyFusionException;

class CheckPaymentCommand extends Command
{
    protected $signature = 'moneyfusion:check-payment {token : Token du paiement}';
    protected $description = 'Vérifier le statut d un paiement MoneyFusion';

    public function handle(MoneyFusionService $moneyFusion): int
    {
        $token = $this->argument('token');
        $this->info('Vérification du paiement: ' . $token);

        try {
            $payment = MoneyFusionPayment::where('token_pay', $token)->first();

            if ($payment) {
                $this->info('Statut local: ' . $payment->statut);
                $this->line('Montant: ' . $payment->montant . ' FCFA');
                $this->line('Client: ' . $payment->nom_client);
            }

            $result = $moneyFusion->checkPaymentStatus($token);

            if (isset($result['data'])) {
                $this->info('Statut MoneyFusion: ' . ($result['data']['statut'] ?? 'unknown'));
            }

            return self::SUCCESS;

        } catch (MoneyFusionException $e) {
            $this->error('Erreur: ' . $e->getMessage());
            return self::FAILURE;
        }
    }
}
