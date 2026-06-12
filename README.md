# total-admin

여러 채널 사이트(영카트, 워드프레스)의 주문을 한 곳에서 받아 관리하는 통합 주문관리 시스템.
채널 사이트는 주문을 자체 저장하지 않고 이 시스템으로 전송만 하고, 주문 수정/상태변경은 여기서만 한다.

Laravel 12 / Filament 4 / MariaDB 11 / Docker Compose

## 로컬 실행

```bash
cp .env.example .env   # DB 비밀번호, 채널 시크릿 채우기
docker compose up -d --build
docker compose exec app composer install
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate
docker compose exec app php artisan make:filament-user
```

관리 화면: http://localhost:8082/admin

## API 인증

채널별 시크릿으로 HMAC 서명한다. 요청 헤더 3개:

- `X-Channel`: 채널 코드 (youngcart, woocommerce)
- `X-Timestamp`: 유닉스 타임스탬프(초), 서버와 ±5분 이내
- `X-Signature`: `hash_hmac('sha256', "{채널}\n{타임스탬프}\n{본문}", 시크릿)`

GET은 본문을 빈 문자열로 서명. 서명한 바이트와 전송 바이트가 일치해야 한다.

```php
// 채널 사이트 쪽
$sig = hash_hmac('sha256', "{$channel}\n{$timestamp}\n{$jsonBody}", $secret);
```

## POST /api/orders — 주문 수신

(channel, channel_order_no) 유니크라서 재전송돼도 중복 저장 안 됨.
응답 201 신규 / 200 중복 / 401 인증 실패 / 422 payload 오류

영카트 payload:

```json
{"od_id":"2606110001","buyer_name":"홍길동","buyer_phone":"010-1234-5678","zipcode":"06236","address1":"서울 강남구 테헤란로 1","address2":"101동 101호","items":[{"name":"상품명","option":"옵션","qty":1,"price":25000}],"payment":{"method":"bank","amount":25000,"paid_at":"2026-06-11 12:00:00"},"pccc":"P123412341234","ordered_at":"2026-06-11 12:00:00"}
```

우커머스 payload:

```json
{"id":1234,"billing":{"last_name":"홍","first_name":"길동","phone":"010-1234-5678"},"shipping":{"postcode":"06236","address_1":"서울 강남구 테헤란로 1","address_2":"101동 101호"},"line_items":[{"name":"상품명","option":"옵션","quantity":1,"price":25000}],"payment_method":"bacs","total":"25000","date_paid":"2026-06-11 12:05:00","pccc":"P123412341234","date_created":"2026-06-11 12:00:00"}
```

## GET /api/orders/{주문번호}?phone={연락처} — 주문 조회

채널 사이트의 고객 주문조회용. 주문번호 + 연락처 일치해야 응답.

## 주문 상태

접수 → 결제확인 → 매입 → 검수 → 발송 (한 단계씩만 전진)

## 채널 추가

어댑터 클래스 작성(`app/Domain/Orders/Adapters/`) + `config/channels.php` 등록 + `.env` 시크릿 추가.

## 운영

```bash
docker compose -f docker-compose.yml -f docker-compose.prod.yml up -d --build
```

운영 .env: `APP_ENV=production`, `APP_DEBUG=false`, `APP_PORT=80`

## 백업

```bash
./scripts/db-backup.sh /home/ubuntu/db-backups
# cron: 15 3 * * * /home/ubuntu/total-admin/scripts/db-backup.sh /home/ubuntu/db-backups >> /home/ubuntu/db-backups/backup.log 2>&1
```

덤프는 서버 밖에도 복사해 둘 것.
