<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class CategoryRegister extends Model
{
    use HasFactory;

    protected $guarded=[];
    protected $hidden=['created_at','updated_at','category_id','user_id'];

    public function category():BelongsTo
    {
        return $this->belongsTo(Category::class,'category_id');
    }
    public function user():BelongsTo
    {
        return $this->belongsTo(User::class,'user_id');
    }
}
