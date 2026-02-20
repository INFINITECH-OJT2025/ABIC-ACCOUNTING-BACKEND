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
        'trans_method',
        'from_owner_id',
        'to_owner_id',
        'amount',
        'fund_reference',
        'particulars',
        'transfer_group_id',
        'created_by',
        'person_in_charge',
        'status',
    ];

    protected $casts = [
        'voucher_date' => 'date',
        'amount' => 'decimal:2',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /*
    |--------------------------------------------------------------------------
    | RELATIONSHIPS
    |--------------------------------------------------------------------------
    */

    public function fromOwner()
    {
        return $this->belongsTo(Owner::class, 'from_owner_id');
    }

    public function toOwner()
    {
        return $this->belongsTo(Owner::class, 'to_owner_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function instruments()
    {
        return $this->hasMany(TransactionInstrument::class);
    }

    public function attachments()
    {
        return $this->hasMany(TransactionAttachment::class);
    }
}