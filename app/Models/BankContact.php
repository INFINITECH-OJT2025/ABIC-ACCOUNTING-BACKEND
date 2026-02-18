<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Bank;


class BankContact extends Model
{
    use HasFactory;

    protected $fillable = [
        'bank_id',
        'branch_name',
        'contact_person',
        'position',
        'notes',
        'status',
    ];

    public function bank()
    {
        return $this->belongsTo(Bank::class);
    }

    public function channels()
    {
        return $this->hasMany(BankContactChannel::class);
    }
    
}
