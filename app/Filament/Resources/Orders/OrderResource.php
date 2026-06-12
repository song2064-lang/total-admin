<?php

namespace App\Filament\Resources\Orders;

use App\Domain\Orders\ChannelManager;
use App\Enums\OrderStatus;
use App\Filament\Resources\Orders\Pages\EditOrder;
use App\Filament\Resources\Orders\Pages\ListOrders;
use App\Filament\Resources\Orders\Pages\ViewOrder;
use App\Models\Order;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class OrderResource extends Resource
{
    protected static ?string $model = Order::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-shopping-bag';

    protected static ?string $modelLabel = '주문';

    protected static ?string $pluralModelLabel = '주문';

    protected static ?string $navigationLabel = '주문 관리';

    // 주문 생성은 API 수신으로만
    public static function canCreate(): bool
    {
        return false;
    }

    // 구매처 코드 → 표시명
    public static function sourceLabel(?string $source): ?string
    {
        if ($source === null || $source === '') {
            return null;
        }

        return match ($source) {
            'mercari' => '메루카리',
            'amiami' => '아미아미',
            'yahoo_auctions' => '야후옥션',
            'rakuten' => '라쿠텐',
            default => $source,
        };
    }

    // 엔화 금액 + 주문 당시 환율 기준 원화 환산 병기
    public static function jpyWithKrw(Order $record, ?int $jpy): ?string
    {
        if ($jpy === null) {
            return null;
        }

        $formatted = number_format($jpy).$record->currencyUnit();
        $rate = data_get($record->payment, 'fx_rate');

        if ($rate === null || $record->currencyUnit() !== '엔') {
            return $formatted;
        }

        return $formatted.' (약 '.number_format((int) round($jpy * (float) $rate)).'원)';
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label('번호')
                    ->sortable(),
                TextColumn::make('channel')
                    ->label('채널')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => config("channels.{$state}.label", $state)),
                TextColumn::make('channel_order_no')
                    ->label('채널 주문번호')
                    ->searchable()
                    ->copyable(),
                TextColumn::make('items_summary')
                    ->label('상품')
                    ->state(function (Order $record) {
                        $items = $record->items ?? [];
                        $first = $items[0]['name'] ?? '-';
                        $extra = count($items) - 1;

                        return $extra > 0 ? "{$first} 외 {$extra}건" : $first;
                    })
                    ->limit(28)
                    ->tooltip(fn (Order $record) => collect($record->items)->pluck('name')->implode(', ')),
                TextColumn::make('source')
                    ->label('구매처')
                    ->state(fn (Order $record) => static::sourceLabel(data_get($record->items, '0.source')))
                    ->badge()
                    ->color('gray')
                    ->placeholder('-'),
                TextColumn::make('customer_name')
                    ->label('주문자')
                    ->searchable(),
                TextColumn::make('customer_phone')
                    ->label('연락처')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('status')
                    ->label('상태')
                    ->badge(),
                TextColumn::make('payment_amount')
                    ->label('결제금액')
                    ->state(fn (Order $record) => $record->paymentAmount())
                    ->numeric()
                    ->suffix(fn (Order $record) => $record->currencyUnit()),
                TextColumn::make('ordered_at')
                    ->label('주문일시')
                    ->dateTime('Y-m-d H:i')
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label('수신일시')
                    ->dateTime('Y-m-d H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('channel')
                    ->label('채널')
                    ->options(ChannelManager::labels()),
                SelectFilter::make('status')
                    ->label('상태')
                    ->options(OrderStatus::options()),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                static::advanceStatusAction(),
            ])
            ->defaultSort('created_at', 'desc');
    }

    // 상태 한 단계 전진 (목록/상세 공용)
    public static function advanceStatusAction(): Action
    {
        return Action::make('advance_status')
            ->label('다음 단계')
            ->icon('heroicon-o-arrow-right-circle')
            ->color('primary')
            ->visible(fn (Order $record) => $record->status->next() !== null)
            ->requiresConfirmation()
            ->modalHeading(fn (Order $record) => sprintf(
                '상태 변경: %s → %s',
                $record->status->getLabel(),
                $record->status->next()?->getLabel(),
            ))
            ->modalSubmitActionLabel('변경')
            ->modalCancelActionLabel('취소')
            ->action(function (Order $record): void {
                $next = $record->status->next();

                if ($next === null || ! $record->status->canTransitionTo($next)) {
                    return;
                }

                $record->update(['status' => $next]);

                Notification::make()
                    ->title("상태가 '{$next->getLabel()}'(으)로 변경되었습니다.")
                    ->success()
                    ->send();
            });
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('주문 정보')
                ->columns(3)
                ->schema([
                    TextEntry::make('channel')
                        ->label('채널')
                        ->badge()
                        ->formatStateUsing(fn (string $state) => config("channels.{$state}.label", $state)),
                    TextEntry::make('channel_order_no')
                        ->label('채널 주문번호')
                        ->copyable(),
                    TextEntry::make('status')
                        ->label('상태')
                        ->badge(),
                    TextEntry::make('member')
                        ->label('회원')
                        ->state(fn (Order $record) => data_get($record->raw, 'member'))
                        ->placeholder('비회원'),
                    TextEntry::make('ordered_at')
                        ->label('주문일시')
                        ->dateTime('Y-m-d H:i:s'),
                    TextEntry::make('created_at')
                        ->label('수신일시')
                        ->dateTime('Y-m-d H:i:s'),
                    TextEntry::make('updated_at')
                        ->label('최종 변경')
                        ->dateTime('Y-m-d H:i:s'),
                ]),

            Section::make('고객 / 배송지')
                ->columns(3)
                ->schema([
                    TextEntry::make('customer_name')->label('주문자'),
                    TextEntry::make('customer_phone')->label('연락처'),
                    TextEntry::make('pccc')
                        ->label('개인통관고유부호')
                        ->placeholder('미입력'),
                    TextEntry::make('shipping_postcode')
                        ->label('우편번호')
                        ->placeholder('-'),
                    TextEntry::make('shipping_address1')->label('기본주소'),
                    TextEntry::make('shipping_address2')
                        ->label('상세주소')
                        ->placeholder('-'),
                    TextEntry::make('order_memo')
                        ->label('요청 메모')
                        ->state(fn (Order $record) => data_get($record->raw, 'memo'))
                        ->visible(fn (Order $record) => filled(data_get($record->raw, 'memo')))
                        ->columnSpanFull(),
                ]),

            Section::make('품목')
                ->schema([
                    RepeatableEntry::make('items')
                        ->hiddenLabel()
                        ->columns(6)
                        ->schema([
                            TextEntry::make('name')
                                ->label('상품명')
                                ->columnSpan(2),
                            TextEntry::make('source')
                                ->label('구매처')
                                ->formatStateUsing(fn (?string $state) => static::sourceLabel($state))
                                ->badge()
                                ->color('gray')
                                ->placeholder('-'),
                            TextEntry::make('qty')->label('수량')->suffix('개'),
                            TextEntry::make('price')
                                ->label('단가')
                                ->numeric()
                                ->suffix(fn (Order $record) => $record->currencyUnit()),
                            TextEntry::make('url')
                                ->label('상품 페이지')
                                ->formatStateUsing(fn () => '바로가기 ↗')
                                ->url(fn (?string $state) => $state)
                                ->openUrlInNewTab()
                                ->color('info')
                                ->placeholder('-'),
                        ]),
                ]),

            Section::make('결제정보')
                ->columns(2)
                ->schema([
                    TextEntry::make('payment_subtotal')
                        ->label('상품금액')
                        ->state(fn (Order $record) => static::jpyWithKrw($record, data_get($record->payment, 'subtotal_jpy')))
                        ->visible(fn (Order $record) => data_get($record->payment, 'subtotal_jpy') !== null),
                    TextEntry::make('fee_agency')
                        ->label('대행수수료')
                        ->state(fn (Order $record) => static::jpyWithKrw($record, data_get($record->payment, 'fees_jpy.agency')))
                        ->visible(fn (Order $record) => data_get($record->payment, 'fees_jpy.agency') !== null),
                    TextEntry::make('fee_remit')
                        ->label('송금수수료')
                        ->state(fn (Order $record) => static::jpyWithKrw($record, data_get($record->payment, 'fees_jpy.remit')))
                        ->visible(fn (Order $record) => data_get($record->payment, 'fees_jpy.remit') !== null),
                    TextEntry::make('fee_inspection')
                        ->label('검수')
                        ->state(function (Order $record) {
                            $label = data_get($record->payment, 'inspection');
                            $fee = data_get($record->payment, 'fees_jpy.inspection');

                            if ($label === null) {
                                return null;
                            }

                            return $fee !== null
                                ? $label.' / '.static::jpyWithKrw($record, (int) $fee)
                                : $label;
                        })
                        ->visible(fn (Order $record) => data_get($record->payment, 'inspection') !== null),
                    TextEntry::make('payment_amount')
                        ->label('합계')
                        ->state(fn (Order $record) => static::jpyWithKrw($record, $record->paymentAmount()))
                        ->weight('bold')
                        ->placeholder('-'),
                    TextEntry::make('fx_rate')
                        ->label('적용 환율 (주문 당시)')
                        ->state(function (Order $record) {
                            $rate = data_get($record->payment, 'fx_rate');

                            return $rate !== null ? '100엔 = '.number_format((float) $rate * 100, 0).'원' : null;
                        })
                        ->visible(fn (Order $record) => data_get($record->payment, 'fx_rate') !== null),
                    TextEntry::make('points_used')
                        ->label('적립금 사용')
                        ->state(fn (Order $record) => '-'.number_format((int) data_get($record->payment, 'points_used', 0)).'원')
                        ->color(fn (Order $record) => (int) data_get($record->payment, 'points_used', 0) > 0 ? 'danger' : 'gray')
                        ->visible(fn (Order $record) => data_get($record->payment, 'points_used') !== null),
                    TextEntry::make('final_krw')
                        ->label('최종 결제 예정액')
                        ->state(function (Order $record) {
                            $totalKrw = data_get($record->payment, 'total_krw');

                            if ($totalKrw === null) {
                                return null;
                            }

                            $points = (int) data_get($record->payment, 'points_used', 0);

                            return number_format(max(0, (int) $totalKrw - $points)).'원';
                        })
                        ->weight('bold')
                        ->color('primary')
                        ->visible(fn (Order $record) => data_get($record->payment, 'total_krw') !== null),
                    TextEntry::make('payment_method')
                        ->label('결제수단')
                        ->state(fn (Order $record) => data_get($record->payment, 'method'))
                        ->placeholder('미결제'),
                    TextEntry::make('payment_paid_at')
                        ->label('결제일시')
                        ->state(fn (Order $record) => data_get($record->payment, 'paid_at'))
                        ->placeholder('미결제'),
                ]),

            Section::make('상태 이력')
                ->schema([
                    RepeatableEntry::make('statusLogs')
                        ->hiddenLabel()
                        ->columns(4)
                        ->schema([
                            TextEntry::make('created_at')
                                ->label('일시')
                                ->dateTime('Y-m-d H:i:s'),
                            TextEntry::make('from_status')
                                ->label('이전 상태')
                                ->badge()
                                ->placeholder('최초 수신'),
                            TextEntry::make('to_status')
                                ->label('변경 상태')
                                ->badge(),
                            TextEntry::make('user.name')
                                ->label('처리자')
                                ->placeholder('시스템(API)'),
                        ]),
                ]),

            Section::make('원본 payload')
                ->collapsed()
                ->schema([
                    TextEntry::make('raw')
                        ->hiddenLabel()
                        ->state(fn (Order $record) => json_encode(
                            $record->raw,
                            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
                        ))
                        ->copyable(),
                ]),
        ]);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('상태')
                ->schema([
                    Select::make('status')
                        ->label('주문 상태')
                        ->options(function (?Order $record) {
                            if ($record === null) {
                                return OrderStatus::options();
                            }

                            // 현재 상태 유지 또는 다음 단계만
                            $options = [$record->status->value => $record->status->getLabel()];

                            if ($next = $record->status->next()) {
                                $options[$next->value] = $next->getLabel();
                            }

                            return $options;
                        })
                        ->required()
                        ->native(false),
                ]),

            Section::make('고객 / 배송지')
                ->columns(2)
                ->schema([
                    TextInput::make('customer_name')
                        ->label('주문자')
                        ->required()
                        ->maxLength(50),
                    TextInput::make('customer_phone')
                        ->label('연락처')
                        ->required()
                        ->maxLength(30),
                    TextInput::make('pccc')
                        ->label('개인통관고유부호')
                        ->maxLength(20),
                    TextInput::make('shipping_postcode')
                        ->label('우편번호')
                        ->maxLength(10),
                    TextInput::make('shipping_address1')
                        ->label('기본주소')
                        ->required()
                        ->maxLength(255)
                        ->columnSpanFull(),
                    TextInput::make('shipping_address2')
                        ->label('상세주소')
                        ->maxLength(255)
                        ->columnSpanFull(),
                ]),

            Section::make('품목')
                ->schema([
                    Repeater::make('items')
                        ->hiddenLabel()
                        ->columns(4)
                        ->addActionLabel('품목 추가')
                        ->schema([
                            TextInput::make('name')->label('상품명')->required(),
                            TextInput::make('option')->label('옵션'),
                            TextInput::make('qty')->label('수량')->numeric()->required(),
                            TextInput::make('price')->label('단가')->numeric()->required(),
                        ]),
                ]),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListOrders::route('/'),
            'view' => ViewOrder::route('/{record}'),
            'edit' => EditOrder::route('/{record}/edit'),
        ];
    }
}
