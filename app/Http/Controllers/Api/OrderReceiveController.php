<?php

namespace App\Http\Controllers\Api;

use App\Domain\Orders\ChannelManager;
use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

// 주문 수신. 같은 주문 재전송시 중복 저장 안 함
class OrderReceiveController extends Controller
{
    public function __invoke(Request $request, ChannelManager $channels): JsonResponse
    {
        $channel = (string) $request->attributes->get('channel');
        $payload = $request->json()->all();

        try {
            $data = $channels->adapter($channel)->normalize($payload);
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'ok' => false,
                'code' => 'INVALID_PAYLOAD',
                'message' => $e->getMessage(),
            ], 422);
        }

        $existing = Order::query()
            ->where('channel', $data->channel)
            ->where('channel_order_no', $data->channelOrderNo)
            ->first();

        if ($existing !== null) {
            return $this->accepted($existing, duplicated: true);
        }

        try {
            $order = Order::create($data->toModelAttributes());
        } catch (UniqueConstraintViolationException) {
            // 동시 재전송 경합
            $order = Order::query()
                ->where('channel', $data->channel)
                ->where('channel_order_no', $data->channelOrderNo)
                ->firstOrFail();

            return $this->accepted($order, duplicated: true);
        }

        Log::info('주문 수신', [
            'order_id' => $order->id,
            'channel' => $order->channel,
            'channel_order_no' => $order->channel_order_no,
            'customer_name' => $order->customer_name,
            'customer_phone' => $order->customer_phone,
            'pccc' => $order->pccc,
        ]);

        return $this->accepted($order, duplicated: false);
    }

    private function accepted(Order $order, bool $duplicated): JsonResponse
    {
        return response()->json([
            'ok' => true,
            'duplicated' => $duplicated,
            'order_id' => $order->id,
            'status' => $order->status->value,
        ], $duplicated ? 200 : 201);
    }
}
