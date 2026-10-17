<?php

namespace Simonet85\LaravelMoneyFusion\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Simonet85\LaravelMoneyFusion\Events\PaymentReceived;
use Simonet85\LaravelMoneyFusion\Events\PaymentStatusUpdated;
use Simonet85\LaravelMoneyFusion\Models\MoneyFusionPayment;
use Simonet85\LaravelMoneyFusion\Tests\TestCase;

class WebhookTest extends TestCase
{
    use RefreshDatabase;

    private const CHECK_URL = 'https://pay.moneyfusion.net/paiementNotif/*';

    private function payment(array $attrs = []): MoneyFusionPayment
    {
        return MoneyFusionPayment::factory()->create(array_merge([
            'token_pay' => 'tok_123',
            'montant' => 5000,
        ], $attrs));
    }

    private function apiStatus(string $statut, $montant = 5000): void
    {
        Http::fake([
            self::CHECK_URL => Http::response([
                'statut' => true,
                'data' => ['tokenPay' => 'tok_123', 'statut' => $statut, 'Montant' => $montant, 'frais' => 100, 'moyen' => 'orange', 'numeroTransaction' => 'TX1'],
                'message' => 'details paiement',
            ], 200),
        ]);
    }

    private function notify(string $event = 'payin.session.completed', array $extra = [])
    {
        return $this->postJson('/api/moneyfusion/webhook', array_merge(['event' => $event, 'tokenPay' => 'tok_123'], $extra));
    }

    /** @test */
    public function it_rejects_a_notification_without_token(): void
    {
        $this->postJson('/api/moneyfusion/webhook', ['event' => 'payin.session.completed'])->assertStatus(400);
    }

    /** @test */
    public function it_returns_404_for_an_unknown_token(): void
    {
        $this->notify()->assertStatus(404);
    }

    /** @test */
    public function a_forged_success_notification_does_not_mark_the_payment_as_paid_when_the_api_says_pending(): void
    {
        $payment = $this->payment();
        $this->apiStatus('pending');

        $this->notify('payin.session.completed', ['statut' => 'paid', 'Montant' => 5000])->assertOk();

        $this->assertSame('pending', $payment->fresh()->statut);
    }

    /** @test */
    public function a_forged_success_notification_is_ignored_when_the_api_is_unreachable(): void
    {
        $payment = $this->payment();
        Http::fake([self::CHECK_URL => Http::response('boom', 500)]);

        $this->notify('payment.success')->assertStatus(503);

        $this->assertSame('pending', $payment->fresh()->statut);
    }

    /** @test */
    public function a_verified_completed_notification_marks_the_payment_as_paid_and_fires_events_once(): void
    {
        Event::fake([PaymentStatusUpdated::class, PaymentReceived::class]);
        $payment = $this->payment();
        $this->apiStatus('paid');

        $this->notify()->assertOk();
        $this->notify()->assertOk(); // notification en double

        $payment->refresh();
        $this->assertSame('paid', $payment->statut);
        $this->assertNotNull($payment->paid_at);
        $this->assertSame('TX1', $payment->numero_transaction);
        Event::assertDispatchedTimes(PaymentReceived::class, 1);
        Event::assertDispatchedTimes(PaymentStatusUpdated::class, 1);
    }

    /** @test */
    public function the_legacy_event_names_are_verified_too(): void
    {
        $payment = $this->payment();
        $this->apiStatus('pending');

        $this->notify('payment.success')->assertOk();

        $this->assertSame('pending', $payment->fresh()->statut);
    }

    /** @test */
    public function a_paid_status_with_a_different_amount_is_not_credited(): void
    {
        $payment = $this->payment();
        $this->apiStatus('paid', 100);

        $this->notify()->assertOk();

        $this->assertSame('pending', $payment->fresh()->statut);
    }

    /** @test */
    public function the_api_failure_status_is_stored_as_failed_without_a_database_error(): void
    {
        Event::fake([PaymentStatusUpdated::class]);
        $payment = $this->payment();
        $this->apiStatus('failure');

        $this->notify('payin.session.cancelled')->assertOk();

        $this->assertSame('failed', $payment->fresh()->statut);
        Event::assertDispatched(PaymentStatusUpdated::class, fn ($e) => $e->oldStatus === 'pending' && $e->newStatus === 'failed');
    }

    /** @test */
    public function the_api_no_paid_status_is_stored_as_cancelled(): void
    {
        $payment = $this->payment();
        $this->apiStatus('no paid');

        $this->notify('payin.session.cancelled')->assertOk();

        $this->assertSame('cancelled', $payment->fresh()->statut);
    }

    /** @test */
    public function a_paid_payment_is_never_reverted(): void
    {
        $payment = $this->payment(['statut' => 'paid', 'paid_at' => now()]);
        $this->apiStatus('failure');

        $this->notify('payin.session.cancelled')->assertOk();
        app(\Simonet85\LaravelMoneyFusion\MoneyFusionService::class)->checkPaymentStatus('tok_123');

        $this->assertSame('paid', $payment->fresh()->statut);
    }
}
