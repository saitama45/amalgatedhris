<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Company extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'code',
        'description',
        'is_active',
        'sss_payout_schedule',
        'philhealth_payout_schedule',
        'pagibig_payout_schedule',
        'withholding_tax_payout_schedule',
    ];

    public function roles()
    {
        return $this->belongsToMany(Role::class);
    }
}
