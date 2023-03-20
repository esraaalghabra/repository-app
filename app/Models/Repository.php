<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class Repository extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;
    protected $guarded=[];
    protected $hidden=['created_at','updated_at','pivot'];


    public function users():BelongsToMany
    {
        return $this->belongsToMany(User::class,RepositoryUser::class,'repository_id','user_id');
    }

    public function categories():HasMany
    {
        return $this -> hasMany(Category::class);
    }

    public function registers():HasMany
    {
        return $this -> hasMany(MoneyBox::class);
    }

    public function products():HasManyThrough
    {
        return $this -> hasManyThrough(Product::class,Category::class,'repository_id','category_id');
    }

    public function sales_invoices():HasManyThrough
    {
        return $this -> hasManyThrough(SaleInvoice::class,Client::class,'repository_id','client_id');
    }

    public function purchases_invoices():HasManyThrough
    {
        return $this -> hasManyThrough(PurchaseInvoice::class,Supplier::class,'repository_id','supplier_id');
    }

    public function suppliers():HasMany
    {
        return $this -> hasMany(Supplier::class);
    }

    public function clients():HasMany
    {
        return $this -> hasMany(Client::class);
    }

    public function expenses():HasMany{
        return $this->hasMany(Expense::class);
    }
}
