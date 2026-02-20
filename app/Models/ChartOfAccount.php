<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ChartOfAccount extends Model
{
    use HasFactory;

    protected $fillable = [
        'account_code',
        'account_name',
        'account_type',
        'parent_id',
        'related_bank_account_id',
        'is_active'
    ];

    public function parent()
    {
        return $this->belongsTo(ChartOfAccount::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(ChartOfAccount::class, 'parent_id');
    }

    public function bankAccount()
    {
        return $this->belongsTo(BankAccount::class, 'related_bank_account_id');
    }

    public function ledgerEntries()
    {
        return $this->hasMany(GeneralLedgerEntry::class, 'account_id');
    }
}
