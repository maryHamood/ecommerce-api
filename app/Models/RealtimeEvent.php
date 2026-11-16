<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RealtimeEvent extends Model
{
    protected $fillable = [
        'event_id',
        'event_type',
        'order_id',
        'user_id',
        'payload',
    ];

    protected $casts = [
        'order_id' => 'integer',
        'user_id' => 'integer',
        'payload' => 'array',
    ];
}
