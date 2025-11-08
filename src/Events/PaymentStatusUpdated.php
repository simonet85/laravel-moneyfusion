<?php

namespace Simonet85\LaravelMoneyFusion\Events;

use Simonet85\LaravelMoneyFusion\Models\MoneyFusionPayment;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PaymentStatusUpdated
{
    use Dispatchable, SerializesModels;

    /**
     * Créer une nouvelle instance de l'événement
     *
     * @param MoneyFusionPayment $payment
     * @param string $oldStatus
     * @param string $newStatus
     */
    public function __construct(
        public MoneyFusionPayment $payment,
        public string $oldStatus,
        public string $newStatus
    ) {}
}
