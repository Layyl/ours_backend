<?php

namespace App\Models\referral;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class referringHCI extends Model
{
    protected $table = 'activefacilities';
    protected $connection = 'mysql';
}
