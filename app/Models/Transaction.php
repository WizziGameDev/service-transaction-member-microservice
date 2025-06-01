<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Transaction extends Model
{
    protected $fillable = ['transaction_code', 'member_id', 'member_name', 'total_price', 'transaction_date'];

    public function items()
    {
        return $this->hasMany(TransactionItem::class);
    }
}
