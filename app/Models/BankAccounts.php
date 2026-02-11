<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BankAccounts extends Model
{
    protected $fillable = [
        'name',
        'bank',
        'account_name',
        'account_number',
        'phone_number',
        'is_pmo' => 'boolean',
    ];
}
