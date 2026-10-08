<?php

namespace Tests\Feature\Payments;

use App\Enums\PaymentGatewayDriver;
use App\Enums\UserRole;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class XenditIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_checkout_creates_a_real_invoice_when_xendit_driver_is_active(): void
    {
        config(['payment.gateway' => 'xendit', 'payment.xendit.secret_key' => 'xnd_development_test']);
        Http::fake([
            'api.xendit.co/v2/invoices' => Http::response([
                'id' => 'xnd-invoice-1',
                'invoice_url' => 'https://checkout-staging.xendit.co/web/xnd-invoice-1',
                'status' => 'PENDING',
                'expiry_date' => now()->addDay()->toIso8601String(),
            ]),
        ]);

        $buyer = User::factory()->create(['role' => UserRole::Buyer]);
        $vehicle = Vehicle::factory()->approved()->create(['price' => 100_000_000]);

        $response = $this->actingAs($buyer)->postJson("/api/buyer/vehicles/{$vehicle->id}/checkout", [
            'amount' => 20_000_000,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.payment_gateway', 'xendit')
            ->assertJsonPath('data.gateway_reference', 'xnd-invoice-1')
            ->assertJsonPath('data.gateway_invoice_url', 'https://checkout-staging.xendit.co/web/xnd-invoice-1');
    }

    public function test_webhook_marks_transaction_as_paid_when_token_matches(): void
    {
        config(['payment.xendit.callback_verification_token' => 'secret-token']);
        $transaction = Transaction::factory()->create([
            'payment_gateway' => PaymentGatewayDriver::Xendit,
            'gateway_reference' => 'xnd-invoice-2',
        ]);

        $response = $this->postJson('/api/webhooks/xendit', [
            'id' => 'xnd-invoice-2',
            'status' => 'PAID',
        ], ['x-callback-token' => 'secret-token']);

        $response->assertNoContent();
        $this->assertDatabaseHas('transactions', ['id' => $transaction->id, 'payment_status' => 'paid']);
    }

    public function test_webhook_rejects_request_with_wrong_token(): void
    {
        config(['payment.xendit.callback_verification_token' => 'secret-token']);
        $transaction = Transaction::factory()->create([
            'payment_gateway' => PaymentGatewayDriver::Xendit,
            'gateway_reference' => 'xnd-invoice-3',
        ]);

        $this->postJson('/api/webhooks/xendit', [
            'id' => 'xnd-invoice-3',
            'status' => 'PAID',
        ], ['x-callback-token' => 'wrong-token'])->assertForbidden();

        $this->assertDatabaseHas('transactions', ['id' => $transaction->id, 'payment_status' => 'pending']);
    }

    public function test_buyer_can_poll_status_as_a_webhook_fallback(): void
    {
        config(['payment.gateway' => 'xendit', 'payment.xendit.secret_key' => 'xnd_development_test']);
        Http::fake([
            'api.xendit.co/v2/invoices/xnd-invoice-4' => Http::response(['status' => 'PAID']),
        ]);

        $buyer = User::factory()->create(['role' => UserRole::Buyer]);
        $transaction = Transaction::factory()->create([
            'buyer_id' => $buyer->id,
            'payment_gateway' => PaymentGatewayDriver::Xendit,
            'gateway_reference' => 'xnd-invoice-4',
        ]);

        $response = $this->actingAs($buyer)->postJson("/api/buyer/transactions/{$transaction->id}/refresh-status");

        $response->assertOk()->assertJsonPath('data.payment_status', 'paid');
    }
}
