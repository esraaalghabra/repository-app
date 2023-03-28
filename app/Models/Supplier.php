<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\SoftDeletes;

class   Supplier extends Model
{
    use HasFactory;
    use SoftDeletes;
    protected $guarded=[];
    protected $hidden=['created_at','updated_at','repository_id','pivot'];

//    protected function Photo(): Attribute{
//        return Attribute::make(
//            get:fn ($value) => ($value != null) ? asset('assets/images/suppliers/'. $value) : asset('assets/images/suppliers/default_supplier.png')
//        );
//    }

    public function purchases_invoices():HasMany
    {
        return $this->hasMany(PurchaseInvoice::class,'supplier_id')->latest('updated_at');
    }

    public function purchases():HasMany
    {
        return $this->hasMany(Purchase::class);
    }

    public function repository():BelongsTo
    {
        return $this->belongsTo(Repository::class,'repository_id');
    }

}
