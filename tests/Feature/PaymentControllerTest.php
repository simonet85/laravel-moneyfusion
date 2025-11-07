<?php

namespace Simonet85\LaravelMoneyFusion\Tests\Feature;

use Simonet85\LaravelMoneyFusion\Tests\TestCase;
use Simonet85\LaravelMoneyFusion\Models\MoneyFusionPayment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

class PaymentControllerTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_can_initiate_a_payment_via_api()
    {
        Http::fake([
            '*' => Http::response([
                'statut' => true,
                'token' => 'test_token_123',
                'url' => 'https://pay.moneyfusion.net/pay/test_token_123',
                'message' => 'paiement en cours'
            ], 200)
        ]);

        $response = $this->postJson('/api/moneyfusion/payments/initiate', [
            'total_price' => 5000,
            'articles' => [
                [
                    'name' => 'Test Product',
                    'price' => 5000,
                    'quantity' => 1
                ]
            ],
            'nom_client' => 'Jean Test',
            'numero_send' => '0707070707',
        ]);

        $response->assertStatus(201)
                 ->assertJson([
                     'success' => true,
                     'token' => 'test_token_123',
                 ]);

        $this->assertDatabaseHas('moneyfusion_payments', [
            'token_pay' => 'test_token_123',
            'montant' => 5000,
        ]);
    }

    /** @test */
    public function it_validates_payment_data()
    {
        $response = $this->postJson('/api/moneyfusion/payments/initiate', [
            'total_price' => 50, // Moins que le minimum
            // articles manquant
            // nom_client manquant
        ]);

        $response->assertStatus(422)
                 ->assertJsonValidationErrors(['total_price', 'articles', 'nom_client']);
    }

    /** @test */
    public function it_can_check_payment_status()
    {
        $payment = MoneyFusionPayment::factory()->create([
            'token_pay' => 'test_token',
        ]);

        Http::fake([
            '*' => Http::response([
                'statut' => true,
                'data' => ['statut' => 'paid']
            ], 200)
        ]);

        $response = $this->getJson('/api/moneyfusion/payments/test_token/status');

        $response->assertStatus(200)
                 ->assertJson(['success' => true]);
    }

    /** @test */
    public function it_redirects_to_success_page_for_paid_payment()
    {
        $payment = MoneyFusionPayment::factory()->paid()->create();

        $response = $this->get("/payment/callback?token={$payment->token_pay}");

        $response->assertRedirect(route('payment.success', $payment->token_pay));
    }
}