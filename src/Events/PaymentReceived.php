<?php

namespace Simonet85\LaravelMoneyFusion\Events;

use Simonet85\LaravelMoneyFusion\Models\MoneyFusionPayment;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PaymentReceived
{
    use Dispatchable, SerializesModels;

    /**
     * Créer une nouvelle instance de l'événement
     *
     * @param MoneyFusionPayment $payment
     */
    public function __construct(public MoneyFusionPayment $payment) {}
}
