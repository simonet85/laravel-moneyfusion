<?php

namespace Simonet85\LaravelMoneyFusion\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static array createPayment(array $data)
 * @method static array checkPaymentStatus(string $tokenPay)
 * @method static \Simonet85\LaravelMoneyFusion\Models\MoneyFusionPayment|null getPaymentByToken(string $tokenPay)
 * 
 * @see \VotreVendor\LaravelMoneyFusion\MoneyFusionService
 */

class MoneyFusion extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'moneyfusion';
    }
}