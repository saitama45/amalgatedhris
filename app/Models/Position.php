<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Position extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'description', 'rank', 'has_late_policy'];

    protected $casts = [
        'has_late_policy' => 'boolean',
    ];
}
