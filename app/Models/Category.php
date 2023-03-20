<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class Category extends Model
{
    use HasFactory;
    protected $guarded=[];
    protected $hidden=['created_at','updated_at','repository_id','deleted_at','pivot'];
//
//    protected function Photo(): Attribute{
//        return Attribute::make(
//            get:fn ($value) => ($value != null) ? asset('assets/images/categories/'. $value) : asset('assets/images/categories/default_category.png')
//        );
//    }

    public function products():HasMany
    {
        return $this -> hasMany('App\Models\Product');
    }

    public function category_registers():HasMany{
        return $this->hasMany(CategoryRegister::class);
    }

    public function sales():HasManyThrough
    {
        return $this->hasManyThrough(Sale::class,Product::class,'category_id','product_id');
    }

    public function purchases():HasManyThrough
    {
        return $this->hasManyThrough(Purchase::class,Product::class,'category_id','product_id');
    }

    public function repositories():BelongsTo
    {
        return $this->belongsTo(Repository::class,'repository_id');
    }
}
