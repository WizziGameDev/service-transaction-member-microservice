<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TransactionMember extends Model
{
    protected $fillable = ['transaction_code', 'member_id', 'member_name', 'total_price', 'transaction_date', 'status'];

    public function items()
    {
        return $this->hasMany(TransactionMemberItem::class, 'transaction_member_id');
    }
}
