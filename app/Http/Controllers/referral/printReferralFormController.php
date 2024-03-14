<?php

namespace App\Http\Controllers\referral;

use App\Http\Controllers\Controller;
use App\Models\referral\referrals;
use Barryvdh\DomPDF\Facade as PDF;
use Dompdf\Dompdf;
use Illuminate\Http\Request;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
class printReferralFormController extends Controller
{
    
    public function getReferralForm(Request $request) {
        $referralID = $request->input('referralID');
        $data = referrals::query()
            ->join('patients', 'patientreferrals.patientID', '=', 'patients.patientID')
            ->join('healthcareinstitutions', 'patientreferrals.referringHospital', '=', 'healthcareinstitutions.healthcareInstitutionID')
            ->join('province', 'patientreferrals.provinceID', '=', 'province.provinceID')
            ->join('municipality', 'patientreferrals.municipalityID', '=', 'municipality.municipalityID')
            ->join('barangay', 'patientreferrals.barangayID', '=', 'barangay.barangayID')
            ->join('referralreasons', 'patientreferrals.transferReason', '=', 'referralreasons.id')
            ->leftJoin('servicetypes', function($join) {
                $join->on('patientreferrals.receivingDepartment', '=', 'servicetypes.serviceTypeID')
                    ->whereNotNull('patientreferrals.receivingDepartment');
            })
            ->selectRaw("CONCAT_WS(' ', patients.firstName, patients.middleName, patients.lastName, patients.suffix) as fullName")
            ->addSelect('healthcareinstitutions.description', 'serviceTypes.serviceType as assignedDepartment', 'referralreasons.Description as reasonForReferral','patients.birthDate', 'patients.gender', 'patients.firstName', 'patients.middleName', 'patients.lastName','province.description as province', 'municipality.description as municipality', 'barangay.name as barangay', 'patientreferrals.*')
            ->selectRaw("DATE_FORMAT(patientreferrals.created_at, '%b %d, %Y') as referralDate")
            ->selectRaw("DATE_FORMAT(patientreferrals.created_at, '%h:%i %p') as referralTime")
            ->where('patientreferrals.referralID', $referralID)
            ->orderBy('patientreferrals.created_at', 'desc')
            ->first();
    
        if(!$data) {
            return response()->json(['message' => 'No result found', 'status' => 404]);
        }
    
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