<?php

namespace App\Models\referral;

use Illuminate\Database\Eloquent\Model;

class persons extends Model
{
    protected $fillable = [
        'lastName',
        'firstName',
        'middleName',
        'suffix',
        'contactNo',
        'emailAddress',
    ];
    
    protected $table = 'persons';
    protected $connection = 'mysql';
}
