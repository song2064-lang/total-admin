<?php

namespace App\Domain\Orders;

use App\Models\Order;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

// 상태·송장 변경을 채널 사이트로 역동기화
class ChannelNotifier
{
    // $snapshot 으로 변경 시점의 상태·송장을 받으면 그 값으로 전송.
    // 막 변경 시 잡이 최신 상태만 읽어 중간 단계가 누락되는 것을 방지.
    public function notify(Order $order, array $snapshot = []): bool
    {
        $config = config("channels.{$order->channel}");
        $url = $config['callback_url'] ?? null;
        $secret = $config['secret'] ?? null;

        if (empty($url) || empty($secret)) {
            return true; // 콜백 미설정 채널은 동기화 대상 아님
        }

        $statusValue = $snapshot['status'] ?? $order->status->value;
        $carrier = $snapshot['tracking_carrier'] ?? $order->tracking_carrier;
        $trackingNo = $snapshot['tracking_no'] ?? $order->tracking_no;

        // 채널측에 대응 단계가 없는 상태는 상태 필드 생략
        $channelStatus = data_get($config, "status_map.{$statusValue}");

        $payload = array_filter([
            'order_no' => $order->channel_order_no,
            'status' => $channelStatus,
            'tracking_carrier' => $carrier,
            'tracking_no' => $trackingNo,
        ], fn ($value) => $value !== null);

        // 보낼 내용이 주문번호뿐이면 생략
        if (count($payload) <= 1) {
            return true;
        }

        $body = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $timestamp = now()->getTimestamp();
        $signature = hash_hmac('sha256', $order->channel."\n".$timestamp."\n".$body, $secret);

        try {
            // 일시 네트워크 오류 흡수 (200ms 간격 1회 재시도)
            $response = Http::timeout(4)
                ->retry(2, 200, throw: false)
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'X-Channel' => $order->channel,
                    'X-Timestamp' => (string) $timestamp,
                    'X-Signature' => $signature,
                ])
                ->withBody($body, 'application/json')
                ->post($url);

            if ($response->successful()) {
                return true;
            }

            Log::warning('채널 동기화 실패', [
                'order_id' => $order->id,
                'channel' => $order->channel,
                'http' => $response->status(),
                'body' => mb_substr($response->body(), 0, 200),
            ]);
        } catch (\Throwable $e) {
            Log::warning('채널 동기화 오류', [
                'order_id' => $order->id,
                'channel' => $order->channel,
                'error' => $e->getMessage(),
            ]);
        }

        return false;
    }
}
