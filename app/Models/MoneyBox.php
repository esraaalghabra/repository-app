<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class MoneyBox extends Model
{
    use HasFactory;
    use SoftDeletes;
    protected $guarded=[];
    protected $table = 'money_box';
    protected $hidden=['created_at','updated_at','repository_id', 'deleted_at'];


    public function purchaseInvoice():HasOne{
        return $this->hasOne(PurchaseInvoice::class,'register_id');
    }
    public function saleInvoice():HasOne{
        return $this->hasOne(SaleInvoice::class,'register_id');
    }

    public function repository():BelongsTo
    {
        return $this->belongsTo(Repository::class,'repository_id')->latest('updated_at');
    }

    public function money_box_registers():HasMany{
        return $this->hasMany(MoneyBoxRegister::class);
    }
}
