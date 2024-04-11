<?php

namespace App\Models\referral;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Nationality extends Model
{
    protected $table = 'Nationality';
    protected $connection = 'sqlsrv';
}
