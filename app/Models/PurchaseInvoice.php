<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PurchaseInvoice extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table='purchases_invoices';
    protected $guarded=[];
    protected $hidden=['created_at','updated_at','repository_id','client_id','deleted_at','supplier_id','register_id','laravel_through_key'];

    public function supplier():BelongsTo
    {
        return $this->belongsTo(Supplier::class,'supplier_id')->latest('updated_at');
    }

    public function register():BelongsTo
    {
        return $this->belongsTo(MoneyBox::class,'register_id')->latest('updated_at');
    }

    public function purchases():HasMany
    {
        return $this->hasMany(Purchase::class,'purchase_invoice_id')->latest('updated_at');
    }

    public function purchase_invoice_registers():HasMany{
        return $this->hasMany(PurchaseInvoiceRegister::class);
    }

}
