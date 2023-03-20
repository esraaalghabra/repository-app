<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductRegister extends Model
{
    use HasFactory;

    protected $guarded=[];
    protected $hidden=['created_at','updated_at','product_id','user_id'];

    public function product():BelongsTo
    {
        return $this->belongsTo(Product::class,'product_id');
    }
    public function user():BelongsTo
    {
        return $this->belongsTo(User::class,'user_id');
    }
}
