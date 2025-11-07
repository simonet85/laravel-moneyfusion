<?php

namespace Simonet85\LaravelMoneyFusion\Tests\Feature;

use Simonet85\LaravelMoneyFusion\Tests\TestCase;
use Simonet85\LaravelMoneyFusion\Facades\MoneyFusion;
use Simonet85\LaravelMoneyFusion\Models\MoneyFusionPayment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

class PaymentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Configuration de test
        config([
            'moneyfusion.api_url' => 'https://api.moneyfusion.net/api/create-payment',
            'moneyfusion.app_key' => 'TestApp/TestKey',
        ]);
    }

    /** @test */
    public function it_can_create_a_payment()
    {
        Http::fake([
            '*' => Http::response([
                'statut' => true,
                'token' => 'test_token_123',
                'url' => 'https://pay.moneyfusion.net/pay/test_token_123',
                'message' => 'paiement en cours'
            ], 200)
        ]);

        $result = MoneyFusion::createPayment([
            'total_price' => 5000,
            'articles' => [
                [
                    'name' => 'Test Product',
                    'price' => 5000,
                    'quantity' => 1
                ]
            ],
            'nom_client' => 'Test Client',
            'numero_send' => '0707070707',
        ]);

        $this->assertTrue($result['statut']);
        $this->assertEquals('test_token_123', $result['token']);
        
        $this->assertDatabaseHas('moneyfusion_payments', [
            'token_pay' => 'test_token_123',
            'montant' => 5000,
            'statut' => 'pending',
        ]);
    }

    /** @test */
    public function it_can_check_payment_status()
    {
        $payment = MoneyFusionPayment::factory()->create([
            'token_pay' => 'test_token_123',
            'statut' => 'pending',
        ]);

        Http::fake([
            '*' => Http::response([
                'statut' => true,
                'data' => [
                    'tokenPay' => 'test_token_123',
                    'statut' => 'paid',
                    'Montant' => 5000,
                    'numeroTransaction' => 'TRX123'
                ]
            ], 200)
        ]);

        $result = MoneyFusion::checkPaymentStatus('test_token_123');

        $this->assertTrue($result['statut']);
        
        $payment->refresh();
        $this->assertEquals('paid', $payment->statut);
        $this->assertNotNull($payment->paid_at);
    }

    /** @test */
    public function payment_model_has_correct_scopes()
    {
        MoneyFusionPayment::factory()->create(['statut' => 'paid']);
        MoneyFusionPayment::factory()->create(['statut' => 'pending']);
        MoneyFusionPayment::factory()->create(['statut' => 'failed']);

        $this->assertCount(1, MoneyFusionPayment::paid()->get());
        $this->assertCount(1, MoneyFusionPayment::pending()->get());
        $this->assertCount(1, MoneyFusionPayment::failed()->get());
    }

    /** @test */
    public function payment_can_be_marked_as_paid()
    {
        $payment = MoneyFusionPayment::factory()->create(['statut' => 'pending']);

        $payment->markAsPaid([
            'numeroTransaction' => 'TRX123',
            'moyen' => 'orange_money',
            'frais' => 250,
        ]);

        $this->assertEquals('paid', $payment->statut);
        $this->assertEquals('TRX123', $payment->numero_transaction);
        $this->assertNotNull($payment->paid_at);
    }
}