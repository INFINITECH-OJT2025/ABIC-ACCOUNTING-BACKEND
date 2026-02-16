<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\BankAccount;
use App\Models\Owner;

class BankTransactionAttachment extends Model
{
    use HasFactory;

    protected $fillable = [
        'bank_transaction_id',
        'owner_id',
        'file_path',
        'person_in_charge',
    ];

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(BankTransaction::class, 'bank_transaction_id');
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(Owner::class);
    }
}
