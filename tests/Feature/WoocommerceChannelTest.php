<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WoocommerceChannelTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'wc-feature-secret';

    protected function setUp(): void
    {
        parent::setUp();

        config(['channels.woocommerce.secret' => self::SECRET]);
    }

    private function payload(): array
    {
        return [
            'id' => 1234,
            'billing' => ['last_name' => '홍', 'first_name' => '길동', 'phone' => '010-1234-5678'],
            'shipping' => ['postcode' => '06236', 'address_1' => '서울 강남구 테헤란로 1', 'address_2' => '101동 101호'],
            'line_items' => [
                ['name' => '오메가3', 'option' => '90캡슐', 'quantity' => 3, 'price' => 15000],
            ],
            'payment_method' => 'bacs',
            'total' => '45000',
            'date_paid' => '2026-06-11 12:05:00',
            'pccc' => 'P123412341234',
            'date_created' => '2026-06-11 12:00:00',
        ];
    }

    private function signedPost(string $body)
    {
        $timestamp = now()->getTimestamp();
        $signature = hash_hmac('sha256', "woocommerce\n{$timestamp}\n{$body}", self::SECRET);

        return $this->call('POST', '/api/orders', [], [], [], $this->transformHeadersToServerVars([
            'X-Channel' => 'woocommerce',
            'X-Timestamp' => (string) $timestamp,
            'X-Signature' => $signature,
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ]), $body);
    }

    public function test_우커머스_주문이_표준_모델로_정규화되어_저장된다(): void
    {
        $body = json_encode($this->payload(), JSON_UNESCAPED_UNICODE);

        $this->signedPost($body)
            ->assertStatus(201)
            ->assertJsonPath('duplicated', false);

        $this->assertDatabaseHas('orders', [
            'channel' => 'woocommerce',
            'channel_order_no' => '1234',
            'customer_name' => '홍길동',
            'customer_phone' => '010-1234-5678',
            'shipping_address1' => '서울 강남구 테헤란로 1',
            'pccc' => 'P123412341234',
            'status' => 'received',
        ]);
    }

    public function test_배송지_누락_payload_는_거부된다(): void
    {
        $payload = $this->payload();
        unset($payload['shipping']['address_1']);
        $body = json_encode($payload, JSON_UNESCAPED_UNICODE);

        $this->signedPost($body)
            ->assertStatus(422)
            ->assertJsonPath('code', 'INVALID_PAYLOAD');
    }
}
