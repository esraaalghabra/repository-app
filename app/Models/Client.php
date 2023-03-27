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

class Client extends Model
{
    use HasFactory;
    use SoftDeletes;
    protected $guarded=[];
    protected $hidden=['created_at','updated_at','deleted_at','repository_id','pivot'];


//    protected function Photo(): Attribute{
//        return Attribute::make(
//            get:fn ($value) => ($value != null) ? public_path('assets/images/clients/'. $value) : asset('assets/images/clients/default_client.png')
//        );
//    }

    /**
     * @return HasMany
     */
    public function sales_invoices():HasMany
    {
        return $this->hasMany(SaleInvoice::class,'client_id');
    }

    /**
     * @return HasManyThrough
     */
    public function sales():HasManyThrough
    {
        return $this->hasManyThrough(Sale::class,SaleInvoice::class,'client_id','sale_invoice_id');
    }

    public function repository():BelongsTo
    {
        return $this->belongsTo(Repository::class,'repository_id');
    }

    public function client_registers():HasMany{
        return $this->hasMany(ClientRegister::class);
    }
}
