<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\BankAccount;
use App\Models\Owner;

class BankTransaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'bank_account_id',
        'owner_id',
        'transaction_date',
        'reference_number',
        'transaction_type',
        'particulars',
        'deposit',
        'withdraw',
        'outstanding_balance',
        'status',
    ];

    protected $casts = [
        'transaction_date' => 'datetime',
        'deposit' => 'decimal:2',
        'withdraw' => 'decimal:2',
        'outstanding_balance' => 'decimal:2',
    ];

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(Owner::class);
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(BankTransactionAttachment::class);
    }
}
