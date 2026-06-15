<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Filament\Resources\Orders\Pages\ListOrders;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AdminOrderListTest extends TestCase
{
    use RefreshDatabase;

    private function makeOrder(array $attrs = []): Order
    {
        return Order::create(array_merge([
            'channel' => 'sajapan',
            'channel_order_no' => (string) fake()->unique()->numerify('######'),
            'customer_name' => '홍길동',
            'customer_phone' => '01012345678',
            'shipping_address1' => '서울 강남구 테헤란로 1',
            'items' => [['name' => '상품', 'option' => null, 'qty' => 1, 'price' => 1000, 'source' => 'amiami']],
            'payment' => ['currency' => 'JPY', 'amount' => 1000],
            'status' => OrderStatus::Received,
            'raw' => [],
        ], $attrs));
    }

    public function test_연락처로_검색된다(): void
    {
        $this->actingAs(User::factory()->create());
        $target = $this->makeOrder(['customer_name' => '김타겟', 'customer_phone' => '01099998888']);
        $this->makeOrder(['customer_name' => '이기타', 'customer_phone' => '01011112222']);

        Livewire::test(ListOrders::class)
            ->set('tableSearch', '9999-8888')
            ->assertCanSeeTableRecords([$target])
            ->assertCountTableRecords(1);
    }

    public function test_송장번호로_검색된다(): void
    {
        $this->actingAs(User::factory()->create());
        $target = $this->makeOrder(['tracking_no' => '688512349876', 'tracking_carrier' => 'CJ대한통운']);
        $this->makeOrder();

        Livewire::test(ListOrders::class)
            ->set('tableSearch', '688512349876')
            ->assertCanSeeTableRecords([$target])
            ->assertCountTableRecords(1);
    }

    public function test_구매처_필터(): void
    {
        $this->actingAs(User::factory()->create());
        $amiami = $this->makeOrder(['items' => [['name' => 'A', 'qty' => 1, 'price' => 1, 'source' => 'amiami']]]);
        $mercari = $this->makeOrder(['items' => [['name' => 'B', 'qty' => 1, 'price' => 1, 'source' => 'mercari']]]);

        Livewire::test(ListOrders::class)
            ->filterTable('source', 'mercari')
            ->assertCanSeeTableRecords([$mercari])
            ->assertCanNotSeeTableRecords([$amiami]);
    }

    public function test_일괄_다음단계_변경(): void
    {
        $this->actingAs(User::factory()->create());
        $a = $this->makeOrder(['status' => OrderStatus::Received]);
        $b = $this->makeOrder(['status' => OrderStatus::Received]);

        Livewire::test(ListOrders::class)
            ->callTableBulkAction('bulk_advance', [$a, $b]);

        $this->assertSame('payment_confirmed', $a->refresh()->status->value);
        $this->assertSame('payment_confirmed', $b->refresh()->status->value);
    }

    public function test_관리자_메모_저장(): void
    {
        $this->actingAs(User::factory()->create());
        $order = $this->makeOrder();

        Livewire::test(ListOrders::class)
            ->callTableAction('edit_note', $order, ['admin_note' => '재고 확인 필요']);

        $this->assertSame('재고 확인 필요', $order->refresh()->admin_note);
    }
}
