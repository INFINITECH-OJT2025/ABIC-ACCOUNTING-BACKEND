<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class BankAccount extends Model
{
    use HasFactory;

    protected $fillable = [
        'owner_id',
        'bank_id',
        'account_name',
        'account_number',
        'account_holder',
        'account_type',
        'opening_balance',
        'opening_date',
        'currency',
        'status',
        'created_by'
    ];


    protected $casts = [
        'contact_numbers' => 'array',
        'is_pmo' => 'boolean',
    ];

    public function bank()
    {
        return $this->belongsTo(Bank::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function owner()
    {
        return $this->belongsTo(Owner::class);
    }

}
