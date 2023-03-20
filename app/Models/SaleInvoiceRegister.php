<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SaleInvoiceRegister extends Model
{
    use HasFactory;
    protected $guarded=[];
    protected $hidden=['created_at','updated_at','sale_invoice_id','user_id'];

    public function sale_invoice():BelongsTo
    {
        return $this->belongsTo(SaleInvoice::class,'sale_invoice_id');
    }
    public function user():BelongsTo
    {
        return $this->belongsTo(User::class,'user_id');
    }
}
