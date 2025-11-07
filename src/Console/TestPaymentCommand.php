<?php

namespace Simonet85\LaravelMoneyFusion\Console;

use Illuminate\Console\Command;
use Simonet85\LaravelMoneyFusion\MoneyFusionService;
use Simonet85\LaravelMoneyFusion\Exceptions\MoneyFusionException;

class TestPaymentCommand extends Command
{
    protected $signature = 'moneyfusion:test-payment
                            {--amount= : Montant du paiement en FCFA}
                            {--client= : Nom du client}
                            {--phone= : Numéro de téléphone}';

    protected $description = 'Tester la création d un paiement MoneyFusion';

    public function handle(MoneyFusionService $moneyFusion): int
    {
        $this->info('Test de création de paiement MoneyFusion...');

        $amount = $this->option('amount') ?? $this->ask('Montant (FCFA)', '5000');
        $client = $this->option('client') ?? $this->ask('Nom du client', 'Client Test');
        $phone = $this->option('phone') ?? $this->ask('Numéro de téléphone (optionnel)', '');

        if (!is_numeric($amount) || $amount < 100) {
            $this->error('Le montant doit être un nombre supérieur ou égal à 100 FCFA');
            return self::FAILURE;
        }

        $data = [
            'total_price' => (float) $amount,
            'articles' => [
                ['name' => 'Article de test', 'price' => (float) $amount, 'quantity' => 1]
            ],
            'nom_client' => $client,
            'numero_send' => $phone ?: '',
        ];

        try {
            $this->info('Création du paiement en cours...');
            $result = $moneyFusion->createPayment($data);

            if ($result['statut'] ?? false) {
                $this->info('Paiement créé avec succès !');
                $this->line('Token: ' . $result['token']);
                $this->line('URL: ' . $result['url']);
                return self::SUCCESS;
            }

            $this->error('Échec de la création du paiement');
            return self::FAILURE;

        } catch (MoneyFusionException $e) {
            $this->error('Erreur: ' . $e->getMessage());
            return self::FAILURE;
        }
    }
}
