<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class GeneralLedgerEntry extends Model
{
    use HasFactory;

    public $timestamps = false;


    protected $fillable = [
        'transaction_id',
        'account_id',
        'debit',
        'credit',
        'entry_description'
        
    ];

    public function transaction()
    {
        return $this->belongsTo(Transaction::class);
    }

    public function account()
    {
        return $this->belongsTo(ChartOfAccount::class, 'account_id');
    }
}
