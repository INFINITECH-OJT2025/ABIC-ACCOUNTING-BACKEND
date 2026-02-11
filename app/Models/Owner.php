<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Owner extends Model
{
    use HasFactory;

    protected $fillable = [
        'account_name',
        'account_number',
        'bank_details',
        'status',
    ];

}
