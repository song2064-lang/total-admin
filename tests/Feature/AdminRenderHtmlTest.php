<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Filament\Resources\Orders\Pages\ListOrders;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AdminRenderHtmlTest extends TestCase
{
    use RefreshDatabase;

    private function makeOrder(OrderStatus $status, array $attrs = []): Order
    {
        return Order::create(array_merge([
            'channel' => 'sajapan',
            'channel_order_no' => (string) fake()->unique()->numerify('######'),
            'customer_name' => '홍길동',
            'customer_phone' => '01012345678',
            'shipping_address1' => '서울 강남구 테헤란로 1',
            'items' => [['name' => '건프라 세트', 'qty' => 3, 'price' => 24000, 'source' => 'amiami']],
            'payment' => ['currency' => 'JPY', 'amount' => 73100, 'total_krw' => 698105, 'fx_rate' => 9.55],
            'status' => $status,
            'raw' => [],
        ], $attrs));
    }

    // 실제 렌더 마크업에 탭 배지 카운트와 주문 데이터가 포함되는지
    public function test_목록_렌더_마크업_검증(): void
    {
        $this->actingAs(User::factory()->create());
        $this->makeOrder(OrderStatus::Received, ['customer_name' => '렌더확인', 'channel_order_no' => '999001']);
        $this->makeOrder(OrderStatus::Received);
        $this->makeOrder(OrderStatus::DomesticShipping, ['tracking_no' => '420123456789', 'tracking_carrier' => '한진택배']);

        $html = Livewire::test(ListOrders::class)->html();

        // 탭 라벨
        foreach (['전체', '접수', '결제확인', '매입', '입고', '검수', '국제배송', '국내배송', '배송완료'] as $label) {
            $this->assertStringContainsString($label, $html, "탭 '{$label}' 누락");
        }

        // 주문 데이터가 화면에 출력
        $this->assertStringContainsString('렌더확인', $html);
        $this->assertStringContainsString('999001', $html);
        $this->assertStringContainsString('아미아미', $html);
        $this->assertStringContainsString('건프라 세트', $html);

        // 발송 주문의 송장 (송장 컬럼 토글 노출 시 데이터 존재)
        $this->assertStringContainsString('한진택배', $html);

        // 탭 배지 숫자 영역에 카운트(3=전체) 가 마크업에 존재
        $this->assertMatchesRegularExpression('/접수.*?[>\s]2[<\s]/s', $html, '접수 탭 배지(2)가 마크업에 없음');
    }
}
