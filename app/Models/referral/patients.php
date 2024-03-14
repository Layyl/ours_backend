<?php

namespace App\Models\referral;
use Illuminate\Database\Eloquent\Model;

class patients extends Model
{

    protected $fillable = [
        'lastName',
        'firstName',
        'middleName',
        'suffix',
        'birthDate',
        'gender',
        'created_by',
    ];
    protected $table = 'patients';
    protected $connection = 'mysql';
}
