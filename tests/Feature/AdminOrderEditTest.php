<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Filament\Resources\Orders\Pages\EditOrder;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class AdminOrderEditTest extends TestCase
{
    use RefreshDatabase;

    private function makeOrder(): Order
    {
        return Order::create([
            'channel' => 'sajapan',
            'channel_order_no' => '184',
            'customer_name' => '홍길동',
            'customer_phone' => '01012345678',
            'shipping_address1' => '서울 강남구 테헤란로 1',
            'items' => [[
                'name' => '건담 RG',
                'option' => '한정판',
                'qty' => 1,
                'price' => 15400,
                'source' => 'amiami',
                'url' => 'https://www.amiami.jp/detail?gcode=ABC',
            ]],
            'payment' => ['currency' => 'JPY', 'amount' => 16500],
            'status' => OrderStatus::Received,
            'raw' => [],
        ]);
    }

    // 회귀: 주소만 수정·저장해도 품목의 구매처/URL 이 유지되어야 한다
    public function test_주소_수정_시_품목_구매처와_URL이_보존된다(): void
    {
        $this->actingAs(User::factory()->create());
        $order = $this->makeOrder();

        Livewire::test(EditOrder::class, ['record' => $order->getKey()])
            ->fillForm(['shipping_address2' => '202동 202호'])
            ->call('save')
            ->assertHasNoFormErrors();

        $order->refresh();
        $this->assertSame('202동 202호', $order->shipping_address2);
        $this->assertSame('amiami', $order->items[0]['source']);
        $this->assertSame('https://www.amiami.jp/detail?gcode=ABC', $order->items[0]['url']);
        $this->assertSame('한정판', $order->items[0]['option']);
    }

    // 수정 폼 상태 옵션에 발송이 없어, 송장 없이 발송 상태가 되지 않는다
    public function test_검수_주문_수정_폼에_발송_옵션이_없다(): void
    {
        $this->actingAs(User::factory()->create());
        $order = $this->makeOrder();
        $order->updateQuietly(['status' => OrderStatus::Inspected]);

        Livewire::test(EditOrder::class, ['record' => $order->getKey()])
            ->assertFormFieldExists('status')
            ->assertSet('data.status', OrderStatus::Inspected->value);

        // 검수 상태에서 다음(발송)은 옵션에 없으므로, 발송으로 저장 시도해도 거부
        Livewire::test(EditOrder::class, ['record' => $order->getKey()])
            ->fillForm(['status' => OrderStatus::Shipped->value])
            ->call('save')
            ->assertHasFormErrors(['status']);

        $this->assertSame('inspected', $order->refresh()->status->value);
        $this->assertNull($order->tracking_no);
    }

    // 수정 폼에서 상태를 바꿔도 채널 역동기화가 동작한다
    public function test_수정_폼_상태_변경이_채널로_동기화된다(): void
    {
        config([
            'channels.sajapan.secret' => 'sync-secret',
            'channels.sajapan.callback_url' => 'http://channel.example/sync',
        ]);
        Http::fake(['http://channel.example/sync' => Http::response(['ok' => true])]);

        $this->actingAs(User::factory()->create());
        $order = $this->makeOrder();

        Livewire::test(EditOrder::class, ['record' => $order->getKey()])
            ->fillForm(['status' => OrderStatus::PaymentConfirmed->value])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('payment_confirmed', $order->refresh()->status->value);
        Http::assertSent(fn ($request) => json_decode($request->body(), true)['status'] === 'sa-paid');
    }
}
