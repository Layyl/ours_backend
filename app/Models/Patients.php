<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Patients extends Model
{
    protected $table = 'Patients';
    protected $connection = 'sqlsrv';
    
    public function persons()
{
    return $this->belongsTo(Persons::class);
}
}
