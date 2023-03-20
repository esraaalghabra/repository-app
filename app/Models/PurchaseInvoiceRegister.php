<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseInvoiceRegister extends Model
{
    use HasFactory;
    protected $guarded=[];
    protected $hidden=['created_at','updated_at','purchase_invoice_id','user_id'];

    public function purchase_invoice():BelongsTo
    {
        return $this->belongsTo(PurchaseInvoice::class,'purchase_invoice_id');
    }
    public function user():BelongsTo
    {
        return $this->belongsTo(User::class,'user_id');
    }
}
