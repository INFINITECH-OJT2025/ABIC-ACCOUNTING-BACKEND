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
        // 'account_name',
        // 'account_number',
        // 'bank_details',
        // 'status',
    ];

    public function transactions()
    {
        return $this->hasMany(BankTransaction::class);
    }

    public function transactionAttachments()
    {
        return $this->hasMany(BankTransactionAttachment::class);
    }

}
