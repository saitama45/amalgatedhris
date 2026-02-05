<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LeaveType extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'days_per_year', 'is_convertible', 'is_cumulative'];

    public function requests()
    {
        return $this->hasMany(LeaveRequest::class);
    }
}