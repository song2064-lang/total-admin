<?php

namespace App\Filament\Resources\Orders;

use App\Domain\Orders\ChannelManager;
use App\Enums\OrderStatus;
use App\Filament\Resources\Orders\Pages\EditOrder;
use App\Filament\Resources\Orders\Pages\ListOrders;
use App\Filament\Resources\Orders\Pages\ViewOrder;
use App\Models\Order;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class OrderResource extends Resource
{
    protected static ?string $model = Order::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-shopping-bag';

    protected static ?string $modelLabel = '주문';

    protected static ?string $pluralModelLabel = '주문';

    protected static ?string $navigationLabel = '주문 관리';

    public const CARRIERS = ['CJ대한통운', '우체국택배', '한진택배', '롯데택배', '로젠택배'];

    // 현지(일본) 구간 택배사
    public const LOCAL_CARRIERS = ['야마토운수', '사가와택배', '일본우편', '세이노운수'];

    // 국제 구간 운송
    public const INTL_CARRIERS = ['EMS', '항공특송', '해상운송', 'DHL', 'FedEx'];

    public const SOURCES = [
        'mercari' => '메루카리',
        'amiami' => '아미아미',
        'yahoo_auctions' => '야후옥션',
        'rakuten' => '라쿠텐',
    ];

    // 주문 생성은 API 수신으로만
    public static function canCreate(): bool
    {
        return false;
    }

    public static function sourceLabel(?string $source): ?string
    {
        if ($source === null || $source === '') {
            return null;
        }

        return self::SOURCES[$source] ?? $source;
    }

    // 엔화 + 주문 당시 환율 원화 환산 병기
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
            ->striped()
            ->recordUrl(fn (Order $record) => static::getUrl('view', ['record' => $record]))
            ->columns([
                TextColumn::make('channel_order_no')
                    ->label('주문번호')
                    ->searchable()
                    ->weight('bold')
                    ->description(fn (Order $record) => $record->ordered_at?->format('Y-m-d H:i'))
                    ->sortable(['id']),
                TextColumn::make('channel')
                    ->label('채널')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => config("channels.{$state}.label", $state)),
                TextColumn::make('items_summary')
                    ->label('상품')
                    ->state(function (Order $record) {
                        $items = $record->items ?? [];
                        $first = $items[0]['name'] ?? '-';
                        $extra = count($items) - 1;

                        return $extra > 0 ? "{$first} 외 {$extra}건" : $first;
                    })
                    ->limit(34)
                    ->tooltip(fn (Order $record) => collect($record->items)->pluck('name')->implode(', '))
                    ->description(function (Order $record) {
                        $source = static::sourceLabel(data_get($record->items, '0.source'));
                        $qty = collect($record->items)->sum('qty');

                        return trim(($source ? $source.' · ' : '').$qty.'개');
                    }),
                TextColumn::make('customer_name')
                    ->label('주문자')
                    ->description(fn (Order $record) => $record->customer_phone)
                    // 주문자명 + 연락처 통합 검색. 숫자 없는 검색어는 연락처 조건 제외(빈 LIKE 방지)
                    ->searchable(query: function (Builder $query, string $search) {
                        $digits = preg_replace('/\D/', '', $search);

                        return $query->where(function (Builder $q) use ($search, $digits) {
                            $q->where('customer_name', 'like', "%{$search}%");
                            if ($digits !== '') {
                                $q->orWhere('customer_phone', 'like', "%{$digits}%");
                            }
                        });
                    }),
                TextColumn::make('payment_amount')
                    ->label('결제금액')
                    ->state(fn (Order $record) => $record->paymentAmount())
                    ->numeric()
                    ->suffix(fn (Order $record) => $record->currencyUnit())
                    ->alignEnd()
                    ->weight('bold')
                    ->description(function (Order $record) {
                        $krw = data_get($record->payment, 'total_krw');

                        return $krw !== null ? '약 '.number_format($krw).'원' : null;
                    }, position: 'below'),
                TextColumn::make('status')
                    ->label('상태')
                    ->badge(),
                TextColumn::make('tracking_no')
                    ->label('송장번호')
                    ->searchable()
                    ->description(fn (Order $record) => $record->tracking_carrier)
                    ->placeholder('미발송')
                    ->toggleable(),
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
                SelectFilter::make('source')
                    ->label('구매처')
                    ->options(self::SOURCES)
                    ->query(fn (Builder $query, array $data) => filled($data['value'])
                        ? $query->where('items', 'like', '%"source":"'.$data['value'].'"%')
                        : $query),
                Filter::make('ordered_at')
                    ->schema([
                        DatePicker::make('from')->label('주문일 from'),
                        DatePicker::make('until')->label('주문일 until'),
                    ])
                    ->query(fn (Builder $query, array $data) => $query
                        ->when($data['from'] ?? null, fn (Builder $q, $date) => $q->whereDate('ordered_at', '>=', $date))
                        ->when($data['until'] ?? null, fn (Builder $q, $date) => $q->whereDate('ordered_at', '<=', $date)))
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];
                        if ($data['from'] ?? null) {
                            $indicators[] = '주문일 ≥ '.$data['from'];
                        }
                        if ($data['until'] ?? null) {
                            $indicators[] = '주문일 ≤ '.$data['until'];
                        }

                        return $indicators;
                    }),
            ])
            ->recordActions([
                // 자주 쓰는 진행 동작은 노출, 나머지는 더보기 메뉴로
                static::advanceStatusAction(),
                static::shipInternationalAction(),
                static::shipDomesticAction(),
                ActionGroup::make([
                    ViewAction::make(),
                    static::localTrackingAction(),
                    static::revertStatusAction(),
                    static::noteAction(),
                    EditAction::make(),
                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    static::bulkAdvanceAction(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    // 목록에선 캐시만 비워 갱신, 상세에선 상태 이력까지 반영되도록 새로고침
    protected static function refreshAfterChange($livewire): void
    {
        if ($livewire instanceof ListOrders) {
            $livewire->refreshAfterStatusChange();

            return;
        }

        $livewire->js('window.location.reload()');
    }

    // 선택한 주문들을 다음 단계로 일괄 전진 (송장 입력이 필요한 배송 단계는 제외)
    public static function bulkAdvanceAction(): BulkAction
    {
        return BulkAction::make('bulk_advance')
            ->label('다음 단계로 일괄 변경')
            ->icon('heroicon-o-arrow-right-circle')
            ->color('primary')
            ->requiresConfirmation()
            ->modalHeading('선택 주문을 다음 단계로')
            ->modalSubmitActionLabel('변경')
            ->action(function (Collection $records, $livewire): void {
                $changed = 0;
                $skipped = 0;

                foreach ($records as $record) {
                    $next = $record->status->next();
                    if ($next === null || $next->requiresTracking()) {
                        $skipped++;

                        continue;
                    }
                    $record->update(['status' => $next]);
                    $changed++;
                }

                Notification::make()
                    ->title("{$changed}건 변경, {$skipped}건 건너뜀")
                    ->success()
                    ->send();

                static::refreshAfterChange($livewire);
            })
            ->deselectRecordsAfterCompletion();
    }

    // 상태 한 단계 전진. 배송 단계는 송장 입력이 필요해 별도 액션으로 분리
    public static function advanceStatusAction(): Action
    {
        return Action::make('advance_status')
            ->label('다음 단계')
            ->icon('heroicon-o-arrow-right-circle')
            ->color('primary')
            ->visible(fn (Order $record) => $record->status->next() !== null
                && ! $record->status->next()->requiresTracking())
            ->requiresConfirmation()
            ->modalHeading(fn (Order $record) => sprintf(
                '%s 에서 %s 로 변경',
                $record->status->getLabel(),
                $record->status->next()?->getLabel(),
            ))
            ->modalSubmitActionLabel('변경')
            ->modalCancelActionLabel('취소')
            ->action(function (Order $record, $livewire): void {
                $next = $record->status->next();

                if ($next === null || ! $record->status->canTransitionTo($next)) {
                    return;
                }

                $record->update(['status' => $next]);

                Notification::make()
                    ->title("상태가 '{$next->getLabel()}'(으)로 변경되었습니다.")
                    ->success()
                    ->send();

                static::refreshAfterChange($livewire);
            });
    }

    // 상태 한 단계 되돌리기. 배송완료는 종결 단계라 역행 불가
    public static function revertStatusAction(): Action
    {
        return Action::make('revert_status')
            ->label('이전 단계로')
            ->color('gray')
            ->visible(fn (Order $record) => $record->status !== OrderStatus::Delivered
                && $record->status->prev() !== null)
            ->requiresConfirmation()
            ->modalHeading(fn (Order $record) => sprintf(
                '%s 에서 %s 로 되돌리기',
                $record->status->getLabel(),
                $record->status->prev()?->getLabel(),
            ))
            ->modalDescription('상태를 한 단계 되돌립니다. 채널 사이트에도 반영됩니다.')
            ->modalSubmitActionLabel('되돌리기')
            ->modalCancelActionLabel('취소')
            ->action(function (Order $record, $livewire): void {
                $prev = $record->status->prev();

                if ($prev === null || $record->status === OrderStatus::Delivered) {
                    return;
                }

                $record->update(['status' => $prev]);

                Notification::make()
                    ->title("상태가 '{$prev->getLabel()}'(으)로 되돌려졌습니다.")
                    ->success()
                    ->send();

                static::refreshAfterChange($livewire);
            });
    }

    // 국제배송 전환. 국제 구간 송장 입력 강제
    public static function shipInternationalAction(): Action
    {
        return Action::make('ship_international')
            ->label('국제배송 처리')
            ->icon('heroicon-o-paper-airplane')
            ->color('info')
            ->visible(fn (Order $record) => $record->status->next() === OrderStatus::InternationalShipping)
            ->schema([
                Select::make('tracking_intl_carrier')
                    ->label('운송수단')
                    ->options(array_combine(self::INTL_CARRIERS, self::INTL_CARRIERS))
                    ->required()
                    ->native(false),
                TextInput::make('tracking_intl_no')
                    ->label('송장번호')
                    ->required()
                    ->maxLength(64),
            ])
            ->modalHeading('국제배송 처리')
            ->modalSubmitActionLabel('국제배송 전환')
            ->modalCancelActionLabel('취소')
            ->action(function (Order $record, array $data, $livewire): void {
                $record->update([
                    'status' => OrderStatus::InternationalShipping,
                    'tracking_intl_carrier' => $data['tracking_intl_carrier'],
                    'tracking_intl_no' => $data['tracking_intl_no'],
                ]);

                Notification::make()
                    ->title('국제배송으로 전환되었습니다.')
                    ->body("{$data['tracking_intl_carrier']} {$data['tracking_intl_no']}")
                    ->success()
                    ->send();

                static::refreshAfterChange($livewire);
            });
    }

    // 국내배송 전환. 국내 구간 송장 입력 강제
    public static function shipDomesticAction(): Action
    {
        return Action::make('ship_domestic')
            ->label('국내배송 처리')
            ->icon('heroicon-o-truck')
            ->color('success')
            ->visible(fn (Order $record) => $record->status->next() === OrderStatus::DomesticShipping)
            ->schema([
                Select::make('tracking_carrier')
                    ->label('택배사')
                    ->options(array_combine(self::CARRIERS, self::CARRIERS))
                    ->required()
                    ->native(false),
                TextInput::make('tracking_no')
                    ->label('송장번호')
                    ->required()
                    ->maxLength(64),
            ])
            ->modalHeading('국내배송 처리')
            ->modalSubmitActionLabel('국내배송 전환')
            ->modalCancelActionLabel('취소')
            ->action(function (Order $record, array $data, $livewire): void {
                $record->update([
                    'status' => OrderStatus::DomesticShipping,
                    'tracking_carrier' => $data['tracking_carrier'],
                    'tracking_no' => $data['tracking_no'],
                ]);

                Notification::make()
                    ->title('국내배송으로 전환되었습니다.')
                    ->body("{$data['tracking_carrier']} {$data['tracking_no']}")
                    ->success()
                    ->send();

                static::refreshAfterChange($livewire);
            });
    }

    // 현지 송장 (입고 이후 선택 입력)
    public static function localTrackingAction(): Action
    {
        return Action::make('local_tracking')
            ->label('현지 송장')
            ->icon('heroicon-o-truck')
            ->color('gray')
            ->visible(fn (Order $record) => in_array($record->status, [
                OrderStatus::Warehoused,
                OrderStatus::Inspected,
                OrderStatus::InternationalShipping,
                OrderStatus::DomesticShipping,
                OrderStatus::Delivered,
            ], true))
            ->fillForm(fn (Order $record) => [
                'tracking_local_carrier' => $record->tracking_local_carrier,
                'tracking_local_no' => $record->tracking_local_no,
            ])
            ->schema([
                Select::make('tracking_local_carrier')
                    ->label('현지 택배사')
                    ->options(array_combine(self::LOCAL_CARRIERS, self::LOCAL_CARRIERS))
                    ->native(false),
                TextInput::make('tracking_local_no')
                    ->label('송장번호')
                    ->maxLength(64),
            ])
            ->modalHeading('현지 송장')
            ->modalSubmitActionLabel('저장')
            ->modalCancelActionLabel('취소')
            ->action(function (Order $record, array $data): void {
                $record->update([
                    'tracking_local_carrier' => $data['tracking_local_carrier'],
                    'tracking_local_no' => $data['tracking_local_no'],
                ]);

                Notification::make()->title('현지 송장이 저장되었습니다.')->success()->send();
            });
    }

    // 관리자 내부 메모 편집 (목록/상세 공용)
    public static function noteAction(): Action
    {
        return Action::make('edit_note')
            ->label('관리자 메모')
            ->icon('heroicon-o-pencil-square')
            ->color('gray')
            ->fillForm(fn (Order $record) => ['admin_note' => $record->admin_note])
            ->schema([
                Textarea::make('admin_note')
                    ->label('관리자 메모 (고객에게 노출되지 않음)')
                    ->rows(4)
                    ->maxLength(2000),
            ])
            ->modalHeading('관리자 메모')
            ->modalSubmitActionLabel('저장')
            ->action(function (Order $record, array $data): void {
                $record->update(['admin_note' => $data['admin_note']]);

                Notification::make()->title('메모가 저장되었습니다.')->success()->send();
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
                    TextEntry::make('full_address')
                        ->label('배송지 (복사용)')
                        ->state(fn (Order $record) => $record->fullAddress())
                        ->copyable()
                        ->columnSpanFull(),
                    TextEntry::make('shipping_address2')
                        ->label('상세주소')
                        ->placeholder('-')
                        ->visible(fn (Order $record) => filled($record->shipping_address2)),
                    TextEntry::make('order_memo')
                        ->label('고객 요청 메모')
                        ->state(fn (Order $record) => data_get($record->raw, 'memo'))
                        ->visible(fn (Order $record) => filled(data_get($record->raw, 'memo')))
                        ->columnSpanFull(),
                ]),

            Section::make('관리자 메모')
                ->headerActions([
                    \Filament\Actions\Action::make('edit_note_inline')
                        ->label('편집')
                        ->icon('heroicon-o-pencil-square')
                        ->link()
                        ->fillForm(fn (Order $record) => ['admin_note' => $record->admin_note])
                        ->schema([
                            Textarea::make('admin_note')
                                ->label('관리자 메모')
                                ->rows(4)
                                ->maxLength(2000),
                        ])
                        ->modalHeading('관리자 메모')
                        ->modalSubmitActionLabel('저장')
                        ->action(function (Order $record, array $data): void {
                            $record->update(['admin_note' => $data['admin_note']]);
                            Notification::make()->title('메모가 저장되었습니다.')->success()->send();
                        }),
                ])
                ->schema([
                    TextEntry::make('admin_note')
                        ->hiddenLabel()
                        ->placeholder('-')
                        ->formatStateUsing(fn (?string $state) => $state ? nl2br(e($state)) : null)
                        ->html(),
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
                                ->formatStateUsing(fn () => '링크')
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

            Section::make('현지 배송 (일본)')
                ->columns(3)
                ->schema([
                    TextEntry::make('tracking_local_carrier')->label('택배사'),
                    TextEntry::make('tracking_local_no')->label('송장번호')->copyable(),
                ])
                ->visible(fn (Order $record) => filled($record->tracking_local_no)),

            Section::make('국제 배송')
                ->columns(3)
                ->schema([
                    TextEntry::make('tracking_intl_carrier')->label('운송수단'),
                    TextEntry::make('tracking_intl_no')->label('송장번호')->copyable(),
                    TextEntry::make('intl_tracking_lookup')
                        ->label('배송조회')
                        ->state(fn () => '조회')
                        ->url(fn (Order $record) => $record->intlTrackingUrl())
                        ->openUrlInNewTab()
                        ->color('info'),
                ])
                ->visible(fn (Order $record) => filled($record->tracking_intl_no)),

            Section::make('국내 배송')
                ->columns(3)
                ->schema([
                    TextEntry::make('tracking_carrier')->label('택배사'),
                    TextEntry::make('tracking_no')->label('송장번호')->copyable(),
                    TextEntry::make('tracking_lookup')
                        ->label('배송조회')
                        ->state(fn () => '조회')
                        ->url(fn (Order $record) => $record->trackingUrl())
                        ->openUrlInNewTab()
                        ->color('info'),
                ])
                ->visible(fn (Order $record) => filled($record->tracking_no)),

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
                        ->helperText('국제·국내배송은 발송 처리에서 송장과 함께')
                        ->options(function (?Order $record) {
                            if ($record === null) {
                                return OrderStatus::options();
                            }

                            // 현재 상태 유지 또는 다음 단계만 (배송 단계 제외)
                            $options = [$record->status->value => $record->status->getLabel()];

                            $next = $record->status->next();
                            if ($next !== null && ! $next->requiresTracking()) {
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
                            Select::make('source')
                                ->label('구매처')
                                ->options(self::SOURCES)
                                ->native(false),
                            TextInput::make('qty')->label('수량')->numeric()->required(),
                            TextInput::make('price')->label('단가')->numeric()->required(),
                            TextInput::make('url')
                                ->label('상품 페이지 URL')
                                ->url()
                                ->columnSpanFull(),
                            // 원본 옵션 보존 (채널이 보낸 값)
                            TextInput::make('option')->label('옵션')->columnSpanFull(),
                        ]),
                ]),

            Section::make('관리자 메모')
                ->schema([
                    Textarea::make('admin_note')
                        ->label('관리자 메모')
                        ->rows(3)
                        ->maxLength(2000),
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
