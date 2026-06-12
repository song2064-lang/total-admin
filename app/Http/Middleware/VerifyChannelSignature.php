<?php

namespace App\Http\Middleware;

use App\Domain\Orders\ChannelManager;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

// 채널 HMAC 서명 검증. X-Signature = hmac_sha256("채널\n타임스탬프\n본문", secret)
class VerifyChannelSignature
{
    private const TIMESTAMP_TOLERANCE = 300;

    public function __construct(private readonly ChannelManager $channels) {}

    public function handle(Request $request, Closure $next): Response
    {
        $channel = (string) $request->header('X-Channel', '');
        $timestamp = (string) $request->header('X-Timestamp', '');
        $signature = (string) $request->header('X-Signature', '');

        if ($channel === '' || $timestamp === '' || $signature === '') {
            return $this->deny('AUTH_HEADER_MISSING', '인증 헤더가 누락되었습니다.');
        }

        if (! $this->channels->isKnown($channel)) {
            return $this->deny('UNKNOWN_CHANNEL', '등록되지 않은 채널입니다.');
        }

        $secret = $this->channels->secret($channel);

        if (empty($secret)) {
            return $this->deny('CHANNEL_SECRET_NOT_SET', '채널 시크릿키가 설정되지 않았습니다.');
        }

        if (abs(now()->getTimestamp() - (int) $timestamp) > self::TIMESTAMP_TOLERANCE) {
            return $this->deny('TIMESTAMP_EXPIRED', '요청 시각이 허용 범위를 벗어났습니다.');
        }

        $expected = hash_hmac(
            'sha256',
            $channel."\n".$timestamp."\n".$request->getContent(),
            $secret,
        );

        if (! hash_equals($expected, strtolower($signature))) {
            return $this->deny('INVALID_SIGNATURE', '서명이 일치하지 않습니다.');
        }

        $request->attributes->set('channel', $channel);

        return $next($request);
    }

    private function deny(string $code, string $message): Response
    {
        return response()->json([
            'ok' => false,
            'code' => $code,
            'message' => $message,
        ], 401);
    }
}
