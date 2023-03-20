<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use HasFactory;
    protected $guarded=[];
    protected $hidden=['created_at','updated_at','repository_id','category_id','laravel_through_key'];

//    protected function Photo(): Attribute{
//        return Attribute::make(
//            get:fn ($value) => ($value != null) ? asset('assets/images/products/'. $value) : asset('assets/images/products/default_product.png')
//        );
//    }

    public function category():BelongsTo
    {
        return $this -> belongsTo('App\Models\Category');
    }
    public function purchases():HasMany
    {
        return $this -> hasMany('App\Models\Purchase');
    }
    public function sales():HasMany
    {
        return $this -> hasMany('App\Models\Sale');
    }

    public function product_registers():HasMany{
        return $this->hasMany(ProductRegister::class);
    }

}
