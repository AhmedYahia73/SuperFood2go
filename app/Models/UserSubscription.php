<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserSubscription extends Model
{
    protected $fillable = [
        'name', 
        'back_link',
        'from',
        'to',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];
}
