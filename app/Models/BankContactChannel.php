<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Bank;


class BankContactChannel extends Model
{
    use HasFactory;

    protected $fillable = [
        'bank_contact_id',
        'channel_type',
        'value',
        'label',
        'status',
    ];

    public function contact()
    {
        return $this->belongsTo(BankContact::class, 'bank_contact_id');    
    }
}
