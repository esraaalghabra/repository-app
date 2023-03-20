<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MoneyBoxRegister extends Model
{
    use HasFactory;
    protected $guarded=[];
    protected $hidden=['created_at','updated_at','product_id','user_id'];

    public function register():BelongsTo
    {
        return $this->belongsTo(MoneyBox::class,'money_box_id');
    }
    public function user():BelongsTo
    {
        return $this->belongsTo(User::class,'user_id');
    }
}
