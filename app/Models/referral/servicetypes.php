<?php

namespace App\Models\referral;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class servicetypes extends Model
{
    protected $table = 'ServiceTypes';
    protected $connection = 'sqlsrv';
}
