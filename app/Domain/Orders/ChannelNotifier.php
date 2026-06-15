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

        // 채널측에 대응 단계가 없는 상태는 상태 필드 생략
        $channelStatus = data_get($config, "status_map.{$statusValue}");

        // 국제·국내 구간 송장을 함께 전송 (현지 구간은 내부용이라 미전송)
        $payload = array_filter([
            'order_no' => $order->channel_order_no,
            'status' => $channelStatus,
            'tracking_intl_carrier' => $snapshot['tracking_intl_carrier'] ?? $order->tracking_intl_carrier,
            'tracking_intl_no' => $snapshot['tracking_intl_no'] ?? $order->tracking_intl_no,
            'tracking_carrier' => $snapshot['tracking_carrier'] ?? $order->tracking_carrier,
            'tracking_no' => $snapshot['tracking_no'] ?? $order->tracking_no,
        ], fn ($value) => $value !== null && $value !== '');

        // 보낼 내용이 주문번호뿐이면 생략
        if (count($payload) <= 1) {
            return true;
        }

        // 변경 시점 시퀀스 (순서 역전 방지)
        $payload['seq'] = $snapshot['seq'] ?? (int) ($order->updated_at?->format('Uu') ?? 0);

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
