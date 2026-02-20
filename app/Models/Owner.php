<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Owner extends Model
{
    use HasFactory;

    protected $fillable = [
        'owner_type',
        'name',
        'email',
        'phone_number',
        'address',
        'status',
        'created_by',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /*
    |--------------------------------------------------------------------------
    | RELATIONSHIPS
    |--------------------------------------------------------------------------
    */

    // Transactions where owner is sender (withdrawal)
    public function outgoingTransactions()
    {
        return $this->hasMany(Transaction::class, 'from_owner_id');
    }

    // Transactions where owner is receiver (deposit)
    public function incomingTransactions()
    {
        return $this->hasMany(Transaction::class, 'to_owner_id');
    }

    // Creator (user)
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // Bank Accounts
    public function bankAccounts()
    {
        return $this->hasMany(BankAccount::class);
    }

    public function chartOfAccount()
    {
        return $this->belongsTo(\App\Models\ChartOfAccount::class);
    }
}