<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Transaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'voucher_no',
        'voucher_date',
        'trans_type',
        'transaction_reference',
        'document_reference',
        'bank_account_id',
        'counterparty_bank_account_id',
        'particulars',
        'deposit_amount',
        'withdrawal_amount',
        'created_by'
    ];

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */



    public function bankAccount()
    {
        return $this->belongsTo(BankAccount::class);
    }

    public function counterpartyBankAccount()
    {
        return $this->belongsTo(BankAccount::class, 'counterparty_bank_account_id');
    }

    public function attachments()
    {
        return $this->hasMany(TransactionAttachment::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
