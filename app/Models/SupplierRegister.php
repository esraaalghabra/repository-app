<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupplierRegister extends Model
{
    use HasFactory;

    protected $guarded=[];
    protected $hidden=['created_at','updated_at','supplier_id','user_id'];

    public function supplier():BelongsTo
    {
        return $this->belongsTo(Supplier::class,'supplier_id');
    }
    public function user():BelongsTo
    {
        return $this->belongsTo(User::class,'user_id');
    }
}
