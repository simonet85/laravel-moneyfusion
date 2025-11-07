<?php

namespace Simonet85\LaravelMoneyFusion\Database\Factories;

use Simonet85\LaravelMoneyFusion\Models\MoneyFusionPayment;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class MoneyFusionPaymentFactory extends Factory
{
    protected $model = MoneyFusionPayment::class;

    public function definition(): array
    {
        return [
            'token_pay' => Str::random(20),
            'user_id' => null,
            'order_id' => null,
            'numero_send' => '07' . $this->faker->numerify('########'),
            'nom_client' => $this->faker->name(),
            'montant' => $this->faker->numberBetween(1000, 100000),
            'frais' => 0,
            'numero_transaction' => null,
            'statut' => 'pending',
            'moyen' => null,
            'payment_url' => 'https://pay.moneyfusion.net/pay/' . Str::random(20),
            'personal_info' => [
                'userId' => null,
                'orderId' => null,
            ],
            'articles' => [
                [
                    'name' => $this->faker->words(3, true),
                    'price' => $this->faker->numberBetween(1000, 50000),
                    'quantity' => 1,
                ]
            ],
            'raw_response' => null,
            'paid_at' => null,
        ];
    }

    /**
     * State pour paiement payé
     */
    public function paid(): static
    {
        return $this->state(fn (array $attributes) => [
            'statut' => 'paid',
            'paid_at' => now(),
            'numero_transaction' => 'TRX' . $this->faker->numerify('######'),
            'moyen' => $this->faker->randomElement(['card', 'orange_money', 'mtn', 'wave', 'moov']),
            'frais' => $attributes['montant'] * 0.05,
        ]);
    }

    /**
     * State pour paiement échoué
     */
    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'statut' => 'failed',
        ]);
    }

    /**
     * State pour paiement annulé
     */
    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'statut' => 'cancelled',
        ]);
    }
}