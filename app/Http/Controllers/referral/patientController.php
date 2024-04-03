<?php

namespace App\Http\Controllers\referral;

use App\Events\NewNotification;
use App\Http\Controllers\Controller;
use App\Models\referral\barangay;
use App\Models\referral\civilStatus;
use App\Models\referral\doctors;
use App\Models\referral\Messages;
use App\Models\referral\municipality;
use App\Models\referral\Notifications;
use App\Models\referral\patients;
use App\Models\referral\province;
use App\Models\referral\referralHistory;
use App\Models\referral\ReferralReasons;
use App\Models\referral\referrals;
use App\Models\referral\referringHCI;
use App\Models\referral\servicetypes;
use Carbon\Carbon;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

date_default_timezone_set('Asia/Manila');

class patientController extends Controller{
  
    public function searchPatients(Request $request){

        $lastName = $request->input('lastName');
        $firstName = $request->input('firstName');
        $middleName = $request->input('middleName');

        $query = patients::query()
            ->select('patients.patientID','patients.lastName', 'patients.firstName', 'patients.middleName', 'patients.suffix', 'patients.birthDate', 'patients.gender');

        if ($lastName) {
            $query->where('patients.lastName', 'like', '%' . $lastName . '%');
        }
        if ($firstName) {
            $query->where('patients.firstName', 'like', '%' . $firstName . '%');
        }
        if ($middleName) {
            $query->where('patients.middleName', 'like', '%' . $middleName . '%');
        }

        $patients = $query->get();

        return response()->json(['patients' => $patients], 200);
    }
    
    public function fetchPatientData(Request $request){
        $query = patients::query();
        $patientData = $query->where("patients.patientID",$request->patID)
       ->get();
        return $patientData;
    }

    public function fetchReferrals(Request $request){

        $lastName = $request->input('lastName');
        $firstName = $request->input('firstName');
        $middleName = $request->input('middleName');
        $referralID = $request->input('referralID');
    
        $query = Referrals::query()
            ->join('patients', 'patientreferrals.patientID', '=', 'patients.patientID')
            ->join('activefacilities', 'patientreferrals.referringHospital', '=', 'activefacilities.HealthFacilityCodeShort')
            ->selectRaw("CONCAT_WS(' ', patients.firstName, patients.middleName, patients.lastName, patients.suffix) as fullName")
            ->addSelect('activefacilities.FacilityName', 'patients.birthDate', 'patients.gender', 'patients.firstName', 'patients.middleName', 'patients.lastName', 'patientreferrals.*')
            ->selectRaw("DATE_FORMAT(patientreferrals.created_at, '%b %d, %Y %h:%i %p') as formatted_created_at")
            ->orderBy('patientreferrals.created_at', 'desc');
        if ($referralID) {
            $query->where('patientreferrals.referralID', $referralID);
        } else {
            if ($lastName) {
                $query->where('patients.lastName', 'like', '%' . $lastName . '%');
            }
            if ($firstName) {
                $query->where('patients.firstName', 'like', '%' . $firstName . '%');
            }
            if ($middleName) {
                $query->where('patients.middleName', 'like', '%' . $middleName . '%');
            }
        }
    
        $referrals = $query->get();
    
        return response()->json(['referrals' => $referrals], 200);
    
    }

    public function fetchReferralData(Request $request){
        $encryptedreferralHistoryID = $request->input('referralHistoryID');
        $referralHistoryID = Crypt::decrypt($encryptedreferralHistoryID);
        $hciID = $request->input('hciID');
    
        $query = referralHistory::query()
          ->select(
            'patientreferralhistory.*',
            'referringHospitalInst.FacilityName as referringHospitalDescription',
            'receivingHospitalInst.FacilityName as receivingHospitalDescription',
            'p.birthDate',
            'p.gender',
            'p.firstName',
            'p.middleName',
            'p.lastName',
            'pr.*',
            referralHistory::raw("DATE_FORMAT(pr.created_at, '%b %d, %Y %h:%i %p') as formatted_created_at")
        )
        ->join('patientreferrals as pr', 'patientreferralhistory.referralID', '=', 'pr.referralID')
        ->join('activefacilities as referringHospitalInst', 'pr.referringHospital', '=', 'referringHospitalInst.HealthFacilityCodeShort')
        ->join('activefacilities as receivingHospitalInst', 'patientreferralhistory.receivingHospital', '=', 'receivingHospitalInst.HealthFacilityCodeShort')
        ->leftJoin('patients as p', 'pr.patientID', '=', 'p.patientID')
        ->where('patientreferralhistory.referralhistoryID', '=', $referralHistoryID)
        ->orderBy('pr.created_at', 'DESC')
        ->get();
        foreach ($query as $referral) {
            $referralHistories = referralHistory::where('referralID', $referral->referralID)
                ->join('activefacilities as receivingHospitalInst', 'patientreferralhistory.receivingHospital', '=', 'receivingHospitalInst.HealthFacilityCodeShort')
                ->addSelect('receivingHospitalInst.FacilityName as receivingHospitalDescription', 'receivingHospitalInst.FacilityName as receivingHospitalDescription','patientreferralhistory.*')
                ->selectRaw("DATE_FORMAT(created_at, '%b %d, %Y %h:%i %p') as formatted_created_at")
                ->orderBy('created_at', 'desc')
                ->get();
            
            foreach ($referralHistories as $history) {
                $history->encryptedReferralID = Crypt::encrypt($history->referralID);
                $history->encryptedReferralHistoryID = Crypt::encrypt($history->referralHistoryID);
            }
            
            $referral->referralHistory = $referralHistories;
        }
        return response()->json(['referrals' => $query], 200);
    
    }

    public function fetchReferralMessages(Request $request){
        $encryptedReferralHistoryID = $request->input('referralHistoryID');
        $referralHistoryID = Crypt::decrypt($encryptedReferralHistoryID);
        $patientMessages = Messages::where('referralHistoryID', $referralHistoryID)
        ->join('users as u', 'messages.user_id', '=', 'u.id')
            ->select('messages.user_id', 'messages.message', 'messages.referralHistoryID', 'u.username',
                Messages::raw("DATE_FORMAT(sent_at, '%M %e, %Y') as sent_date"), 
                Messages::raw("DATE_FORMAT(sent_at, '%l:%i %p') as sent_time"))
            ->get();
    
        return $patientMessages;
    }

    public function fetchInboundPatients(Request $request){

        $hciID = $request->input('hciID');
        if (is_null($hciID) || $hciID === '') {
            return response()->json(['referrals' => []], 200);
        }
    
        $lastName = $request->input('lastName');
        $firstName = $request->input('firstName');
        $middleName = $request->input('middleName');
        $referralID = $request->input('referralID');
        $limit = $request->input('limit');
    
        $query = referralHistory::query()
            ->where('patientreferralhistory.receivingHospital', '=', $hciID)
            ->join('patientreferrals as pr', 'patientreferralhistory.referralID', '=', 'pr.referralID')
            ->join('activefacilities as referringHospitalInst', 'pr.referringHospital', '=', 'referringHospitalInst.HealthFacilityCodeShort')
            ->join('activefacilities as receivingHospitalInst', 'patientreferralhistory.receivingHospital', '=', 'receivingHospitalInst.HealthFacilityCodeShort')
            ->leftJoin('patients as p', 'pr.patientID', '=', 'p.patientID')
            ->selectRaw("CONCAT_WS(' ', p.firstName, p.middleName, p.lastName, p.suffix) as fullName")
            ->addSelect('patientreferralhistory.*','referringHospitalInst.FacilityName as referringHospitalDescription', 'receivingHospitalInst.FacilityName as receivingHospitalDescription', 'p.birthDate', 'p.gender', 'p.firstName', 'p.middleName', 'p.lastName', 'pr.*')          
            ->selectRaw("DATE_FORMAT(pr.created_at, '%b %d, %Y %h:%i %p') as formatted_created_at")
            ->orderBy('patientreferralhistory.created_at','desc');
            if ($referralID) {
                $query->where('pr.referralID', $referralID);
            } else {
                if ($lastName) {
                    $query->where('p.lastName', 'like', '%' . $lastName . '%');
                }
                if ($firstName) {
                    $query->where('p.firstName', 'like', '%' . $firstName . '%');
                }
                if ($middleName) {
                    $query->where('p.middleName', 'like', '%' . $middleName . '%');
                }
            }
        
            if ($limit) {
                $query->limit($limit);
            }
        
            $referrals = $query->get();
            foreach ($referrals as $referral) {
                $referral->encryptedReferralID = Crypt::encrypt($referral->referralID);
                $referral->encryptedReferralHistoryID = Crypt::encrypt($referral->referralHistoryID);
            }
            return response()->json(['referrals' => $referrals], 200);
    
    }
    
    public function fetchOutboundPatients(Request $request){

        $hciID = $request->input('hciID');
        
        if (is_null($hciID) || $hciID === '') {
            return response()->json(['referrals' => []], 200);
        }
    
        $lastName = $request->input('lastName');
        $firstName = $request->input('firstName');
        $middleName = $request->input('middleName');
        $referralID = $request->input('referralID');
        
        $patientReferrals = Referrals::join('patients', 'patientreferrals.patientID', '=', 'patients.patientID')
        ->join('activefacilities as referringHospitalInst', 'patientreferrals.referringHospital', '=', 'referringHospitalInst.HealthFacilityCodeShort')

        ->addSelect('referringHospitalInst.FacilityName as referringHospitalDescription', 'patients.birthDate', 'patients.gender', 'patients.firstName', 'patients.middleName', 'patients.lastName', 'patientreferrals.*')
        ->selectRaw("DATE_FORMAT(patientreferrals.created_at, '%b %d, %Y %h:%i %p') as formatted_created_at")
        ->selectRaw("CONCAT_WS(' ', patients.firstName, patients.middleName, patients.lastName, patients.suffix) as fullName")
        ->where('patientreferrals.referringHospital', $hciID)
        ->when($referralID, function ($query) use ($referralID) {
            return $query->where('patientreferrals.referralID', $referralID);
        })
        ->when(!$referralID, function ($query) use ($lastName, $firstName, $middleName) {
            $query->when($lastName, function ($query) use ($lastName) {
                $query->where('patients.lastName', 'like', '%' . $lastName . '%');
            })->when($firstName, function ($query) use ($firstName) {
                $query->where('patients.firstName', 'like', '%' . $firstName . '%');
            })->when($middleName, function ($query) use ($middleName) {
                $query->where('patients.middleName', 'like', '%' . $middleName . '%');
            });
        })
        ->orderBy('patientreferrals.created_at', 'desc')
        ->get();
            foreach ($patientReferrals as $referral) {
                $referralHistories = referralHistory::where('referralID', $referral->referralID)
                    ->join('activefacilities as receivingHospitalInst', 'patientreferralhistory.receivingHospital', '=', 'receivingHospitalInst.HealthFacilityCodeShort')
                    ->addSelect('receivingHospitalInst.FacilityName as receivingHospitalDescription', 'receivingHospitalInst.FacilityName as receivingHospitalDescription','patientreferralhistory.*')
                    ->selectRaw("DATE_FORMAT(created_at, '%b %d, %Y %h:%i %p') as formatted_created_at")
                    ->orderBy('created_at', 'desc')
                    ->get();
                
                foreach ($referralHistories as $history) {
                    $history->encryptedReferralID = Crypt::encrypt($history->referralID);
                    $history->encryptedReferralHistoryID = Crypt::encrypt($history->referralHistoryID);
                }
                
                $referral->referralHistory = $referralHistories;
            }
                return response()->json(['referrals' => $patientReferrals], 200);
        
    }
    
    public function acceptPatient(Request $request){
        $updateMain = referrals::where("referralID", $request->referralID)
        ->update(['receivingDepartment' => $request->receivingDepartment,
        'assignedDoctor' => $request->assignedDoctor]);    

        $updateHistory = referralHistory::where("referralHistoryID", $request-> referralHistoryID)
        ->update(['referralStatus'=> 3]);

        $user = Auth::user();
        $user_id = $user->id;
        $sent_to = $request->referringHospital;
        $notification = sprintf("Your Patient %s %s %s has been accepted", $request->firstName, $request->middleName, $request->lastName);
        $dateTime = Carbon::now();
        $date = $dateTime->format('F j, Y'); 
        $time = $dateTime->format('g:i A');
        
        $notif = new Notifications();
        $notif->notification = $notification;
        $notif->notificationType = 1;
        $notif->referralID = $request->referralID;
        $notif->user_id = $user->id; 
        $notif->sent_to = $sent_to;
        $notif->sent_at = $dateTime;
        $notif->save();

        event(new NewNotification($notification, $user_id, 1, $request->referralHistoryID, $sent_to, $date, $time));

        return response()->json(["message" => "Success"], 200);
    }

    public function updateVitalSigns(Request $request){
    
        $updated = referrals::where("referralID", $request->referralID)
        ->update(['height' => $request->height,
                  'systolic' => $request->systolic,
                  'diastolic' => $request->diastolic,
                  'weight' => $request->weight,
                  'oxygenSaturation' => $request->oxygenSaturation,
                  'bmi' => $request->bmi,
                  'temperature' => $request->temperature,
                  'respiratoryRate' => $request->respiratoryRate,
                  'pulseRate' => $request->pulseRate,
                  'cardiacRate' => $request->cardiacRate,
                  'cbg' => $request->cbg,
                  'painScale' => $request->painScale,
                  'e' => $request->e,
                  'v' => $request->v,
                  'm' => $request->m,
                  'gcs' => $request->gcs,]);    

        return response()->json(["message" => "Success"], 200);
    }

    public function deferPatient(Request $request){  
        $updateHistory = referralHistory::where("referralHistoryID", $request-> referralHistoryID)
        ->update(['referralStatus'=> $request->deferReason]);
        return response()->json(["message" => "Success"], 200);
    }
   
    public function reopenReferral(Request $request){
        $updated = referralHistory::where("referralID", $request->referralID)
        ->update(['referralStatus' => '1']);    

        return response()->json(["message" => "Success"], 200);
    }
    
    public function createNewReferral(Request $request){

        $birthdate = date('Y-m-d', strtotime($request->birthDate));
        $referrerContact = (int)preg_replace('/[^0-9]/', '', $request->referrerContact);
        $patientContact = (int)preg_replace('/[^0-9]/', '', $request->patientContact);
        $informantContact = (int)preg_replace('/[^0-9]/', '', $request->informantContact);
        
        if(isset($request->newReferral)){
        $newReferral = $request->newReferral;
        }else{
            $newReferral = 0;
        }

        if($newReferral == 1){
        $patient = patients::create([
            "lastName" => $request->lastName,
            "firstName" => $request->firstName,
            "middleName" => $request->middleName,
            "suffix" => $request->suffix,
            "birthDate" => $birthdate,
            "gender" => $request->gender,
            "created_by" => $request->created_by 
        ]);
        $patientID = $patient->id;
        }else{
            $patientID = $request->patientID;
        }

        $referral = referrals::create([
            'patientID' => $patientID,
            'referringHospital' => $request->referringHospital,
            'referringDoctor' => $request->referringDoctor,
            'referrerContact' => $referrerContact,
            'transferReason' => $request->transferReason,
            'referralRemarks' => $request->referralRemarks,
            'receivingDepartment' => $request->receivingDepartment,
            'assignedDoctor' => $request->assignedDoctor,
            'street' => $request->street,
            'provinceID' => $request->provinceID,
            'municipalityID' => $request->municipalityID,
            'barangayID' => $request->barangayID,
            'civilStatus' => $request->civilStatus,
            'patientContact' => $patientContact,
            'informantName' => $request->informantName,
            'informantRelationship' => $request->informantRelationship,
            'informantContact' => $informantContact,
            'impression' => $request->impression,
            'chiefComplaint' => $request->chiefComplaint,
            'history' => $request->history,
            'examinationFindings' => $request->examinationFindings,
            'laboratories' => $request->laboratories,
            'imaging' => $request->imaging,
            'medicalInterventions' => $request->medicalInterventions,
            'courseInTheWard' => $request->courseInTheWard,
            'diagnosticsDone' => $request->diagnosticsDone,
            'height' => $request->height,
            'systolic' => $request->systolic,
            'diastolic' => $request->diastolic,
            'weight' => $request->weight,
            'oxygenSaturation' => $request->oxygenSaturation,
            'bmi' => $request->bmi,
            'temperature' => $request->temperature,
            'respiratoryRate' => $request->respiratoryRate,
            'pulseRate' => $request->pulseRate,
            'cardiacRate' => $request->cardiacRate,
            'cbg' => $request->cbg,
            'painScale' => $request->painScale,
            'e' => $request->e,
            'v' => $request->v,
            'm' => $request->m,
            'gcs' => $request->gcs,
            'patientFiles' => $request->patientFiles,
            'referralToFill' => $request->referralToFill,
            'addedBy' => $request->addedBy,
            'updatedBy' => $request->updatedBy,
            'status' => 1,
        ]);
        $referralHistory = referralHistory::create([
            'referralID' => $referral->id,
            'receivingHospital' => $request->receivingHospital,
            'referralStatus' => 1,
        ]);
    
        $referralID = $referral->id;
        $referralHistoryID = $referralHistory->referralHistoryID;

        $user = Auth::user();
        $user_id = $user->id;
        $sent_to = $request->receivingHospital;
        $notification = sprintf("You have an new referral: %s %s %s", $request->firstName, $request->middleName, $request->lastName);
        $dateTime = Carbon::now();
        $date = $dateTime->format('F j, Y'); 
        $time = $dateTime->format('g:i A');
        
        $notif = new Notifications();
        $notif->notification = $notification;
        $notif->notificationType = 4;
        $notif->referralID = $referralID;
        $notif->user_id = $user->id; 
        $notif->sent_to = $sent_to;
        $notif->sent_at = $dateTime;
        $notif->save();

        event(new NewNotification($notification, $user_id, 3, $referralHistoryID, $sent_to, $date, $time));
        return response()->json(["message" => "Referral Created", "referralID" => $referralID], 200);
    }
    
    public function transferToOtherHCI(Request $request){

        $referralID = $request->referralID;
        $referralHistoryID = $request->referralHistoryID;

        $update = referralHistory::where("referralHistoryID", $referralHistoryID)
        ->update(['referralStatus' => 7]);    

        $referralHistory = referralHistory::create([
            'referralID' => $referralID,
            'receivingHospital' => $request->newReceivingHospital,
            'referralStatus' => 1,
        ]);
    
        $referralID = $referralHistory->id;

        $user = Auth::user();
        $user_id = $user->id;
        $sent_to = $request->referringHospital;
        $notification = sprintf("Your Patient %s %s %s has been referred to another Healthcare Institution", $request->firstName, $request->middleName, $request->lastName);
        $dateTime = Carbon::now();
        $date = $dateTime->format('F j, Y'); 
        $time = $dateTime->format('g:i A');
        
        $notif = new Notifications();
        $notif->notification = $notification;
        $notif->notificationType = 3;
        $notif->referralID = $request->referralID;
        $notif->user_id = $user->id; 
        $notif->sent_to = $sent_to;
        $notif->sent_at = $dateTime;
        $notif->save();

        event(new NewNotification($notification, $user_id, 3, $request->referralHistoryID, $sent_to, $date, $time));


        return response()->json(["message" => "Referral Created", "referralID" => $referralID], 200);
    }

    public function transferToOPCEN(Request $request){

        $referralID = $request->referralID;
        $referralHistoryID = $request->referralHistoryID;

        $update = referralHistory::where("referralHistoryID", $referralHistoryID)
        ->update(['referralStatus' => 6]);    

        $referralHistory = referralHistory::create([
            'referralID' => $referralID,
            'receivingHospital' => 100000,
            'referralStatus' => 1,
        ]);
    
        $referralID = $referralHistory->id;
        
        $user = Auth::user();
        $user_id = $user->id;
        $sent_to = $request->referringHospital;
        $notification = sprintf("Your Patient %s %s %s has been referred to OPCEN", $request->firstName, $request->middleName, $request->lastName);
        $dateTime = Carbon::now();
        $date = $dateTime->format('F j, Y'); 
        $time = $dateTime->format('g:i A');
        
        $notif = new Notifications();
        $notif->notification = $notification;
        $notif->notificationType = 2;
        $notif->referralID = $request->referralID;
        $notif->user_id = $user->id; 
        $notif->sent_to = $sent_to;
        $notif->sent_at = $dateTime;
        $notif->save();

        event(new NewNotification($notification, $user_id, 2, $request->referralHistoryID, $sent_to, $date, $time));

        return response()->json(["message" => "Referral Created", "referralID" => $referralID], 200);


    }

    public function OPCENToOtherHCI(Request $request){

        $referralID = $request->referralID;
        $referralHistoryID = $request->referralHistoryID;

        $update = referralHistory::where("referralHistoryID", $referralHistoryID)
        ->update(['referralStatus' => 7]);    

        $referralHistory = referralHistory::create([
            'referralID' => $referralID,
            'receivingHospital' => $request->newReceivingHospital,
            'referralStatus' => 3,
            'arrived' => 1,
        ]);
    
        $referralID = $referralHistory->id;

        $user = Auth::user();
        $user_id = $user->id;
        $sent_to = $request->referringHospital;
        $notification = sprintf("Your Patient %s %s %s has been referred to another Healthcare Institution by OPCEN", $request->firstName, $request->middleName, $request->lastName);
        $dateTime = Carbon::now();
        $date = $dateTime->format('F j, Y'); 
        $time = $dateTime->format('g:i A');
        
        $notif = new Notifications();
        $notif->notification = $notification;
        $notif->notificationType = 3;
        $notif->referralID = $request->referralID;
        $notif->user_id = $user->id; 
        $notif->sent_to = $sent_to;
        $notif->sent_at = $dateTime;
        $notif->save();

        event(new NewNotification($notification, $user_id, 3, $request->referralHistoryID, $sent_to, $date, $time));
        return response()->json(["message" => "Referral Created", "referralID" => $referralID], 200);
    }

    public function setToExpired(Request $request){

        $referralID = $request->referralID;
        $referralHistoryID = $request->referralHistoryID;
    
        $update = referralHistory::where("referralID", $referralHistoryID)
        ->whereDate('created_at', '<=', Carbon::now()->subDays(1))
        ->whereNotNull('arrived')
        ->where('arrived', '<>', '')
        ->update(['referralStatus' => '9']); 
    }
   
    public function fetchCivilStatus(){
        $data = civilStatus::get();
        return $data;
    }
    
    public function fetchDoctors(){
        $query = doctors::query();
        $doctors = $query->selectRaw("CONCAT(firstName, ' ', lastName) AS fullName, doctorID")
        ->where('status', 1)
        ->get();
        return $doctors;
    }

    public function fetchReferralReasons(){
        $data = ReferralReasons::where("status",1)
        ->get();
        return $data;
    }

    public function fetchProvince(){
        $data = province::where("status",1)
        ->get();
        return $data;
    }

    public function fetchMunicipality(Request $request){
        $data = municipality::where("ProvinceID", $request->ProvinceID)
        ->get();
        return $data;
    }

    public function fetchBarangay(Request $request){
        $data = barangay::where("MunicipalityID", $request->MunicipalityID)
        ->get();
        return $data;
    }

    public function fetchHealthCareInstitution(Request $request){
        if ($request->has('HealthFacilityCodeShort')) {
            $data = referringHCI::where('HealthFacilityCodeShort', $request->HealthFacilityCodeShort)
            ->get();
        } else if ($request->has('hciID')) {
            $hciID = $request->hciID;
            $data = referringHCI::where('HealthFacilityCodeShort', '!=', $hciID)
            ->orWhereNull('HealthFacilityCodeShort')
            ->get();
            } else {
                $data = referringHCI::get();
            }
        return $data;
    }

    public function fetchServiceTypes(Request $request){
        $data = servicetypes::get();
        return $data;
    }
   
    public function fetchDashboardCensus(Request $request){

        $hciID = $request->input('hciID');
        if (is_null($hciID) || $hciID === '') {
            return response()->json(['error' => 'Healthcare institution ID is required.'], 400);
        }
    
        // Inbound Patients Count
        $inboundCount = referralHistory::query()
            ->where('receivingHospital', '=', $hciID)
            ->where('referralStatus', '<>', 0)
            ->count();
    
        // Outbound Patients Count
        $outboundCount = Referrals::query()
            ->where('referringHospital', '=', $hciID)
            ->where('status', '<>', 0)
            ->count();
    
        // Accepted Patients Count
        $acceptedCount = referralHistory::query()
            ->where('receivingHospital', '=', $hciID)
            ->where('referralStatus', '=', 2)
            ->count();
    
        // Deferred Patients Count
        $deferredCount = referralHistory::query()
            ->where('receivingHospital', '=', $hciID)
            ->whereIn('referralStatus', [4, 5])
            ->count();
    
        return response()->json([
            'inboundCount' => $inboundCount,
            'outboundCount' => $outboundCount,
            'acceptedCount' => $acceptedCount,
            'deferredCount' => $deferredCount,
        ], 200);
    }
}
