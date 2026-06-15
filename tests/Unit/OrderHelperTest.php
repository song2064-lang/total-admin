<?php

namespace Tests\Unit;

use App\Models\Order;
use PHPUnit\Framework\TestCase;

class OrderHelperTest extends TestCase
{
    private function order(array $attrs = []): Order
    {
        return new Order(array_merge([
            'shipping_postcode' => '06236',
            'shipping_address1' => '서울 강남구 테헤란로 1',
            'shipping_address2' => '101동 101호',
        ], $attrs));
    }

    public function test_전체주소를_조합한다(): void
    {
        $this->assertSame(
            '[06236] 서울 강남구 테헤란로 1 101동 101호',
            $this->order()->fullAddress(),
        );
    }

    public function test_상세주소_없으면_생략한다(): void
    {
        $this->assertSame(
            '[06236] 서울 강남구 테헤란로 1',
            $this->order(['shipping_address2' => null])->fullAddress(),
        );
    }

    public function test_CJ대한통운_배송조회_URL(): void
    {
        $order = $this->order(['tracking_carrier' => 'CJ대한통운', 'tracking_no' => '688512349876']);

        $this->assertStringContainsString('cjlogistics.com', $order->trackingUrl());
        $this->assertStringContainsString('688512349876', $order->trackingUrl());
    }

    public function test_미지원_택배사는_네이버검색으로(): void
    {
        $order = $this->order(['tracking_carrier' => '경동택배', 'tracking_no' => '12345']);

        $this->assertStringContainsString('search.naver.com', $order->trackingUrl());
    }

    public function test_송장없으면_배송조회_URL_없음(): void
    {
        $this->assertNull($this->order(['tracking_no' => null])->trackingUrl());
    }

    public function test_EMS_국제송장_조회URL_과_정규화(): void
    {
        $order = $this->order(['tracking_intl_carrier' => 'EMS', 'tracking_intl_no' => 'EE 123-456#789 JP']);

        $url = $order->intlTrackingUrl();
        $this->assertStringContainsString('epost.go.kr', $url);
        // 영숫자만 남아 URL 파라미터 오염 방지
        $this->assertStringContainsString('POSTCODE=EE123456789JP', $url);
        $this->assertStringNotContainsString('#', $url);
        $this->assertStringNotContainsString(' ', $url);
    }

    public function test_국제송장_없으면_조회URL_없음(): void
    {
        $this->assertNull($this->order(['tracking_intl_no' => null])->intlTrackingUrl());
    }
}
