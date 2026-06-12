<?php

namespace App\Models;

use App\Enums\OrderStatus;
use App\Observers\OrderObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[ObservedBy(OrderObserver::class)]
class Order extends Model
{
    protected $fillable = [
        'channel',
        'channel_order_no',
        'customer_name',
        'customer_phone',
        'shipping_postcode',
        'shipping_address1',
        'shipping_address2',
        'items',
        'payment',
        'pccc',
        'status',
        'raw',
        'ordered_at',
    ];

    protected function casts(): array
    {
        return [
            'items' => 'array',
            'payment' => 'array',
            'raw' => 'array',
            'status' => OrderStatus::class,
            'ordered_at' => 'datetime',
        ];
    }

    public function statusLogs(): HasMany
    {
        return $this->hasMany(OrderStatusLog::class)->latest('id');
    }

    public function channelLabel(): string
    {
        return config("channels.{$this->channel}.label", $this->channel);
    }

    public function paymentAmount(): ?int
    {
        $amount = data_get($this->payment, 'amount');

        return $amount === null ? null : (int) $amount;
    }
}
