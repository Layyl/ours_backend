<?php

namespace App\Http\Controllers\referral;

use App\Http\Controllers\Controller;
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
          'receivingHospitalInst.FacilityName as receivingHospitalDescription',
          'province.description as province', 'municipality.description as municipality', 'barangay.name as barangay',
          'p.birthDate',
          'p.gender',
          'p.firstName',
          'p.middleName',
          'p.lastName',
          'pr.patientFiles',
          'pr.referralID',
          'pr.*',
          
          referralHistory::raw("DATE_FORMAT(pr.created_at, '%b %d, %Y %h:%i %p') as formatted_created_at")
      )
      ->join('patientreferrals as pr', 'patientreferralhistory.referralID', '=', 'pr.referralID')
       ->join('activefacilities as referringHospitalInst', 'pr.referringHospital', '=', 'referringHospitalInst.HealthFacilityCodeShort')
      ->join('activefacilities as receivingHospitalInst', 'patientreferralhistory.receivingHospital', '=', 'receivingHospitalInst.HealthFacilityCodeShort')
      ->join('province', 'pr.provinceID', '=', 'province.provinceID')
      ->join('municipality', 'pr.municipalityID', '=', 'municipality.municipalityID')
      ->join('barangay', 'pr.barangayID', '=', 'barangay.barangayID')
      ->leftJoin('patients as p', 'pr.patientID', '=', 'p.patientID')
      ->where('patientreferralhistory.referralhistoryID', '=', $referralHistoryID)
      ->orderBy('pr.created_at', 'DESC')
      ->first();
    
        if(!$data) {
            return response()->json(['message' => 'No result found', 'status' => 404]);
        }
        $patientFilesArray = explode(', ', $data->patientFiles);
        $data->patientFiles = $patientFilesArray;
        $gender = $data->gender;
        $givenDateTime = $data->birthDate;
        $diff = $this->getDateDifference($givenDateTime);
        $years = $diff->y;
        $months = $diff->m;
        $days = $diff->d;
    
        $data->Age = $years. ' YRS ' . $months . ' MTHS ' . $days . ' DYS';
        $data->Gender = ($gender == 1) ? 'Male' : 'Female';
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