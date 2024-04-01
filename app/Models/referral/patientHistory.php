<?php

namespace App\Models\referral;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class patientHistory extends Model
{
    protected $table = 'PatientHistory';
    protected $connection = 'sqlsrv';

}
