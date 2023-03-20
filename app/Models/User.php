<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\Casts\Attribute;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $guarded=[];
    protected $hidden=['password','email_verified_at','rememberToken','created_at','updated_at','pivot'];
    const USER_TOKEN = "userToken";
    protected $casts = [
        'email_verified_at' => 'datetime',
    ];

    protected function Photo(): Attribute{
        return Attribute::make(
            get:fn ($value) => ($value != null) ? asset('assets/images/users/'. $value) : asset('assets/images/users/default_user.png')
        );
    }
    public function repositories():BelongsToMany
    {
        return $this->belongsToMany(Repository::class,RepositoryUser::class);
    }

    public function category_registers():HasMany{
        return $this->hasMany(CategoryRegister::class);
    }
    public function product_registers():HasMany{
        return $this->hasMany(ProductRegister::class);
    }
    public function client_registers():HasMany{
        return $this->hasMany(ClientRegister::class);
    }
    public function supplier_registers():HasMany{
        return $this->hasMany(SupplierRegister::class);
    }
}
