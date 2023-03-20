<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExpenseRegister extends Model
{
    use HasFactory;

    protected $guarded=[];
    protected $hidden=['created_at','updated_at','user_id'];

    public function expense():BelongsTo
    {
        return $this->belongsTo(Expense::class,'expense_id');
    }
    public function user():BelongsTo
    {
        return $this->belongsTo(User::class,'user_id');
    }
}
