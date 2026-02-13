<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class BankAccount extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_name',
        'bank_id',
        'account_name',
        'account_number',
        'contact_numbers',
        'is_pmo',
        'status',
    ];

    protected $casts = [
        'contact_numbers' => 'array',
        'is_pmo' => 'boolean',
    ];

    public function bank()
    {
        return $this->belongsTo(Bank::class);
    }
}
