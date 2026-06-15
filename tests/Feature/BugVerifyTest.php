<?php

namespace Tests\Feature;

use App\Domain\Orders\Adapters\SajapanAdapter;
use App\Domain\Orders\Adapters\YoungcartAdapter;
use App\Domain\Orders\OrderData;
use Tests\TestCase;

class BugVerifyTest extends TestCase
{
    private function sajapanPayload(array $override = []): array
    {
        return array_merge([
            'order_id' => 1, 'name' => '홍', 'phone' => '010', 'address' => '서울',
            'product' => ['name' => '상품', 'price_jpy' => 100, 'qty' => 1],
        ], $override);
    }

    // 빈/공백 ordered_at 은 now() 가 아니라 null 로 저장
    public function test_빈_orderedAt은_null(): void
    {
        foreach (['', '   '] as $blank) {
            $attrs = (new SajapanAdapter)->normalize($this->sajapanPayload(['ordered_at' => $blank]))
                ->toModelAttributes();
            $this->assertNull($attrs['ordered_at'], "빈값 '{$blank}' 은 null 이어야 함");
        }
    }

    // 잘못된 형식은 422 (InvalidArgumentException)
    public function test_잘못된_orderedAt은_거부(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new SajapanAdapter)->normalize($this->sajapanPayload(['ordered_at' => '형식아님']))
            ->toModelAttributes();
    }

    // 정상 ISO8601 은 앱 타임존으로 보존
    public function test_정상_orderedAt_보존(): void
    {
        $attrs = (new SajapanAdapter)->normalize($this->sajapanPayload(['ordered_at' => '2026-06-12T06:00:00+00:00']))
            ->toModelAttributes();
        $this->assertSame('2026-06-12 15:00:00', $attrs['ordered_at']->format('Y-m-d H:i:s'));
    }

    // 스칼라 items 원소는 422 (TypeError/500 아님)
    public function test_youngcart_스칼라_items는_422(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new YoungcartAdapter)->normalize([
            'od_id' => '1', 'buyer_name' => '홍', 'buyer_phone' => '010', 'address1' => '서울',
            'items' => ['문자열아이템'],
        ]);
    }

    // 빈 items 배열은 422
    public function test_youngcart_빈_items는_422(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new YoungcartAdapter)->normalize([
            'od_id' => '1', 'buyer_name' => '홍', 'buyer_phone' => '010', 'address1' => '서울',
            'items' => [],
        ]);
    }

    // 정상 items 는 통과
    public function test_youngcart_정상_items(): void
    {
        $data = (new YoungcartAdapter)->normalize([
            'od_id' => '1', 'buyer_name' => '홍', 'buyer_phone' => '010', 'address1' => '서울',
            'items' => [['name' => 'A', 'qty' => 2, 'price' => 5000]],
        ]);
        $this->assertSame('A', $data->items[0]['name']);
        $this->assertSame(2, $data->items[0]['qty']);
    }
}
