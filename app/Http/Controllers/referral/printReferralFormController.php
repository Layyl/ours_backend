<?php

namespace App\Http\Controllers\referral;

use App\Http\Controllers\Controller;
use App\Models\referral\barangay;
use App\Models\referral\civilStatus;
use App\Models\referral\doctors;
use App\Models\referral\municipality;
use App\Models\referral\province;
use App\Models\referral\referralHistory;
use App\Models\referral\referrals;
use Barryvdh\DomPDF\Facade as PDF;
use Dompdf\Dompdf;
use Illuminate\Http\Request;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Crypt;

class printReferralFormController extends Controller
{
    
    public function getReferralForm(Request $request) {
        $encryptedreferralHistoryID = $request->input('referralHistoryID');
        $referralHistoryID = Crypt::decrypt($encryptedreferralHistoryID);
        $data = referralHistory::query()
        ->select(
            'patientreferralhistory.*',
            'referringHospitalInst.FacilityName as referringHospitalDescription',
            'referringHospitalInst.FacilityName as referringHospitalDescription',
            'referringHospitalInst.street as referringHospitalStreet',
            'referringHospitalInst.barangay as referringHospitalBarangay',
            'referringHospitalInst.municipality as referringHospitalMunicipality',
            'referringHospitalInst.province as referringHospitalProvince',
            'pr.patientFiles',
            'pr.referralID',
            'pr.*',
            'rr.Description as reasonForReferral',
            'st1.serviceType as st1',
            'st2.serviceType as st2',
            referralHistory::raw("DATE_FORMAT(pr.created_at, '%M %d, %Y') as referralDate"),
            referralHistory::raw("DATE_FORMAT(pr.created_at, '%h:%i %p') as referralTime")
        )
        ->leftJoin('patientreferrals as pr', 'patientreferralhistory.referralID', '=', 'pr.referralID')
        ->leftJoin('referralreasons as rr', 'pr.transferReason', '=', 'rr.id')
        ->leftJoin('activefacilities as referringHospitalInst', 'pr.referringHospital', '=', 'referringHospitalInst.HealthFacilityCodeShort')
        ->leftJoin('activefacilities as receivingHospitalInst', 'patientreferralhistory.receivingHospital', '=', 'receivingHospitalInst.HealthFacilityCodeShort')
        ->leftJoin('servicetypes as st1', 'pr.receivingDepartment', '=', 'st1.serviceTypeID')
        ->leftJoin('servicetypes as st2', 'pr.secondaryReceivingDepartment', '=', 'st2.serviceTypeID')
        ->where('patientreferralhistory.referralhistoryID', '=', $referralHistoryID)
        ->orderBy('pr.created_at', 'DESC')
        ->first();

    if (!$data->serviceType) {
        $data->serviceType = $data->receivingDepartment;
    }
    
        if(!$data) {
            return response()->json(['message' => 'No result found', 'status' => 404]);
        }
        if (!empty($data->patientFiles)) {
            $patientFilesArray = explode(', ', $data->patientFiles);
            $data->patientFiles = $patientFilesArray;
        }
        
        $gender = $data->gender;
        $provinceID = $data->provinceID;
        $municipalityID = $data->municipalityID;
        $barangayID = $data->barangayID;
        $doctorID = $data->assignedDoctor;
        $doctorID2 = $data->secondaryAssignedDoctor;
        $civilStatusID = $data->civilStatus;

        $provinceDesc = province::where("status", 1)
        ->where("ProvinceID", $provinceID)
        ->value('Description');
    
        $municipalityDesc = municipality::where("status", 1)
            ->where("MunicipalityID", $municipalityID)
            ->value('Description');
        
        $barangayDesc = barangay::where("status", 1)
            ->where("Id", $barangayID)
            ->value('Name');
        
            if(!empty($doctorID)){
                $doctorName = doctors::selectRaw("CONCAT(payroll.name, ' ', payroll.lname) AS fullName")
                    ->join('position', 'payroll.positionid', '=', 'position.positionid')
                    ->join('department', 'payroll.department', '=', 'department.id')
                    ->where('payroll.status', 'A')
                    ->where(function ($query) {
                        $query->whereBetween('payroll.positionid', [47, 57])
                            ->orWhere('payroll.positionid', 34)
                            ->orWhereBetween('payroll.positionid', [23, 25]);
                    })
                    ->where('payroll.id', $doctorID)
                    ->first();
            
                if ($doctorName) {
                    $data->doctorName = strtoupper($doctorName->fullName);
                } else {
                    $data->doctorName = strtoupper($doctorID);
                }
            }

            if(!empty($doctorID2)){
                $doctorName2 = doctors::selectRaw("CONCAT(payroll.name, ' ', payroll.lname) AS fullName")
                    ->join('position', 'payroll.positionid', '=', 'position.positionid')
                    ->join('department', 'payroll.department', '=', 'department.id')
                    ->where('payroll.status', 'A')
                    ->where(function ($query) {
                        $query->whereBetween('payroll.positionid', [47, 57])
                            ->orWhere('payroll.positionid', 34)
                            ->orWhereBetween('payroll.positionid', [23, 25]);
                    })
                    ->where('payroll.id', $doctorID2)
                    ->first();
            
                if ($doctorName) {
                    $data->doctorName2 = strtoupper($doctorName2->fullName);
                } else {
                    $data->doctorName2 = strtoupper($doctorID2);
                }
            }
            
        $civilStatusDesc = civilStatus::where('CivilStatusID',$civilStatusID)->first();

        $data->provinceDesc = $provinceDesc ?? '';
        $data->municipalityDesc = $municipalityDesc ?? '';
        $data->barangayDesc = strtoupper($barangayDesc) ?? '';
        
        $data->civilStatusDesc = isset($civilStatusDesc->Name) ? strtoupper($civilStatusDesc->Name) : '';

        
            

        $givenDateTime = $data->birthDate;
        $diff = $this->getDateDifference($givenDateTime);
        $ageString = '';
        
        $years = $diff->y;
        if ($years > 0) {
            $ageString .= $years . ' YRS ';
        }
        
        $months = $diff->m;
        if ($months > 0 || $ageString !== '') {
            $ageString .= $months . ' MTHS ';
        }
        
        $days = $diff->d;
        if ($days > 0 || $ageString !== '') {
            $ageString .= $days . ' DYS ';
        }
        
        $data->Age = trim($ageString);
        $data->Gender = ($gender == 1) ? 'MALE' : 'FEMALE';
        $html = view('forms.referralForm', ['data' => $data])->render();
        $dompdf = new DomPDF(['paper' => 'A4', 'orientation' => 'portrait']);
        $dompdf->loadHtml($html);
        $dompdf->render();

        return $dompdf->stream("ReferralForm.pdf", array("Attachment" => false));
    }
    
private function getDateDifference($givenDateTime){
    $givenDate = Carbon::parse($givenDateTime);
    $currentDate = Carbon::now();
    $diff = $currentDate->diff($givenDate);
    return $diff;
}
}