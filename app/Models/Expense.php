<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Expense extends Model
{
    use HasFactory;
    protected $table='expenses';
    protected $guarded=[];
    protected $hidden=['created_at','updated_at','repository_id','deleted_at', 'register_id'];


    public function register():BelongsTo
    {
        return $this->belongsTo(MoneyBox::class,'register_id')->latest('updated_at');
    }

    public function repository():BelongsTo
    {
        return $this->belongsTo(Repository::class,'repository_id')->latest('updated_at');
    }
}
