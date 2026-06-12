<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

// 주문 조회. 채널 사이트의 고객 주문조회용
class OrderLookupController extends Controller
{
    public function __invoke(Request $request, string $channelOrderNo): JsonResponse
    {
        $channel = (string) $request->attributes->get('channel');

        $request->validate([
            'phone' => ['required', 'string', 'max:30'],
        ]);

        $order = Order::query()
            ->where('channel', $channel)
            ->where('channel_order_no', $channelOrderNo)
            ->first();

        // 연락처 불일치도 404로 동일 처리
        if ($order === null || ! $this->phoneMatches($order->customer_phone, $request->string('phone'))) {
            return response()->json([
                'ok' => false,
                'code' => 'ORDER_NOT_FOUND',
                'message' => '주문을 찾을 수 없습니다.',
            ], 404);
        }

        return response()->json([
            'ok' => true,
            'order' => [
                'channel_order_no' => $order->channel_order_no,
                'status' => $order->status->value,
                'status_label' => $order->status->getLabel(),
                'customer_name' => $order->customer_name,
                'shipping_postcode' => $order->shipping_postcode,
                'shipping_address1' => $order->shipping_address1,
                'shipping_address2' => $order->shipping_address2,
                'items' => $order->items,
                'payment' => $order->payment,
                'ordered_at' => $order->ordered_at?->toIso8601String(),
                'updated_at' => $order->updated_at?->toIso8601String(),
            ],
        ]);
    }

    private function phoneMatches(string $stored, string $given): bool
    {
        $normalize = fn (string $value) => preg_replace('/\D/', '', $value);

        return $normalize($stored) !== '' && $normalize($stored) === $normalize($given);
    }
}
