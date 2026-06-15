<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Filament\Resources\Orders\OrderResource;
use App\Filament\Resources\Orders\Pages\ListOrders;
use App\Filament\Resources\Orders\Pages\ViewOrder;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AdminInteractionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create());
        config(['channels.sajapan.callback_url' => null]);
    }

    private function order(OrderStatus $status, array $attrs = []): Order
    {
        return Order::create(array_merge([
            'channel' => 'sajapan',
            'channel_order_no' => (string) fake()->unique()->numerify('######'),
            'customer_name' => '홍길동',
            'customer_phone' => '01012345678',
            'shipping_address1' => '서울 강남구 테헤란로 1',
            'items' => [['name' => '상품', 'qty' => 1, 'price' => 1000, 'source' => 'amiami']],
            'payment' => ['currency' => 'JPY', 'amount' => 1000],
            'status' => $status,
            'raw' => [],
        ], $attrs));
    }

    // 검색 적용 카운트 (getAllTableRecordsCount 는 매 호출 새 쿼리)
    private function searchCount(string $search): int
    {
        return Livewire::test(ListOrders::class)
            ->searchTable($search)
            ->instance()
            ->getAllTableRecordsCount();
    }

    public function test_검색_주문번호(): void
    {
        $this->order(OrderStatus::Received, ['channel_order_no' => 'ORD-7788']);
        $this->order(OrderStatus::Received);

        $this->assertSame(1, $this->searchCount('ORD-7788'));
    }

    public function test_검색_주문자명(): void
    {
        $this->order(OrderStatus::Received, ['customer_name' => '특이한이름']);
        $this->order(OrderStatus::Received);

        $this->assertSame(1, $this->searchCount('특이한이름'));
    }

    public function test_검색_연락처_하이픈무관(): void
    {
        $this->order(OrderStatus::Received, ['customer_phone' => '01055667788']);
        $this->order(OrderStatus::Received);

        $this->assertSame(1, $this->searchCount('5566-7788'));
    }

    public function test_검색_송장번호(): void
    {
        $this->order(OrderStatus::DomesticShipping, ['tracking_no' => '770088991122', 'tracking_carrier' => 'CJ대한통운']);
        $this->order(OrderStatus::Received);

        $this->assertSame(1, $this->searchCount('770088991122'));
    }

    public function test_필터_채널(): void
    {
        config(['channels.youngcart.secret' => 'x']);
        $sa = $this->order(OrderStatus::Received);
        $yc = $this->order(OrderStatus::Received, ['channel' => 'youngcart']);

        Livewire::test(ListOrders::class)->filterTable('channel', 'youngcart')
            ->assertCanSeeTableRecords([$yc])->assertCanNotSeeTableRecords([$sa]);
    }

    public function test_필터_구매처(): void
    {
        $am = $this->order(OrderStatus::Received, ['items' => [['name' => 'A', 'qty' => 1, 'price' => 1, 'source' => 'amiami']]]);
        $me = $this->order(OrderStatus::Received, ['items' => [['name' => 'B', 'qty' => 1, 'price' => 1, 'source' => 'mercari']]]);

        Livewire::test(ListOrders::class)->filterTable('source', 'amiami')
            ->assertCanSeeTableRecords([$am])->assertCanNotSeeTableRecords([$me]);
    }

    public function test_필터_주문일_기간(): void
    {
        $old = $this->order(OrderStatus::Received, ['ordered_at' => '2026-01-01 10:00:00']);
        $new = $this->order(OrderStatus::Received, ['ordered_at' => '2026-06-12 10:00:00']);

        Livewire::test(ListOrders::class)
            ->filterTable('ordered_at', ['from' => '2026-06-01', 'until' => '2026-06-30'])
            ->assertCanSeeTableRecords([$new])->assertCanNotSeeTableRecords([$old]);
    }

    public function test_탭_상태별_필터링(): void
    {
        $received = $this->order(OrderStatus::Received);
        $shipped = $this->order(OrderStatus::DomesticShipping, ['tracking_no' => '1', 'tracking_carrier' => 'CJ대한통운']);

        Livewire::test(ListOrders::class)->set('activeTab', 'domestic_shipping')
            ->assertCanSeeTableRecords([$shipped])->assertCanNotSeeTableRecords([$received]);
    }

    public function test_정렬_주문번호(): void
    {
        $a = $this->order(OrderStatus::Received);
        $b = $this->order(OrderStatus::Received);

        Livewire::test(ListOrders::class)->sortTable('channel_order_no')
            ->assertCanSeeTableRecords([$a, $b], inOrder: true);
    }

    public function test_접수주문은_다음단계_버튼_노출(): void
    {
        $o = $this->order(OrderStatus::Received);
        Livewire::test(ListOrders::class)
            ->assertTableActionVisible('advance_status', $o)
            ->assertTableActionHidden('ship_international', $o)
            ->assertTableActionHidden('ship_domestic', $o);
    }

    public function test_검수주문은_국제배송_버튼_노출(): void
    {
        $o = $this->order(OrderStatus::Inspected);
        Livewire::test(ListOrders::class)
            ->assertTableActionVisible('ship_international', $o)
            ->assertTableActionHidden('ship_domestic', $o)
            ->assertTableActionHidden('advance_status', $o);
    }

    public function test_국제배송주문은_국내배송_버튼_노출(): void
    {
        $o = $this->order(OrderStatus::InternationalShipping, ['tracking_intl_no' => '1', 'tracking_intl_carrier' => 'EMS']);
        Livewire::test(ListOrders::class)
            ->assertTableActionVisible('ship_domestic', $o)
            ->assertTableActionHidden('ship_international', $o)
            ->assertTableActionHidden('advance_status', $o);
    }

    public function test_배송완료주문은_진행_버튼_없음(): void
    {
        $o = $this->order(OrderStatus::Delivered, ['tracking_no' => '1', 'tracking_carrier' => 'CJ대한통운']);
        Livewire::test(ListOrders::class)
            ->assertTableActionHidden('advance_status', $o)
            ->assertTableActionHidden('ship_international', $o)
            ->assertTableActionHidden('ship_domestic', $o);
    }

    public function test_국제배송처리_송장없으면_거부(): void
    {
        $o = $this->order(OrderStatus::Inspected);

        Livewire::test(ListOrders::class)
            ->callTableAction('ship_international', $o, ['tracking_intl_carrier' => 'EMS', 'tracking_intl_no' => ''])
            ->assertHasTableActionErrors(['tracking_intl_no']);

        $this->assertSame('inspected', $o->refresh()->status->value);
    }

    public function test_국제배송처리_정상시_상태와_송장_저장(): void
    {
        $o = $this->order(OrderStatus::Inspected);

        Livewire::test(ListOrders::class)
            ->callTableAction('ship_international', $o, ['tracking_intl_carrier' => 'EMS', 'tracking_intl_no' => 'EE123456789JP'])
            ->assertHasNoTableActionErrors();

        $o->refresh();
        $this->assertSame('international_shipping', $o->status->value);
        $this->assertSame('EMS', $o->tracking_intl_carrier);
        $this->assertSame('EE123456789JP', $o->tracking_intl_no);
    }

    public function test_국내배송처리_정상시_상태와_송장_저장(): void
    {
        $o = $this->order(OrderStatus::InternationalShipping, ['tracking_intl_no' => '1', 'tracking_intl_carrier' => 'EMS']);

        Livewire::test(ListOrders::class)
            ->callTableAction('ship_domestic', $o, ['tracking_carrier' => '롯데택배', 'tracking_no' => '330011220033'])
            ->assertHasNoTableActionErrors();

        $o->refresh();
        $this->assertSame('domestic_shipping', $o->status->value);
        $this->assertSame('롯데택배', $o->tracking_carrier);
        $this->assertSame('330011220033', $o->tracking_no);
    }

    public function test_일괄변경_배송단계주문은_건너뜀(): void
    {
        $received = $this->order(OrderStatus::Received);
        // 검수 다음은 국제배송(송장 필요) — 일괄 전진 대상 아님
        $inspected = $this->order(OrderStatus::Inspected);

        Livewire::test(ListOrders::class)
            ->callTableBulkAction('bulk_advance', [$received, $inspected]);

        $this->assertSame('payment_confirmed', $received->refresh()->status->value);
        $this->assertSame('inspected', $inspected->refresh()->status->value); // 변화 없음
    }

    public function test_국내배송처리_송장없으면_거부(): void
    {
        $o = $this->order(OrderStatus::InternationalShipping, ['tracking_intl_no' => '1', 'tracking_intl_carrier' => 'EMS']);

        Livewire::test(ListOrders::class)
            ->callTableAction('ship_domestic', $o, ['tracking_carrier' => 'CJ대한통운', 'tracking_no' => ''])
            ->assertHasTableActionErrors(['tracking_no']);

        $this->assertSame('international_shipping', $o->refresh()->status->value);
    }

    // 접수부터 배송완료까지 실제 액션만으로 완주, 게이트·이력 검증
    public function test_전체_라이프사이클_액션으로_완주(): void
    {
        $o = $this->order(OrderStatus::Received);
        $t = Livewire::test(ListOrders::class);

        $t->callTableAction('advance_status', $o);
        $this->assertSame('payment_confirmed', $o->refresh()->status->value);
        $t->callTableAction('advance_status', $o);
        $this->assertSame('purchased', $o->refresh()->status->value);
        $t->callTableAction('advance_status', $o);
        $this->assertSame('warehoused', $o->refresh()->status->value);
        $t->callTableAction('advance_status', $o);
        $this->assertSame('inspected', $o->refresh()->status->value);

        // 검수 다음은 국제배송: advance_status 로는 못 넘어감
        $t->assertTableActionHidden('advance_status', $o->refresh());

        $t->callTableAction('ship_international', $o, ['tracking_intl_carrier' => 'EMS', 'tracking_intl_no' => 'EE777JP']);
        $this->assertSame('international_shipping', $o->refresh()->status->value);

        $t->callTableAction('ship_domestic', $o, ['tracking_carrier' => '한진택배', 'tracking_no' => '550011223344']);
        $this->assertSame('domestic_shipping', $o->refresh()->status->value);

        $t->callTableAction('advance_status', $o);
        $o->refresh();
        $this->assertSame('delivered', $o->status->value);
        $this->assertSame('EE777JP', $o->tracking_intl_no);
        $this->assertSame('550011223344', $o->tracking_no);

        // 최초 수신 1 + 전이 7 = 8건 이력
        $this->assertSame(8, $o->statusLogs()->count());
    }

    public function test_주문_생성_불가(): void
    {
        $this->assertFalse(OrderResource::canCreate());
    }

    public function test_발송주문_상세에_배송정보와_조회링크(): void
    {
        $o = $this->order(OrderStatus::DomesticShipping, ['tracking_no' => '688512349876', 'tracking_carrier' => 'CJ대한통운']);

        $html = Livewire::test(ViewOrder::class, ['record' => $o->getKey()])->html();

        $this->assertStringContainsString('국내 배송', $html);
        $this->assertStringContainsString('CJ대한통운', $html);
        $this->assertStringContainsString('688512349876', $html);
        $this->assertStringContainsString('cjlogistics.com', $html);
    }

    public function test_없는_검색어는_결과없음(): void
    {
        $this->order(OrderStatus::Received);

        $this->assertSame(0, $this->searchCount('존재하지않는검색어xyz'));
    }
}
