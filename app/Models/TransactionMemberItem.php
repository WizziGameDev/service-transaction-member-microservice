<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TransactionMemberItem extends Model
{
    protected $fillable = ['transaction_member_id', 'product_id', 'product_name', 'price', 'quantity', 'subtotal'];

    public function transaction()
    {
        return $this->belongsTo(TransactionMember::class, 'transaction_member_id');
    }
}
