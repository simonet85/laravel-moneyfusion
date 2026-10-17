<?php

namespace Simonet85\LaravelMoneyFusion\Tests\Feature;

use Illuminate\Support\Facades\Route;
use Simonet85\LaravelMoneyFusion\Tests\TestCase;

class RoutesConfigTest extends TestCase
{
    /** @test */
    public function all_routes_are_registered_by_default(): void
    {
        $this->assertTrue(Route::has('moneyfusion.payments.initiate'));
        $this->assertTrue(Route::has('moneyfusion.webhook'));
        $this->assertTrue(Route::has('payment.callback'));
    }
}

class PaymentEndpointsDisabledTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);
        $app['config']->set('moneyfusion.routes.payment_endpoints', false);
        $app['config']->set('moneyfusion.routes.web', false);
    }

    /** @test */
    public function the_payment_endpoints_and_pages_can_be_disabled_while_the_webhook_stays(): void
    {
        $this->assertFalse(Route::has('moneyfusion.payments.initiate'));
        $this->assertFalse(Route::has('moneyfusion.payments.status'));
        $this->assertFalse(Route::has('moneyfusion.payments.cancel'));
        $this->assertFalse(Route::has('payment.callback'));
        $this->assertTrue(Route::has('moneyfusion.webhook'));
    }
}

class AllRoutesDisabledTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);
        $app['config']->set('moneyfusion.routes.enabled', false);
    }

    /** @test */
    public function no_route_is_registered_when_disabled(): void
    {
        $this->assertFalse(Route::has('moneyfusion.webhook'));
        $this->assertFalse(Route::has('moneyfusion.payments.initiate'));
    }
}
