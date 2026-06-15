<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ChannelSyncTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'sync-secret';

    private const CALLBACK = 'http://channel.example/wp-json/sa/v1/order-sync';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'channels.sajapan.secret' => self::SECRET,
            'channels.sajapan.callback_url' => self::CALLBACK,
        ]);
    }

    private function makeOrder(): Order
    {
        return Order::create([
            'channel' => 'sajapan',
            'channel_order_no' => '184',
            'customer_name' => '최통합',
            'customer_phone' => '01077778888',
            'shipping_address1' => '경기 성남시 분당구 판교역로 166',
            'items' => [['name' => '건담 RG', 'option' => null, 'qty' => 1, 'price' => 15400]],
            'payment' => ['currency' => 'JPY', 'amount' => 16500],
            'status' => OrderStatus::Received,
            'raw' => [],
        ]);
    }

    public function test_수신_시점에는_역동기화하지_않는다(): void
    {
        Http::fake();
        $this->makeOrder();

        Http::assertNothingSent();
    }

    public function test_상태_변경이_채널_상태로_매핑되어_전송된다(): void
    {
        Http::fake([self::CALLBACK => Http::response(['ok' => true])]);
        $order = $this->makeOrder();

        $order->update(['status' => OrderStatus::PaymentConfirmed]);

        Http::assertSent(function (Request $request) {
            $body = json_decode($request->body(), true);
            $signature = hash_hmac(
                'sha256',
                "sajapan\n".$request->header('X-Timestamp')[0]."\n".$request->body(),
                self::SECRET,
            );

            return $request->url() === self::CALLBACK
                && $request->header('X-Channel')[0] === 'sajapan'
                && hash_equals($signature, $request->header('X-Signature')[0])
                && $body['order_no'] === '184'
                && $body['status'] === 'sa-paid';
        });
    }

    public function test_채널에_대응_단계가_없는_상태는_전송하지_않는다(): void
    {
        Http::fake();
        $order = $this->makeOrder();
        $order->updateQuietly(['status' => OrderStatus::Purchased]);

        // 검수(inspected)는 채널측 대응 상태가 null — 송장도 없으므로 전송 생략
        $order->update(['status' => OrderStatus::Inspected]);

        Http::assertNothingSent();
    }

    public function test_발송_처리_시_송장과_함께_전송된다(): void
    {
        Http::fake([self::CALLBACK => Http::response(['ok' => true])]);
        $order = $this->makeOrder();
        $order->updateQuietly(['status' => OrderStatus::Inspected]);

        $order->update([
            'status' => OrderStatus::Shipped,
            'tracking_carrier' => 'CJ대한통운',
            'tracking_no' => '123456789012',
        ]);

        Http::assertSent(function (Request $request) {
            $body = json_decode($request->body(), true);

            return $body['status'] === 'sa-shipping'
                && $body['tracking_carrier'] === 'CJ대한통운'
                && $body['tracking_no'] === '123456789012';
        });
    }

    public function test_콜백_미설정_채널은_동기화하지_않는다(): void
    {
        config(['channels.sajapan.callback_url' => null]);
        Http::fake();
        $order = $this->makeOrder();

        $order->update(['status' => OrderStatus::PaymentConfirmed]);

        Http::assertNothingSent();
    }

    // 막 변경(빠른 연속): 각 단계가 변경 시점 상태로 전송되어 중간 단계 누락 없음
    public function test_연속_변경시_각_단계가_스냅샷으로_전송(): void
    {
        $sent = [];
        Http::fake(function (Request $request) use (&$sent) {
            $sent[] = json_decode($request->body(), true)['status'] ?? null;

            return Http::response(['ok' => true]);
        });

        $order = $this->makeOrder();
        $order->update(['status' => OrderStatus::PaymentConfirmed]);
        $order->update(['status' => OrderStatus::Purchased]);
        $order->update(['status' => OrderStatus::Inspected]);

        // 결제확인·매입은 전송, 검수는 채널 대응 없어 미전송
        $this->assertContains('sa-paid', $sent);
        $this->assertContains('sa-purchased', $sent);
        $this->assertNotContains('sa-shipped', $sent);
    }
}
