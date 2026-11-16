<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProcessedEvent extends Model
{
    protected $fillable = [
        'consumer_name',
        'event_id',
        'processed_at',
    ];

    protected $casts = [
        'processed_at' => 'datetime',
    ];
}
