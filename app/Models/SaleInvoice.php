<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class SaleInvoice extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table='sales_invoices';
    protected $guarded=[];
    protected $hidden=['created_at','updated_at','repository_id','client_id','deleted_at','register_id','laravel_through_key'];

    public function client():BelongsTo
    {
        return $this->belongsTo(Client::class,'client_id')->latest('updated_at');
    }

    public function sales():HasMany
    {
        return $this->hasMany(Sale::class,'sale_invoice_id')->latest('updated_at');
    }

    public function register():BelongsTo
    {
        return $this->belongsTo(MoneyBox::class,'register_id')->latest('updated_at');
    }
    public function sale_invoice_registers():HasMany
    {
        return $this->hasMany(SaleInvoiceRegister::class)->latest('updated_at');
    }

}
