<?php

namespace App\Models\referral;

use Illuminate\Database\Eloquent\Model;

class referrals extends Model
{
    protected $fillable = [
        'lastName',
        'firstName',
        'middleName',
        'suffix',
        'birthDate',
        'gender',
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
        'nationality',
        'street',
        'provinceID',
        'municipalityID',
        'barangayID',
        'locationOfAccident',
        'typeOfInjury',
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
        'safru',
        'status',
    ];

    protected $table = 'patientreferrals';
    protected $connection = 'mysql';
    
}
