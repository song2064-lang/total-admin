<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Filament\Resources\Orders\Pages\ListOrders;
use App\Jobs\SyncOrderToChannel;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;

class OrderRevertTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create());
        config(['channels.sajapan.callback_url' => null]);
    }

    private function order(OrderStatus $status): Order
    {
        return Order::create([
            'channel' => 'sajapan',
            'channel_order_no' => (string) fake()->unique()->numerify('######'),
            'customer_name' => '홍길동',
            'customer_phone' => '01012345678',
            'shipping_address1' => '서울',
            'items' => [['name' => '상품', 'qty' => 1, 'price' => 1000]],
            'payment' => ['currency' => 'JPY', 'amount' => 1000],
            'status' => $status,
            'raw' => [],
        ]);
    }

    // 상태 변경 시 동기화 잡이 큐로 dispatch 되는가 (동기 아님)
    public function test_상태_변경시_동기화잡_dispatch(): void
    {
        Queue::fake();
        $order = $this->order(OrderStatus::Received);

        $order->update(['status' => OrderStatus::PaymentConfirmed]);

        Queue::assertPushed(SyncOrderToChannel::class, fn ($job) => $job->orderId === $order->id);
    }

    public function test_수신시에는_dispatch_안함(): void
    {
        Queue::fake();
        $this->order(OrderStatus::Received);

        Queue::assertNotPushed(SyncOrderToChannel::class);
    }

    public function test_OrderStatus_prev(): void
    {
        $this->assertSame(OrderStatus::Received, OrderStatus::PaymentConfirmed->prev());
        $this->assertSame(OrderStatus::Warehoused, OrderStatus::Inspected->prev());
        $this->assertNull(OrderStatus::Received->prev());
    }

    public function test_매입주문_역행_가능(): void
    {
        $o = $this->order(OrderStatus::Purchased);

        Livewire::test(ListOrders::class)->callTableAction('revert_status', $o);

        $this->assertSame('payment_confirmed', $o->refresh()->status->value);
    }

    public function test_접수주문은_역행_버튼_없음(): void
    {
        $o = $this->order(OrderStatus::Received);

        Livewire::test(ListOrders::class)->assertTableActionHidden('revert_status', $o);
    }

    public function test_배송완료주문은_역행_버튼_없음(): void
    {
        $o = $this->order(OrderStatus::Delivered);

        Livewire::test(ListOrders::class)->assertTableActionHidden('revert_status', $o);
    }

    public function test_역행도_이력에_남는다(): void
    {
        $o = $this->order(OrderStatus::Inspected);

        Livewire::test(ListOrders::class)->callTableAction('revert_status', $o);

        $o->refresh();
        $this->assertSame('warehoused', $o->status->value);
        $log = $o->statusLogs()->first();
        $this->assertSame('inspected', $log->from_status->value);
        $this->assertSame('warehoused', $log->to_status->value);
    }
}
