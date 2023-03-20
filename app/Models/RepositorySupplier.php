<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RepositorySupplier extends Model
{
    use HasFactory;
    protected $table='repositories_suppliers';
    protected $guarded=[];

}
