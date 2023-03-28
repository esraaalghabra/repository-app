<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Sale extends Model
{
    use HasFactory;
    use SoftDeletes;
    protected $guarded=[];
    protected $hidden=['created_at','updated_at','sale_invoice_id','product_id','repository_id','date','laravel_through_key'];

    public function product():BelongsTo
    {
        return $this->belongsTo(Product::class,'product_id')->latest('updated_at');
    }

    public function saleInvoice():BelongsTo
    {
        return $this->belongsTo(SaleInvoice::class,'sale_invoice_id')->latest('updated_at');
    }

    public function client():BelongsTo
    {
        return $this->belongsTo(Client::class,'client_id')->latest('updated_at');
    }
}
