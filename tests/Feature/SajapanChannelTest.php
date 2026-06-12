<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SajapanChannelTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'sajapan-feature-secret';

    protected function setUp(): void
    {
        parent::setUp();

        config(['channels.sajapan.secret' => self::SECRET]);
    }

    private function payload(): array
    {
        return [
            'order_id' => 312,
            'name' => '홍길동',
            'phone' => '01012345678',
            'pccc' => 'P123412341234',
            'zipcode' => '06236',
            'address' => '서울 강남구 테헤란로 1 101동 101호',
            'product' => [
                'name' => '피규어 ABC',
                'url' => 'https://www.amiami.jp/top/detail/detail?gcode=FIGURE-123',
                'source' => 'amiami',
                'price_jpy' => 5000,
                'qty' => 2,
            ],
            'inspection' => '일반검수',
            'fees_jpy' => ['agency' => 400, 'remit' => 200, 'inspection' => 300],
            'subtotal_jpy' => 10000,
            'total_jpy' => 10900,
            'points_used' => 1000,
            'memo' => '빠른 배송 부탁드립니다',
            'ordered_at' => '2026-06-12 14:00:00',
        ];
    }

    private function signedPost(string $body)
    {
        $timestamp = now()->getTimestamp();
        $signature = hash_hmac('sha256', "sajapan\n{$timestamp}\n{$body}", self::SECRET);

        return $this->call('POST', '/api/orders', [], [], [], $this->transformHeadersToServerVars([
            'X-Channel' => 'sajapan',
            'X-Timestamp' => (string) $timestamp,
            'X-Signature' => $signature,
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ]), $body);
    }

    public function test_구매대행_주문이_표준_모델로_저장된다(): void
    {
        $body = json_encode($this->payload(), JSON_UNESCAPED_UNICODE);

        $this->signedPost($body)
            ->assertStatus(201)
            ->assertJsonPath('duplicated', false);

        $this->assertDatabaseHas('orders', [
            'channel' => 'sajapan',
            'channel_order_no' => '312',
            'customer_name' => '홍길동',
            'customer_phone' => '01012345678',
            'pccc' => 'P123412341234',
            'status' => 'received',
        ]);
    }

    public function test_같은_주문_재전송은_중복_저장되지_않는다(): void
    {
        $body = json_encode($this->payload(), JSON_UNESCAPED_UNICODE);

        $first = $this->signedPost($body);
        $first->assertStatus(201);

        $this->signedPost($body)
            ->assertStatus(200)
            ->assertJsonPath('duplicated', true)
            ->assertJsonPath('order_id', $first->json('order_id'));
    }

    public function test_잘못된_주문일시_형식은_거부된다(): void
    {
        $payload = $this->payload();
        $payload['ordered_at'] = '형식이 아닌 값';
        $body = json_encode($payload, JSON_UNESCAPED_UNICODE);

        $this->signedPost($body)
            ->assertStatus(422)
            ->assertJsonPath('code', 'INVALID_PAYLOAD');
    }

    public function test_상품_누락_payload_는_거부된다(): void
    {
        $payload = $this->payload();
        unset($payload['product']);
        $body = json_encode($payload, JSON_UNESCAPED_UNICODE);

        $this->signedPost($body)
            ->assertStatus(422)
            ->assertJsonPath('code', 'INVALID_PAYLOAD');
    }
}
