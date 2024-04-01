<?php

namespace App\Http\Controllers\referral;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\referral\servicetypes;
use App\Models\referral\patientHistory;
use App\Models\referral\Rooms;

class ErCountController extends Controller
{
    public function fetchERCount(Request $request)
    {
        $results = [];
        $ongoingEDConsultationTotal = 0;
        $admittedStillAtEDTotal = 0;
        $totalPatientsTotal = 0;
        $d = $e = $totapatient = $l = $m = $n = $o = 0;
    $serviceTypeIDs = [1, 2, 4, 9, 10, 13, 28, 41, 45, 22,6];

    foreach ($serviceTypeIDs as $serviceTypeID) {
        $description = ServiceTypes::where('ServiceTypeID', $serviceTypeID)
            ->value('Description');

        $totalPatients = PatientHistory::join('Patients', 'Patients.PatientID', '=', 'PatientHistory.PatientID')
            ->join('Persons', 'Persons.PersonID', '=', 'Patients.PersonID')
            ->leftJoin('Admissions', 'Admissions.AdmissionID', '=', 'PatientHistory.AdmissionID')
            ->leftJoin('AdmissionsOPD', 'AdmissionsOPD.AdmissionID', '=', 'PatientHistory.AdmissionOPDID')
            ->leftJoin('ServiceTypes as SAD', 'SAD.ServiceTypeID', '=', 'Admissions.ServiceTypeID')
            ->leftJoin('ServiceTypes as SADOPD', 'SADOPD.ServiceTypeID', '=', 'AdmissionsOPD.ServiceTypeID')
            ->join('PatientInfo', 'PatientInfo.PatientHistoryID', '=', 'PatientHistory.PatientHistoryID')
            ->join('Users', 'Users.UserID', '=', 'PatientHistory.UserID')
            ->join('Persons as UserPersons', 'UserPersons.PersonID', '=', 'Users.PersonID')
            ->where('PatientHistory.PatientTypeID', 4)
            ->whereNotIn('PatientHistory.PatientStatusID', [6, 7, 8, 9, 10, 11, 13, 12, 18])
            ->whereRaw('CAST(PatientHistory.TransactionDateTime AS DATE) BETWEEN DATEADD(DD, -1, CAST(GETDATE() AS DATE)) AND CAST(GETDATE() AS DATE)')
            ->where(function ($query) {
                $query->whereNull('PatientHistory.settoinpatient')
                    ->orWhere('PatientHistory.SetToInPatient', 0);
            })
            ->where('SADOPD.ServiceTypeID', $serviceTypeID)
            ->groupBy(PatientHistory::raw("CASE PatientHistory.PatientTypeID WHEN 1 THEN SAD.Description ELSE SADOPD.Description END"))
            ->selectRaw('COUNT(PatientHistory.PatientHistoryID) as total')
            ->pluck('total')
;

        $hoursNumbers = PatientHistory::join('Patients', 'Patients.PatientID', '=', 'PatientHistory.PatientID')
            ->join('Persons', 'Persons.PersonID', '=', 'Patients.PersonID')
            ->leftJoin('Admissions', 'Admissions.AdmissionID', '=', 'PatientHistory.AdmissionID')
            ->leftJoin('AdmissionsOPD', 'AdmissionsOPD.AdmissionID', '=', 'PatientHistory.AdmissionOPDID')
            ->leftJoin('ServiceTypes as SAD', 'SAD.ServiceTypeID', '=', 'Admissions.ServiceTypeID')
            ->leftJoin('ServiceTypes as SADOPD', 'SADOPD.ServiceTypeID', '=', 'AdmissionsOPD.ServiceTypeID')
            ->join('PatientInfo', 'PatientInfo.PatientHistoryID', '=', 'PatientHistory.PatientHistoryID')
            ->join('Users', 'Users.UserID', '=', 'PatientHistory.UserID')
            ->join('Persons as UserPersons', 'UserPersons.PersonID', '=', 'Users.PersonID')
            ->where('PatientHistory.PatientTypeID', 4)
            ->whereNotIn('PatientHistory.PatientStatusID', [6, 7, 8, 9, 10, 11, 13, 12, 18])
            ->whereRaw('CAST(PatientHistory.TransactionDateTime AS DATE) BETWEEN DATEADD(DD, -1, CAST(GETDATE() AS DATE)) AND CAST(GETDATE() AS DATE)')
            ->where(function ($query) {
                $query->whereNull('PatientHistory.settoinpatient')
                    ->orWhere('PatientHistory.SetToInPatient', 0);
            })
            ->where('SADOPD.ServiceTypeID', $serviceTypeID)
            ->selectRaw('DATEDIFF(minute, PatientHistory.TransactionDateTime, GETDATE()) AS HoursNumber')
            ->get();

        $withinFourHrs = $moreThanFourHrs = $AdmittedLessThan10 = $AdmittedWithin20 = $AdmittedBeyond20 = $NewlyAdmitted = $AdmittedACU = $countTotalAD = 0;

        foreach ($hoursNumbers as $hoursNumber) {
            if ($hoursNumber->HoursNumber <= 240) {
                $withinFourHrs++;
            } else {
                $moreThanFourHrs++;
            }

            if($hoursNumber->totaler >= 4) {
                $NewlyAdmitted++;
            } elseif ($hoursNumber->HoursNumber >= 5 && $hoursNumber->HoursNumber <= 9) {
                $AdmittedLessThan10++;
            } else if ($hoursNumber->HoursNumber >= 10 && $hoursNumber->HoursNumber <= 20) {
                $AdmittedWithin20++;
            } else if ($hoursNumber->HoursNumber >= 20) {
                $AdmittedBeyond20++;
            }
        }

        $totalAdmissions = PatientHistory::join('Patients', 'Patients.PatientID', '=', 'PatientHistory.PatientID')
        ->join('Persons', 'Persons.PersonID', '=', 'Patients.PersonID')
        ->leftJoin('Admissions', 'Admissions.AdmissionID', '=', 'PatientHistory.AdmissionID')
        ->leftJoin('ServiceTypes as SAD', 'SAD.ServiceTypeID', '=', 'Admissions.ServiceTypeID')
        ->join('PatientInfo', 'PatientInfo.PatientHistoryID', '=', 'PatientHistory.PatientHistoryID')
        ->join('Users', 'Users.UserID', '=', 'PatientHistory.UserID')
        ->join('Persons as UserPersons', 'UserPersons.PersonID', '=', 'Users.PersonID')
        ->leftJoin('PatientHistory as pher', 'pher.outpatienthistoryid', '=', 'PatientHistory.PatientHistoryID')
        ->leftJoin('AdmissionsOPD as adopd', 'adopd.admissionid', '=', 'pher.AdmissionOPDID')
        ->where('PatientHistory.PatientTypeID', 1)
        ->whereNotIn('PatientHistory.PatientStatusID', [6, 7, 8, 9, 10, 11, 13, 12])
        ->whereRaw('CAST(PatientHistory.TransactionDateTime AS DATE) BETWEEN DATEADD(DD, -20, CAST(GETDATE() AS DATE)) AND CAST(GETDATE() AS DATE)')
        ->where(function ($query) {
            $query->whereNull('PatientHistory.settoinpatient')
                ->orWhere('PatientHistory.SetToInPatient', 0);
        })
        ->where('Admissions.alert', '<>', '1')
        ->whereNull('Admissions.dateofdeath')
        ->whereNull('PatientHistory.mergedwithid')
        ->where('PatientHistory.status', 1)
        ->where('pher.PatientTypeID', 4)
        ->where('SAD.ServiceTypeID', $serviceTypeID)
        ->groupBy(PatientHistory::raw("CASE PatientHistory.PatientTypeID WHEN 1 THEN SAD.Description ELSE SAD.Description END"))
        ->selectRaw('COUNT(PatientHistory.PatientHistoryID) as total')
        ->pluck('total');
    

        $totalerHours =PatientHistory::join('Patients', 'Patients.PatientID', '=', 'PatientHistory.PatientID')
            ->join('Persons', 'Persons.PersonID', '=', 'Patients.PersonID')
            ->leftJoin('Admissions', 'Admissions.AdmissionID', '=', 'PatientHistory.AdmissionID')
            ->leftJoin('ServiceTypes as SAD', 'SAD.ServiceTypeID', '=', 'Admissions.ServiceTypeID')
            ->join('PatientInfo', 'PatientInfo.PatientHistoryID', '=', 'PatientHistory.PatientHistoryID')
            ->join('Users', 'Users.UserID', '=', 'PatientHistory.UserID')
            ->join('Persons as UserPersons', 'UserPersons.PersonID', '=', 'Users.PersonID')
            ->leftJoin('PatientHistory as pher', 'pher.outpatienthistoryid', '=', 'PatientHistory.PatientHistoryID')
            ->leftJoin('AdmissionsOPD as adopd', 'adopd.admissionid', '=', 'pher.AdmissionOPDID')
            ->where('PatientHistory.PatientTypeID', 1)
            ->whereNotIn('PatientHistory.PatientStatusID', [6, 7, 8, 9, 10, 11, 13, 12])
            ->whereRaw('CAST(PatientHistory.TransactionDateTime AS DATE) BETWEEN DATEADD(DD, -20, CAST(GETDATE() AS DATE)) AND CAST(GETDATE() AS DATE)')
            ->where(function ($query) {
                $query->whereNull('PatientHistory.settoinpatient')
                    ->orWhere('PatientHistory.SetToInPatient', 0);
            })
            ->where('Admissions.alert', '<>', '1')
            ->whereNull('Admissions.dateofdeath')
            ->whereNull('PatientHistory.mergedwithid')
            ->where('PatientHistory.status', 1)
            ->where('pher.PatientTypeID', 4)
            ->where('SAD.ServiceTypeID', $serviceTypeID)
            ->selectRaw('DATEDIFF(hour, PatientHistory.TransactionDateTime, GETDATE()) AS totaler, PatientHistory.PatientHistoryID')
            ->get();

        $AdmittedACU = $countL = $countM = $countN = $countO = 0;

        foreach ($totalerHours as $totalerHour) {
            $ward = Rooms::join('patient', 'patient.roomid', '=', 'rooms.id')
                ->where('rooms.id', 2539)
                ->where('patient.patienthistoryid', $totalerHour->PatientHistoryID)
                ->count();

            if ($ward > 0) {
                $AdmittedACU++;
            }

            if($totalerHour->totaler >= 4) {
                $countO++;
            }elseif ($totalerHour->totaler >= 5 && $totalerHour->totaler <= 9) {
                $countL++;
            } elseif ($totalerHour->totaler >= 10 && $totalerHour->totaler <= 20) {
                $countM++;
            } elseif ($totalerHour->totaler >= 20) {
                $countN++;
            } 
        }

        $d += $withinFourHrs;
        $e += $moreThanFourHrs;
        $totalPatientsCount = $totalPatients ? $totalPatients->first() : 0;
        $totalAdmissionsCount = $totalAdmissions ? $totalAdmissions->first() : 0;
        $l += $countL;
        $m += $countM;
        $n += $countN;
        $o += $countO;
        $ongoingEDConsultationTotal += $totalPatients ? $totalPatients->first() : 0;
        $admittedStillAtEDTotal += $totalAdmissions ? $totalAdmissions->first() : 0;
        $totalPatientsTotal = $ongoingEDConsultationTotal + $admittedStillAtEDTotal;

        // $results[] = [
        //     'description' => $description,
        //     'service_type_id' => $serviceTypeID,
        //     'count_within_4_hours' => $withinFourHrs,
        //     'count_beyond_4_hours' => $moreThanFourHrs,
        //     'total_patients' => $totalPatients,
        //     'count_5_to_9_hours' => $countL,
        //     'count_10_to_20_hours' => $countM,
        //     'count_beyond_20_hours' => $countN,
        //     'count_less_than_5_hours' => $countO,
        //     'count_ACU' => $AdmittedACU,
        //     'total_admissions' => $totalAdmissions,
        //     'department_total' => $totalPatientsCount + $totalAdmissionsCount,
        // ];   
    }
    $results[] = [
        'ongoing_ED_consultation_total' => $ongoingEDConsultationTotal,
        'admitted_still_at_ED_total' => $admittedStillAtEDTotal,
        'total_patients_total' => $totalPatientsTotal,
    ];
    return $results;
}

    }
