<?php

namespace Simonet85\LaravelMoneyFusion\Tests\Feature;

use Illuminate\Auth\GenericUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Simonet85\LaravelMoneyFusion\Models\MoneyFusionPayment;
use Simonet85\LaravelMoneyFusion\Tests\TestCase;

class AccessControlTest extends TestCase
{
    use RefreshDatabase;

    private function ownedPayment(): MoneyFusionPayment
    {
        return MoneyFusionPayment::factory()->create(['token_pay' => 'tok_own', 'user_id' => 7, 'statut' => 'pending']);
    }

    /** @test */
    public function a_guest_cannot_read_the_status_of_a_payment_owned_by_a_user(): void
    {
        $this->ownedPayment();
        Http::fake(['*' => Http::response(['statut' => true, 'data' => ['statut' => 'pending']], 200)]);

        $this->getJson('/api/moneyfusion/payments/tok_own/status')->assertForbidden();
    }

    /** @test */
    public function another_user_cannot_read_or_cancel_the_payment(): void
    {
        $payment = $this->ownedPayment();
        $other = new GenericUser(['id' => 8]);

        $this->actingAs($other)->getJson('/api/moneyfusion/payments/tok_own/status')->assertForbidden();
        $this->actingAs($other)->postJson('/api/moneyfusion/payments/tok_own/cancel')->assertForbidden();
        $this->assertSame('pending', $payment->fresh()->statut);
    }

    /** @test */
    public function a_guest_cannot_cancel_the_payment_of_a_user(): void
    {
        $payment = $this->ownedPayment();

        $this->postJson('/api/moneyfusion/payments/tok_own/cancel')->assertForbidden();
        $this->assertSame('pending', $payment->fresh()->statut);
    }

    /** @test */
    public function the_owner_can_read_the_status(): void
    {
        $this->ownedPayment();
        Http::fake(['*' => Http::response(['statut' => true, 'data' => ['statut' => 'pending']], 200)]);

        $this->actingAs(new GenericUser(['id' => 7]))->getJson('/api/moneyfusion/payments/tok_own/status')->assertOk();
    }

    /** @test */
    public function a_payment_without_owner_stays_reachable_by_token(): void
    {
        MoneyFusionPayment::factory()->create(['token_pay' => 'tok_anon', 'user_id' => null]);
        Http::fake(['*' => Http::response(['statut' => true, 'data' => ['statut' => 'pending']], 200)]);

        $this->getJson('/api/moneyfusion/payments/tok_anon/status')->assertOk();
    }

    /** @test */
    public function the_callback_without_token_redirects_home_even_without_a_home_route(): void
    {
        $this->get('/payment/callback')->assertRedirect(url('/'));
    }
}
