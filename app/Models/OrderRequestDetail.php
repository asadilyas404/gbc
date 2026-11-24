<?php

namespace App\Models;

use App\Services\CallCenterSchema;
use Illuminate\Database\Eloquent\Model;

class OrderRequestDetail extends Model
{
    protected $table = 'order_request_details';

    public $incrementing = false;

    protected $guarded = [];

    protected $casts = [
        'order_request_id' => 'integer',
        'food_id' => 'integer',
        'quantity' => 'integer',
        'price' => 'float',
    ];

    protected static function booted()
    {
        static::creating(function (self $detail) {
            if (!$detail->id) {
                $detail->id = CallCenterSchema::nextId('order_req_details_seq');
            }
        });
    }

    public function request()
    {
        return $this->belongsTo(OrderRequest::class, 'order_request_id');
    }

    public function extras(): array
    {
        $raw = $this->add_ons;
        if (is_object($raw) && method_exists($raw, 'load')) {
            $raw = $raw->load();
        } elseif (is_resource($raw)) {
            $raw = stream_get_contents($raw);
        }
        $decoded = json_decode((string) $raw, true);

        return is_array($decoded) ? $decoded : [];
    }

    public function summaryText(): string
    {
        return (string) ($this->extras()['summary'] ?? '');
    }

    public function lineAmount(): float
    {
        $addons = (float) ($this->extras()['addon_price'] ?? 0);

        return round(((float) $this->price * (int) $this->quantity) + $addons, 3);
    }
}
