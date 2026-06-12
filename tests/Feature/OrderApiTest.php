<?php

namespace Tests\Feature;

use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderApiTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'feature-secret';

    protected function setUp(): void
    {
        parent::setUp();

        config(['channels.youngcart.secret' => self::SECRET]);
    }

    private function payload(): array
    {
        return [
            'od_id' => '2606110001',
            'buyer_name' => '홍길동',
            'buyer_phone' => '010-1234-5678',
            'zipcode' => '06236',
            'address1' => '서울 강남구 테헤란로 1',
            'address2' => '101동 101호',
            'items' => [
                ['name' => '비타민C 1000mg', 'option' => '180정', 'qty' => 2, 'price' => 25000],
            ],
            'payment' => ['method' => 'bank', 'amount' => 50000, 'paid_at' => '2026-06-11 12:00:00'],
            'pccc' => 'P123412341234',
            'ordered_at' => '2026-06-11 12:00:00',
        ];
    }

    private function signedRequest(string $method, string $uri, string $body = '')
    {
        $timestamp = now()->getTimestamp();
        $signature = hash_hmac('sha256', "youngcart\n{$timestamp}\n{$body}", self::SECRET);

        $server = $this->transformHeadersToServerVars([
            'X-Channel' => 'youngcart',
            'X-Timestamp' => (string) $timestamp,
            'X-Signature' => $signature,
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ]);

        return $this->call($method, $uri, [], [], [], $server, $body);
    }

    public function test_서명_없는_요청은_거부된다(): void
    {
        $this->postJson('/api/orders', $this->payload())
            ->assertStatus(401)
            ->assertJsonPath('code', 'AUTH_HEADER_MISSING');
    }

    public function test_잘못된_서명은_거부된다(): void
    {
        $body = json_encode($this->payload(), JSON_UNESCAPED_UNICODE);
        $timestamp = now()->getTimestamp();

        $this->call('POST', '/api/orders', [], [], [], $this->transformHeadersToServerVars([
            'X-Channel' => 'youngcart',
            'X-Timestamp' => (string) $timestamp,
            'X-Signature' => 'deadbeef',
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ]), $body)
            ->assertStatus(401)
            ->assertJsonPath('code', 'INVALID_SIGNATURE');
    }

    public function test_주문이_정상_수신된다(): void
    {
        $body = json_encode($this->payload(), JSON_UNESCAPED_UNICODE);

        $this->signedRequest('POST', '/api/orders', $body)
            ->assertStatus(201)
            ->assertJsonPath('ok', true)
            ->assertJsonPath('duplicated', false);

        $this->assertDatabaseHas('orders', [
            'channel' => 'youngcart',
            'channel_order_no' => '2606110001',
            'customer_name' => '홍길동',
            'status' => 'received',
        ]);
    }

    public function test_같은_주문_재전송은_중복_저장되지_않는다(): void
    {
        $body = json_encode($this->payload(), JSON_UNESCAPED_UNICODE);

        $first = $this->signedRequest('POST', '/api/orders', $body);
        $first->assertStatus(201);

        $second = $this->signedRequest('POST', '/api/orders', $body);
        $second->assertStatus(200)
            ->assertJsonPath('duplicated', true)
            ->assertJsonPath('order_id', $first->json('order_id'));

        $this->assertSame(1, Order::count());
    }

    public function test_필수_항목_누락_payload_는_거부된다(): void
    {
        $payload = $this->payload();
        unset($payload['buyer_name']);
        $body = json_encode($payload, JSON_UNESCAPED_UNICODE);

        $this->signedRequest('POST', '/api/orders', $body)
            ->assertStatus(422)
            ->assertJsonPath('code', 'INVALID_PAYLOAD');
    }

    public function test_주문번호와_연락처로_조회된다(): void
    {
        $body = json_encode($this->payload(), JSON_UNESCAPED_UNICODE);
        $this->signedRequest('POST', '/api/orders', $body);

        $this->signedRequest('GET', '/api/orders/2606110001?phone=010-1234-5678')
            ->assertOk()
            ->assertJsonPath('order.channel_order_no', '2606110001')
            ->assertJsonPath('order.status', 'received');
    }

    public function test_연락처가_다르면_조회되지_않는다(): void
    {
        $body = json_encode($this->payload(), JSON_UNESCAPED_UNICODE);
        $this->signedRequest('POST', '/api/orders', $body);

        $this->signedRequest('GET', '/api/orders/2606110001?phone=010-9999-9999')
            ->assertStatus(404)
            ->assertJsonPath('code', 'ORDER_NOT_FOUND');
    }
}
