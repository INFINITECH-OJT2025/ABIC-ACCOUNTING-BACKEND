<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class TransactionAttachment extends Model
{
    use HasFactory;

    protected $fillable = [
        'transaction_id',
        'attachment_type',
        'file_name',
        'file_path',
        'file_type',
        'uploaded_at'
    ];

    public function transaction()
    {
        return $this->belongsTo(Transaction::class);
    }
}
