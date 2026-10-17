<?php

namespace Simonet85\LaravelMoneyFusion\Tests\Unit;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Simonet85\LaravelMoneyFusion\Exceptions\MoneyFusionException;
use Simonet85\LaravelMoneyFusion\Models\MoneyFusionPayment;
use Simonet85\LaravelMoneyFusion\MoneyFusionService;
use Simonet85\LaravelMoneyFusion\Tests\TestCase;

class MoneyFusionServiceTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_normalizes_documented_api_statuses(): void
    {
        $this->assertSame('pending', MoneyFusionService::normalizeStatus('pending'));
        $this->assertSame('paid', MoneyFusionService::normalizeStatus('paid'));
        $this->assertSame('failed', MoneyFusionService::normalizeStatus('failure'));
        $this->assertSame('cancelled', MoneyFusionService::normalizeStatus('no paid'));
        $this->assertSame('paid', MoneyFusionService::normalizeStatus(' PAID '));
    }

    /** @test */
    public function an_unknown_status_never_credits_nor_fails_a_payment(): void
    {
        $this->assertSame('pending', MoneyFusionService::normalizeStatus('???'));
        $this->assertSame('pending', MoneyFusionService::normalizeStatus(null));
    }

    /** @test */
    public function it_requires_configuration(): void
    {
        config(['moneyfusion.app_key' => null]);

        $this->expectException(MoneyFusionException::class);
        new MoneyFusionService;
    }

    /** @test */
    public function the_check_url_defaults_to_the_documented_endpoint(): void
    {
        config(['moneyfusion.check_payment_url' => null]);
        Http::fake(['*' => Http::response(['statut' => true, 'data' => ['statut' => 'pending']], 200)]);

        (new MoneyFusionService)->checkPaymentStatus('abc');

        Http::assertSent(fn ($request) => $request->url() === 'https://pay.moneyfusion.net/paiementNotif/abc');
    }

    /** @test */
    public function a_real_api_answer_is_flagged_verified(): void
    {
        MoneyFusionPayment::factory()->create(['token_pay' => 'abc', 'montant' => 200]);
        Http::fake(['*' => Http::response(['statut' => true, 'data' => ['statut' => 'paid', 'Montant' => 200], 'message' => 'details paiement'], 200)]);

        $result = (new MoneyFusionService)->checkPaymentStatus('abc');

        $this->assertTrue($result['verified']);
        $this->assertSame('paid', $result['data']['statut']);
    }

    /** @test */
    public function the_local_fallback_is_flagged_unverified(): void
    {
        MoneyFusionPayment::factory()->create(['token_pay' => 'abc', 'statut' => 'paid', 'montant' => 200]);
        Http::fake(['*' => Http::response('down', 503)]);

        $result = (new MoneyFusionService)->checkPaymentStatus('abc');

        $this->assertFalse($result['verified']);
        $this->assertSame('local_database', $result['data']['source']);
    }

    /** @test */
    public function creating_a_payment_requires_a_token_and_url_in_the_response(): void
    {
        Http::fake(['*' => Http::response(['statut' => true, 'message' => 'paiement en cours'], 200)]);

        $this->expectException(MoneyFusionException::class);
        (new MoneyFusionService)->createPayment([
            'total_price' => 1000, 'nom_client' => 'Jean', 'articles' => [['name' => 'A', 'price' => 1000]],
        ]);
    }

    /** @test */
    public function it_sends_the_documented_payload(): void
    {
        Http::fake(['*' => Http::response(['statut' => true, 'token' => 't1', 'url' => 'https://payin.moneyfusion.net/payment/t1/M', 'message' => 'paiement en cours'], 200)]);

        (new MoneyFusionService)->createPayment([
            'total_price' => 1500, 'nom_client' => 'Jean', 'numero_send' => '0707070707', 'order_id' => 42,
            'articles' => [['name' => 'Cagnotte', 'price' => 1500]],
            'return_url' => 'https://exemple.test/retour', 'webhook_url' => 'https://exemple.test/hook',
        ]);

        Http::assertSent(fn ($r) => $r['totalPrice'] === '1500'
            && $r['nomclient'] === 'Jean'
            && $r['numeroSend'] === '0707070707'
            && $r['personal_Info'][0]['orderId'] === 42
            && $r['return_url'] === 'https://exemple.test/retour'
            && $r['webhook_url'] === 'https://exemple.test/hook');
        $this->assertDatabaseHas('moneyfusion_payments', ['token_pay' => 't1', 'order_id' => 42]);
    }
}
