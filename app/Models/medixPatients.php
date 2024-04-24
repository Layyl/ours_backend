<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class medixPatients extends Model
{
    protected $table = 'PatientHistory';
    protected $connection = 'sqlsrv';
    protected $primaryKey = 'PatientHistoryID';
    
    public function patientinfo()
    {
        return $this->hasOne(PatientInfo::class, 'PatientHistoryID'); 
    }
    public function patients()
    {
        return $this->belongsTo(Patients::class);
    }
}
