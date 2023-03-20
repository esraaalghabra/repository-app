<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Purchase extends Model
{
    use HasFactory;
    use SoftDeletes;
    protected $guarded=[];
    protected $hidden=['created_at','updated_at','purchase_invoice_id','repository_id','date','laravel_through_key','product_id'];

    public function product():BelongsTo
    {
        return $this->belongsTo(Product::class,'product_id')->latest('updated_at');
    }
    public function purchaseInvoice():BelongsTo
    {
            return $this->belongsTo(PurchaseInvoice::class,'purchase_invoice_id')->latest('updated_at');
    }
}
