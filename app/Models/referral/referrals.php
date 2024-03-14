<?php

namespace App\Models\referral;

use Illuminate\Database\Eloquent\Model;

class referrals extends Model
{
    protected $fillable = [
        'patientID',
        'initialReferralID',
        'referringHospital',
        'referringDoctor',
        'referrerContact',
        'transferReason',
        'referralRemarks',
        'deferRemarks',
        'receivingDepartment',
        'assignedDoctor',
        'civilStatus',
        'street',
        'provinceID',
        'municipalityID',
        'barangayID',
        'patientContact',
        'informantName',
        'informantRelationship',
        'informantContact',
        'impression',
        'chiefComplaint',
        'history',
        'examinationFindings',
        'laboratories',
        'imaging',
        'medicalInterventions',
        'courseInTheWard',
        'diagnosticsDone',
        'height',
        'systolic',
        'diastolic',
        'weight',
        'oxygenSaturation',
        'bmi',
        'temperature',
        'respiratoryRate',
        'pulseRate',
        'cardiacRate',
        'cbg',
        'painScale',
        'e',
        'v',
        'm',
        'gcs',
        'patientFiles',
        'referralToFill',
        'addedBy',
        'updatedBy',
        'status',
    ];

    protected $table = 'patientreferrals';
    protected $connection = 'mysql';
    
}
