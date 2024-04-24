<?php

namespace App\Http\Controllers\referral;

use App\Events\NewNotification;
use App\Http\Controllers\Controller;
use App\Models\medixPatients;
use App\Models\referral\barangay;
use App\Models\referral\civilStatus;
use App\Models\referral\doctors;
use App\Models\referral\Messages;
use App\Models\referral\municipality;
use App\Models\referral\Nationality;
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
  
    private function getDateDifference($givenDateTime){
        $givenDate = Carbon::parse($givenDateTime);
        $currentDate = Carbon::now();
        $diff = $currentDate->diff($givenDate);
        return $diff;
    }

    public function searchPatients(Request $request){
        $patientNo = $request->input('patientNo');
        $lastName = $request->input('lastName');
        $firstName = $request->input('firstName');
        $middleName = $request->input('middleName');
    
        $patients = medixPatients::whereNotNull('p.PatientNo')->select([
            'PatientHistory.PatientHistoryID',
            'PatientHistory.PatientID',
            'pi.FirstName as firstName',
            'pi.MiddleName as middleName',
            'pi.LastName as lastName',
            'p.PatientNo',
            'pr.SuffixName as suffix',
            'pr.Gender as gender',
            'pr.BirthDate',
            'pr.CivilStatusID as civilStatus',
            'permAdr.Street AS street',
            'permAdr.MobileNo AS permanent_mobile',
            'permAdr.RegionID AS permanent_region',
            'permAdr.ProvinceID AS provinceID',
            'permAdr.MunicipalityID AS municipalityID',
            'permAdr.Barangay'
        ])
        ->join(medixPatients::raw('(SELECT PatientID, MAX(PatientHistoryID) AS LatestPatientHistoryID FROM PatientHistory WHERE PatientTypeID != 6 GROUP BY PatientID) AS latest'), function($join) {
            $join->on('PatientHistory.PatientID', '=', 'latest.PatientID')
                ->on('PatientHistory.PatientHistoryID', '=', 'latest.LatestPatientHistoryID');
        })
        ->join('PatientInfo AS pi', 'PatientHistory.PatientHistoryID', '=', 'pi.PatientHistoryID')
        ->join('Patients AS p', 'PatientHistory.PatientID', '=', 'p.PatientID')
        ->join('Persons AS pr', 'p.PersonID', '=', 'pr.PersonID')
        ->leftJoin('Address AS currAdr', function($join) {
            $join->on('PatientHistory.PatientHistoryID', '=', 'currAdr.ObjID')
                ->where('currAdr.EntityID', '=', 19);
        })
        ->leftJoin('Address AS permAdr', function($join) {
            $join->on('PatientHistory.PatientHistoryID', '=', 'permAdr.ObjID')
                ->where('permAdr.EntityID', '=', 4);
        })
        
        ->when($request->firstName, function ($query) use ($request) {
            $query->where('pi.FirstName', 'LIKE', '%' . $request->firstName . '%');
        })
        ->when($request->middleName, function ($query) use ($request) {
            $query->where('pi.MiddleName', 'LIKE', '%' . $request->middleName . '%');
        })
        ->when($request->lastName, function ($query) use ($request) {
            $query->where('pi.LastName', 'LIKE', '%' . $request->lastName . '%');
        })
        ->when($request->hospitalNo, function ($query) use ($request) {
        $encryptedPatientID = $request->input('hospitalNo');
        $hospitalNo = Crypt::decrypt($encryptedPatientID);
        $query->where('p.PatientID', $hospitalNo);
        })
        ->get();

        foreach ($patients as $patient) {
        $PermanentBarangay = Barangay::where('MunicipalityId', $patient->municipalityID)
        ->where('Name', $patient->Barangay)
        ->select('Id AS BarangayID')
        ->first();
        unset($patient->Barangay);
        $patient->barangayID = $PermanentBarangay ? $PermanentBarangay->BarangayID : null;
        }

        foreach ($patients as $pat) {
            $pat->encryptedPatientID = Crypt::encrypt($pat->PatientID);
        }
        return response()->json(['patients' => $patients], 200);
    }
    
    public function fetchPatientData(Request $request){
        $query = referrals::query();
        $patientData = $query->where("patientreferrals.patientID",$request->patID)
       ->get();
        return $patientData;
    }

    public function fetchReferrals(Request $request){

        $lastName = $request->input('lastName');
        $firstName = $request->input('firstName');
        $middleName = $request->input('middleName');
        $referralID = $request->input('referralID');
    
        $query = Referrals::query()
            ->join('activefacilities', 'patientreferrals.referringHospital', '=', 'activefacilities.HealthFacilityCodeShort')
            ->selectRaw("CONCAT_WS(' ', patientreferrals.firstName, patientreferrals.middleName, patientreferrals.lastName, patientreferrals.suffix) as fullName")
            ->addSelect('activefacilities.FacilityName', 'patientreferrals.*')
            ->selectRaw("DATE_FORMAT(patientreferrals.created_at, '%b %d, %Y %h:%i %p') as formatted_created_at")
            ->orderBy('patientreferrals.created_at', 'desc');
        if ($referralID) {
            $query->where('patientreferrals.referralID', $referralID);
        } else {
            if ($lastName) {
                $query->where('patientreferrals.lastName', 'like', '%' . $lastName . '%');
            }
            if ($firstName) {
                $query->where('patientreferrals.firstName', 'like', '%' . $firstName . '%');
            }
            if ($middleName) {
                $query->where('patientreferrals.middleName', 'like', '%' . $middleName . '%');
            }
        }
    
        $referrals = $query->get();
    
        return response()->json(['referrals' => $referrals], 200);
    
    }

    public function fetchReferralData(Request $request){
        $encryptedreferralHistoryID = $request->input('referralHistoryID');
        $referralHistoryID = Crypt::decrypt($encryptedreferralHistoryID);
        $hciID = $request->input('hciID');
    
        $query = Referrals::query()
            ->select(
                'patientreferrals.*',
                'referringHospitalInst.FacilityName as referringHospitalDescription',
                'receivingHospitalInst.FacilityName as receivingHospitalDescription',
                'prh.*',
                Referrals::raw("DATE_FORMAT(patientreferrals.created_at, '%b %d, %Y %h:%i %p') as formatted_created_at")
            )
            ->leftJoin('patientreferralhistory as prh', 'patientreferrals.referralID', '=', 'prh.referralID')
            ->leftJoin('activefacilities as referringHospitalInst', 'patientreferrals.referringHospital', '=', 'referringHospitalInst.HealthFacilityCodeShort')
            ->leftJoin('activefacilities as receivingHospitalInst', 'prh.receivingHospital', '=', 'receivingHospitalInst.HealthFacilityCodeShort')
            ->where('prh.referralhistoryID', '=', $referralHistoryID)
            ->orderBy('patientreferrals.created_at', 'desc')
            ->get();
    
        foreach ($query as $referral) {
            $referralHistories = referralHistory::where('referralID', $referral->referralID)
                ->join('activefacilities as receivingHospitalInst', 'patientreferralhistory.receivingHospital', '=', 'receivingHospitalInst.HealthFacilityCodeShort')
                ->addSelect('receivingHospitalInst.FacilityName as receivingHospitalDescription', 'receivingHospitalInst.FacilityName as receivingHospitalDescription', 'patientreferralhistory.*')
                ->selectRaw("DATE_FORMAT(patientreferralhistory.created_at, '%b %d, %Y %h:%i %p') as formatted_created_at")
                ->orderBy('created_at', 'desc')
                ->get();
    
            foreach ($referralHistories as $history) {
                $history->encryptedReferralHistoryID = Crypt::encrypt($history->referralHistoryID);
            }
    
            $referral->EncryptedReferralID = Crypt::encrypt($referral->referralID);
            $referral->referralHistory = $referralHistories;
        }
        return response()->json(['referrals' => $query], 200);
    }
    
    public function fetchReferralDataMasterfile(Request $request){
        $encryptedreferralID = $request->input('referralID');
        $referralID = Crypt::decrypt($encryptedreferralID);
        $hciID = $request->input('hciID');
    
        $query = Referrals::query()
            ->select(
                'patientreferrals.*',
                'referringHospitalInst.FacilityName as referringHospitalDescription',
                'receivingHospitalInst.FacilityName as receivingHospitalDescription',
                'prh.*',
                Referrals::raw("DATE_FORMAT(patientreferrals.created_at, '%b %d, %Y %h:%i %p') as formatted_created_at")
            )
            ->leftJoin('patientreferralhistory as prh', 'patientreferrals.referralID', '=', 'prh.referralID')
            ->leftJoin('activefacilities as referringHospitalInst', 'patientreferrals.referringHospital', '=', 'referringHospitalInst.HealthFacilityCodeShort')
            ->leftJoin('activefacilities as receivingHospitalInst', 'prh.receivingHospital', '=', 'receivingHospitalInst.HealthFacilityCodeShort')
            ->where('patientreferrals.referralID', '=', $referralID)
            ->orderBy('patientreferrals.created_at', 'desc')
            ->get();
    
        foreach ($query as $referral) {
            $referralHistories = referralHistory::where('referralID', $referral->referralID)
                ->join('activefacilities as receivingHospitalInst', 'patientreferralhistory.receivingHospital', '=', 'receivingHospitalInst.HealthFacilityCodeShort')
                ->addSelect('receivingHospitalInst.FacilityName as receivingHospitalDescription', 'receivingHospitalInst.FacilityName as receivingHospitalDescription', 'patientreferralhistory.*')
                ->selectRaw("DATE_FORMAT(patientreferralhistory.created_at, '%b %d, %Y %h:%i %p') as formatted_created_at")
                ->orderBy('patientreferralhistory.created_at', 'desc')
                ->get();
    
            foreach ($referralHistories as $history) {
                $history->encryptedReferralHistoryID = Crypt::encrypt($history->referralHistoryID);
            }
    
            $referral->EncryptedReferralID = Crypt::encrypt($referral->referralID);
            $referral->referralHistory = $referralHistories;
        }
        return response()->json(['referrals' => $query], 200);
    }

    public function fetchReferralMessages(Request $request){
        $encryptedReferralID = $request->input('referralID');
        $referralID = Crypt::decrypt($encryptedReferralID);
        $patientMessages = Messages::where('referralID', $referralID)
        ->join('users as u', 'messages.user_id', '=', 'u.id')
            ->select('messages.user_id', 'messages.message', 'messages.referralID', 'u.username',
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
            ->whereBetween('patientreferralhistory.created_at', [now()->subDays(1), now()])
            ->where('pr.status','1')
            ->leftJoin('patientreferrals as pr', 'patientreferralhistory.referralID', '=', 'pr.referralID')
            ->leftJoin('activefacilities as referringHospitalInst', 'pr.referringHospital', '=', 'referringHospitalInst.HealthFacilityCodeShort')
            ->leftJoin('activefacilities as receivingHospitalInst', 'patientreferralhistory.receivingHospital', '=', 'receivingHospitalInst.HealthFacilityCodeShort')
            ->selectRaw("CONCAT_WS(' ', pr.firstName, pr.middleName, pr.lastName, pr.suffix) as fullName")
            ->addSelect('patientreferralhistory.*','referringHospitalInst.FacilityName as referringHospitalDescription', 'receivingHospitalInst.FacilityName as receivingHospitalDescription', 'pr.*')          
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
                $givenDateTime = $referral->birthDate;
                $diff = $this->getDateDifference($givenDateTime);
                $years = $diff->y;
                $months = $diff->m;
                $days = $diff->d;
                $referral->Age = $years. ' YRS ' . $months . ' MTHS ' . $days . ' DYS';
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
        
        $patientReferrals = Referrals::leftJoin('activefacilities as referringHospitalInst', 'patientreferrals.referringHospital', '=', 'referringHospitalInst.HealthFacilityCodeShort')
        ->addSelect('referringHospitalInst.FacilityName as referringHospitalDescription', 'patientreferrals.birthDate', 'patientreferrals.gender', 'patientreferrals.firstName', 'patientreferrals.middleName', 'patientreferrals.lastName', 'patientreferrals.*')
        ->selectRaw("DATE_FORMAT(patientreferrals.created_at, '%b %d, %Y %h:%i %p') as formatted_created_at")
        ->selectRaw("CONCAT_WS(' ', patientreferrals.firstName, patientreferrals.middleName, patientreferrals.lastName, patientreferrals.suffix) as fullName")
        ->where('patientreferrals.referringHospital', $hciID)
        ->where('patientreferrals.status','1')
        ->when($referralID, function ($query) use ($referralID) {
            return $query->where('patientreferrals.referralID', $referralID);
        })
        ->when(!$referralID, function ($query) use ($lastName, $firstName, $middleName) {
            $query->when($lastName, function ($query) use ($lastName) {
                $query->where('patientreferrals.lastName', 'like', '%' . $lastName . '%');
            })->when($firstName, function ($query) use ($firstName) {
                $query->where('patientreferrals.firstName', 'like', '%' . $firstName . '%');
            })->when($middleName, function ($query) use ($middleName) {
                $query->where('patientreferrals.middleName', 'like', '%' . $middleName . '%');
            });
        })
        ->orderBy('patientreferrals.created_at', 'desc')
        ->get();
            foreach ($patientReferrals as $referral) {
                $givenDateTime = $referral->birthDate;
                $diff = $this->getDateDifference($givenDateTime);
                $years = $diff->y;
                $months = $diff->m;
                $days = $diff->d;
                $referral->Age = $years. ' YRS ' . $months . ' MTHS ' . $days . ' DYS';
                $referralHistories = referralHistory::where('patientreferralhistory.referralID', $referral->referralID)
                ->leftJoin('patientreferrals', 'patientreferralhistory.referralID', '=', 'patientreferrals.referralID')
                ->leftJoin('activefacilities as receivingHospitalInst', 'patientreferralhistory.receivingHospital', '=', 'receivingHospitalInst.HealthFacilityCodeShort')
                ->addSelect('receivingHospitalInst.FacilityName as receivingHospitalDescription', 'receivingHospitalInst.FacilityName as receivingHospitalDescription','patientreferralhistory.*', 'patientreferrals.*')
                ->selectRaw("DATE_FORMAT(patientreferralhistory.created_at, '%b %d, %Y %h:%i %p') as formatted_created_at")
                ->orderBy('patientreferralhistory.created_at', 'desc')
                ->get();
            
                
                foreach ($referralHistories as $history) {
                    $history->encryptedReferralID = Crypt::encrypt($history->referralID);
                    $history->encryptedReferralHistoryID = Crypt::encrypt($history->referralHistoryID);
                }
                
                $referral->referralHistory = $referralHistories;
            }
                return response()->json(['referrals' => $patientReferrals], 200);
        
    }

    public function fetchMasterfile(Request $request){

        $hciID = $request->input('hciID');
        
        if (is_null($hciID) || $hciID === '') {
            return response()->json(['referrals' => []], 200);
        }
    
        $lastName = $request->input('lastName');
        $firstName = $request->input('firstName');
        $middleName = $request->input('middleName');
        $referralID = $request->input('referralID');
        
        $patientReferrals = Referrals::leftJoin('activefacilities as referringHospitalInst', 'patientreferrals.referringHospital', '=', 'referringHospitalInst.HealthFacilityCodeShort')
        ->addSelect('referringHospitalInst.FacilityName as referringHospitalDescription','patientreferrals.*')
        ->selectRaw("DATE_FORMAT(patientreferrals.created_at, '%b %d, %Y %h:%i %p') as formatted_created_at")
        ->selectRaw("CONCAT_WS(' ', patientreferrals.firstName, patientreferrals.middleName, patientreferrals.lastName, patientreferrals.suffix) as fullName")
        ->where('patientreferrals.status','1')
        ->when($referralID, function ($query) use ($referralID) {
            return $query->where('patientreferrals.referralID', $referralID);
        })
        ->when(!$referralID, function ($query) use ($lastName, $firstName, $middleName) {
            $query->when($lastName, function ($query) use ($lastName) {
                $query->where('patientreferrals.lastName', 'like', '%' . $lastName . '%');
            })->when($firstName, function ($query) use ($firstName) {
                $query->where('patientreferrals.firstName', 'like', '%' . $firstName . '%');
            })->when($middleName, function ($query) use ($middleName) {
                $query->where('patientreferrals.middleName', 'like', '%' . $middleName . '%');
            });
        })
        ->orderBy('patientreferrals.created_at', 'desc')
        ->get();
            foreach ($patientReferrals as $referral) {
                $referralHistories = referralHistory::where('referralID', $referral->referralID)
                    ->join('activefacilities as receivingHospitalInst', 'patientreferralhistory.receivingHospital', '=', 'receivingHospitalInst.HealthFacilityCodeShort')
                    ->addSelect('receivingHospitalInst.FacilityName as receivingHospitalDescription', 'receivingHospitalInst.FacilityName as receivingHospitalDescription','patientreferralhistory.*')
                    ->selectRaw("DATE_FORMAT(patientreferralhistory.created_at, '%b %d, %Y %h:%i %p') as formatted_created_at")
                    ->orderBy('patientreferralhistory.created_at', 'desc')
                    ->get();
                
                foreach ($referralHistories as $history) {
                    $history->encryptedReferralHistoryID = Crypt::encrypt($history->referralHistoryID);
                }
                
                $referral->EncryptedReferralID = Crypt::encrypt($referral->referralID);
                $referral->referralHistory = $referralHistories;
            }
                return response()->json(['referrals' => $patientReferrals], 200);
        
    }

    public function setToOngoing(Request $request){

        $updateHistory = referralHistory::where("referralHistoryID", $request-> referralHistoryID)
        ->update(['referralStatus'=> 2]);

        $user = Auth::user();
        $user_id = $user->id;
        $sent_to = $request->referringHospital;
        $notification = sprintf("Your referral for %s %s %s is now undergoing assessment.", $request->firstName, $request->middleName, $request->lastName);
        $dateTime = Carbon::now();
        $date = $dateTime->format('F j, Y'); 
        $time = $dateTime->format('g:i A');
            
        $encryptedReferralID = Crypt::encrypt($request->referralID);
        $encryptedReferralHistoryID = Crypt::encrypt($request->referralHistoryID);

        $notif = new Notifications();
        $notif->notification = $notification;
        $notif->notificationType = 6;
        $notif->referralID = $encryptedReferralID;
        $notif->referralHistoryID =  $encryptedReferralHistoryID;
        $notif->user_id = $user->id; 
        $notif->sent_to = $sent_to;
        $notif->sent_at = $dateTime;
        $notif->save();

        event(new NewNotification($notification, $user_id, 6, $encryptedReferralID, $request->referralID , $encryptedReferralHistoryID, $sent_to, $date, $time));

        return response()->json(["message" => "Success"], 200);
    }
    
    public function acceptPatient(Request $request){
        $updateMain = referrals::where("referralID", $request->referralID)
        ->update(['receivingDepartment' => $request->receivingDepartment,
        'assignedDoctor' => $request->assignedDoctor]);    

        $updateHistory = referralHistory::where("referralHistoryID", $request-> referralHistoryID)
        ->update(['referralStatus'=> 3, 'accepted'=> 1]);

        $user = Auth::user();
        $user_id = $user->id;
        $sent_to = $request->referringHospital;
        $notification = sprintf("Your Patient %s %s %s has been accepted. Do not forget to print the referral form. Thank you.", $request->firstName, $request->middleName, $request->lastName);
        $dateTime = Carbon::now();
        $date = $dateTime->format('F j, Y'); 
        $time = $dateTime->format('g:i A');
        
        $notif = new Notifications();
        $notif->notification = $notification;
        $notif->notificationType = 1;
        $notif->referralID = Crypt::encrypt($request->referralID);
        $notif->referralHistoryID = Crypt::encrypt($request->referralHistoryID);
        $notif->user_id = $user->id; 
        $notif->sent_to = $sent_to;
        $notif->sent_at = $dateTime;
        $notif->save();

        event(new NewNotification($notification, $user_id, 1, Crypt::encrypt($request->referralID), $request->referralID, Crypt::encrypt($request->referralHistoryID), $sent_to, $date, $time));

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

        $user = Auth::user();
        $user_id = $user->id;
        $sent_to = $request->receivingHospital;
        $notification = sprintf("Vital signs for %s %s %s has been updated", $request->firstName, $request->middleName, $request->lastName);
        $dateTime = Carbon::now();
        $date = $dateTime->format('F j, Y'); 
        $time = $dateTime->format('g:i A');
        
        $notif = new Notifications();
        $notif->notification = $notification;
        $notif->notificationType = 10;
        $notif->referralID = Crypt::encrypt($request->referralID);
        $notif->referralHistoryID = Crypt::encrypt($request->referralHistoryID);
        $notif->user_id = $user->id; 
        $notif->sent_to = $sent_to;
        $notif->sent_at = $dateTime;
        $notif->save();

        event(new NewNotification($notification, $user_id, 10, Crypt::encrypt($request->referralID), $request->referralID, Crypt::encrypt($request->referralHistoryID), $sent_to, $date, $time));

        return response()->json(["message" => "Success"], 200);
    }

    public function deferPatient(Request $request){  
        $updateHistory = referralHistory::where("referralHistoryID", $request-> referralHistoryID)
        ->update(['referralStatus'=> $request->deferReason]);

        $user = Auth::user();
        $user_id = $user->id;
        $sent_to = $request->referringHospital;

        if($request->deferReason == '4'){
        $notification = sprintf("Your Patient %s %s %s has been deferred (Given Management)", $request->firstName, $request->middleName, $request->lastName);
        }else if($request->deferReason == '5'){
        $notification = sprintf("Your Patient %s %s %s has been deferred (Patient Refused Transfer)", $request->firstName, $request->middleName, $request->lastName);
        }
        $dateTime = Carbon::now();
        $date = $dateTime->format('F j, Y'); 
        $time = $dateTime->format('g:i A');
        
        $notif = new Notifications();
        $notif->notification = $notification;
        $notif->notificationType = 5;
        $notif->referralID = Crypt::encrypt($request->referralID);
        $notif->referralHistoryID = Crypt::encrypt($request->referralHistoryID);
        $notif->user_id = $user->id; 
        $notif->sent_to = $sent_to;
        $notif->sent_at = $dateTime;
        $notif->save();
        event(new NewNotification($notification, $user_id, 5, Crypt::encrypt($request->referralID), $request->referralID, Crypt::encrypt($request->referralHistoryID), $sent_to, $date, $time));

        return response()->json(["message" => "Success"], 200);
    }

    public function cancelReferral(Request $request){  
        $updateHistory = referrals::where("referralID", $request-> referralID)
        ->update(['status'=> 0]);

        $user = Auth::user();
        $user_id = $user->id;
        $sent_to = $request->receivingHospital;
        $notification = sprintf("Referral for %s %s %s has been cancelled", $request->firstName, $request->middleName, $request->lastName);
        $dateTime = Carbon::now();
        $date = $dateTime->format('F j, Y'); 
        $time = $dateTime->format('g:i A');
        
        $notif = new Notifications();
        $notif->notification = $notification;
        $notif->notificationType = 8;
        $notif->referralID = Crypt::encrypt($request->referralID);
        $notif->referralHistoryID = Crypt::encrypt($request->referralHistoryID);
        $notif->user_id = $user->id; 
        $notif->sent_to = $sent_to;
        $notif->sent_at = $dateTime;
        $notif->save();

        event(new NewNotification($notification, $user_id, 8, Crypt::encrypt($request->referralID), $request->referralID, Crypt::encrypt($request->referralHistoryID), $sent_to, $date, $time));

        return response()->json(["message" => "Success"], 200);
    }

    public function expiredPatient(Request $request){  

        $encryptedReferralHistoryID = $request->input('encryptedReferralHistoryID');
        $referralHistoryID = Crypt::decrypt($encryptedReferralHistoryID);

        $updateHistory = referralHistory::where("referralHistoryID", $referralHistoryID)
        ->update(['referralStatus'=> 9]);

        $user = Auth::user();
        $user_id = $user->id;
        $sent_to = $request->receivingHospital;
        $notification = sprintf("Patient %s %s %s has expired", $request->firstName, $request->middleName, $request->lastName);
        $dateTime = Carbon::now();
        $date = $dateTime->format('F j, Y'); 
        $time = $dateTime->format('g:i A');
        
        $notif = new Notifications();
        $notif->notification = $notification;
        $notif->notificationType = 11;
        $notif->referralID = Crypt::encrypt($request->referralID);
        $notif->referralHistoryID = Crypt::encrypt($request->referralHistoryID);
        $notif->user_id = $user->id; 
        $notif->sent_to = $sent_to;
        $notif->sent_at = $dateTime;
        $notif->save();

        event(new NewNotification($notification, $user_id, 11, Crypt::encrypt($request->referralID), $request->referralID, Crypt::encrypt($request->referralHistoryID), $sent_to, $date, $time));

        return response()->json(["message" => "Success"], 200);
    }
   
    public function reopenReferral(Request $request){
        $updated = referralHistory::where("referralHistoryID", $request->referralHistoryID)
        ->update(['referralStatus' => '1']);    

        return response()->json(["message" => "Success"], 200);
    }
    
    public function createNewReferral(Request $request){

        $birthdate = date('Y-m-d', strtotime($request->birthDate));
        $referrerContact = (int)preg_replace('/[^0-9]/', '', $request->referrerContact);
        $patientContact = (int)preg_replace('/[^0-9]/', '', $request->patientContact);
        $informantContact = (int)preg_replace('/[^0-9]/', '', $request->informantContact);

        $referral = referrals::create([
            "lastName" => $request->lastName,
            "firstName" => $request->firstName,
            "middleName" => $request->middleName,
            "suffix" => $request->suffix,
            "birthDate" => $birthdate,
            "gender" => $request->gender,
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
            'nationality' => $request->nationality,
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
        $referralHistoryID = $referralHistory->id;

        $user = Auth::user();
        $user_id = $user->id;
        $sent_to = $request->receivingHospital;
        $notification = sprintf("You have a new referral: %s %s %s", $request->firstName, $request->middleName, $request->lastName);
        $dateTime = Carbon::now();
        $date = $dateTime->format('F j, Y'); 
        $time = $dateTime->format('g:i A');
        
        $notif = new Notifications();
        $notif->notification = $notification;
        $notif->notificationType = 4;
        $notif->referralID = Crypt::encrypt($request->referralID);
        $notif->referralHistoryID = Crypt::encrypt($referralHistoryID);
        $notif->user_id = $user->id; 
        $notif->sent_to = $sent_to;
        $notif->sent_at = $dateTime;
        $notif->save();

        event(new NewNotification($notification, $user_id, 4, Crypt::encrypt($referralID), $request->referralID, Crypt::encrypt($referralHistoryID), $sent_to, $date, $time));
        return response()->json(["message" => "Referral Created", "referralID" => $referralID], 200);
    }
    
    public function createReferralSafru(Request $request){

        $birthdate = date('Y-m-d', strtotime($request->birthDate));
        $referrerContact = (int)preg_replace('/[^0-9]/', '', $request->referrerContact);
        $patientContact = (int)preg_replace('/[^0-9]/', '', $request->patientContact);
        $informantContact = (int)preg_replace('/[^0-9]/', '', $request->informantContact);

        $referral = referrals::create([
            "lastName" => $request->lastName,
            "firstName" => $request->firstName,
            "middleName" => $request->middleName,
            "suffix" => $request->suffix,
            "gender" => $request->gender,
            'referringHospital' => $request->referringHospital,
            'street' => $request->street,
            'provinceID' => $request->provinceID,
            'municipalityID' => $request->municipalityID,
            'barangayID' => $request->barangayID,
            'impression' => $request->impression,
            'locationOfAccident' => $request->locationOfAccident,
            'typeOfInjury' => $request->typeOfInjury,
            'updatedBy' => $request->updatedBy,
            'safru' => 1,
            'status' => 1,
        ]);
        $referralHistory = referralHistory::create([
            'referralID' => $referral->id,
            'receivingHospital' => 271,
            'referralStatus' => 1,
        ]);
    
        $referralID = $referral->id;
        $referralHistoryID = $referralHistory->id;

        $user = Auth::user();
        $user_id = $user->id;
        $sent_to = $request->receivingHospital;
        $notification = sprintf("You have a new referral: %s %s %s from SAFRU", $request->firstName, $request->middleName, $request->lastName);
        $dateTime = Carbon::now();
        $date = $dateTime->format('F j, Y'); 
        $time = $dateTime->format('g:i A');
        
        $notif = new Notifications();
        $notif->notification = $notification;
        $notif->notificationType = 13;
        $notif->referralID = Crypt::encrypt($request->referralID);
        $notif->referralHistoryID = Crypt::encrypt($referralHistoryID);
        $notif->user_id = $user->id; 
        $notif->sent_to = $sent_to;
        $notif->sent_at = $dateTime;
        $notif->save();

        event(new NewNotification($notification, $user_id, 13, Crypt::encrypt($request->referralID), $request->referralID, Crypt::encrypt($request->referralHistoryID), $sent_to, $date, $time));
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
        $notif->referralID = Crypt::encrypt($request->referralID);
        $notif->referralHistoryID = Crypt::encrypt($request->referralHistoryID);
        $notif->user_id = $user->id; 
        $notif->sent_to = $sent_to;
        $notif->sent_at = $dateTime;
        $notif->save();

        event(new NewNotification($notification, $user_id, 3, Crypt::encrypt($request->referralID), $request->referralID, Crypt::encrypt($request->referralHistoryID), $sent_to, $date, $time));


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
        $notif->referralID = Crypt::encrypt($request->referralID);
        $notif->referralHistoryID = Crypt::encrypt($request->referralHistoryID);
        $notif->user_id = $user->id; 
        $notif->sent_to = $sent_to;
        $notif->sent_at = $dateTime;
        $notif->save();

        event(new NewNotification($notification, $user_id, 2, Crypt::encrypt($request->referralID), $request->referralID, Crypt::encrypt($request->referralHistoryID), $sent_to, $date, $time));
        
        return response()->json(["message" => "Referral Created", "referralID" => $referralID], 200);


    }

    public function returnToJBLMGH(Request $request){

        $referralID = $request->referralID;
        $referralHistoryID = $request->referralHistoryID;

        $update = referralHistory::where("referralHistoryID", $referralHistoryID)
        ->update(['referralStatus' => 6]);    

        $referralHistory = referralHistory::create([
            'referralID' => $referralID,
            'receivingHospital' => 271,
            'referralStatus' => 1,
        ]);
    
        $referralID = $referralHistory->id;
        
        $user = Auth::user();
        $user_id = $user->id;
        $sent_to = $request->referringHospital;
        $notification = sprintf("Your Patient Referral %s %s %s has been referred to JBLMGH", $request->firstName, $request->middleName, $request->lastName);
        $dateTime = Carbon::now();
        $date = $dateTime->format('F j, Y'); 
        $time = $dateTime->format('g:i A');
        
        $notif = new Notifications();
        $notif->notification = $notification;
        $notif->notificationType = 9;
        $notif->referralID = Crypt::encrypt($request->referralID);
        $notif->referralHistoryID = Crypt::encrypt($request->referralHistoryID);
        $notif->user_id = $user->id; 
        $notif->sent_to = $sent_to;
        $notif->sent_at = $dateTime;
        $notif->save();

        event(new NewNotification($notification, $user_id, 9, Crypt::encrypt($request->referralID), $request->referralID, Crypt::encrypt($request->referralHistoryID), $sent_to, $date, $time));
        
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
            'accepted' => 1,
            'arrived' => 1,
        ]);
    
        $referralHistory = $referralHistory->referralHistoryID;
        $newreferralID = $referralID;

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
        $notif->referralID = Crypt::encrypt($request->referralID);
        $notif->referralHistoryID = Crypt::encrypt($request->referralHistoryID);
        $notif->user_id = $user->id; 
        $notif->sent_to = $sent_to;
        $notif->sent_at = $dateTime;
        $notif->save();

        event(new NewNotification($notification, $user_id, 3, Crypt::encrypt($request->referralID), $request->referralID, Crypt::encrypt($request->referralHistoryID), $sent_to, $date, $time));

        $user = Auth::user();
        $user_id = $user->id;
        $sent_to = $request->newReceivingHospital;
        $notification = sprintf("You have a new referral for patient %s %s %s from OPCEN", $request->firstName, $request->middleName, $request->lastName);
        $dateTime = Carbon::now();
        $date = $dateTime->format('F j, Y'); 
        $time = $dateTime->format('g:i A');
        
        $notif = new Notifications();
        $notif->notification = $notification;
        $notif->notificationType = 12;
        $notif->referralID = Crypt::encrypt($request->referralID);
        $notif->referralHistoryID = Crypt::encrypt($request->referralHistoryID);
        $notif->user_id = $user->id; 
        $notif->sent_to = $sent_to;
        $notif->sent_at = $dateTime;
        $notif->save();

        event(new NewNotification($notification, $user_id, 12, Crypt::encrypt($newreferralID), $newreferralID, Crypt::encrypt($referralHistoryID), $sent_to, $date, $time));
        return response()->json(["message" => "Referral Created", "referralID" => $newreferralID], 200);
    }

    public function setToExpired(){
        $update = referralHistory::whereDate('created_at', '<=', now()->subDays(1))
        ->whereNull('arrived')
        ->whereIn('referralStatus', [1, 2, 3])
        ->update(['referralStatus' => '8']);
    }
   
    public function fetchCivilStatus(){
        $data = civilStatus::get();
        return $data;
    }

    public function fetchNationality(){
        $data = Nationality::where('Status', 1)->get();
        return $data;
    }    
    
    public function fetchDoctors(){
        $query = doctors::query();
        $doctors = $query->selectRaw("CONCAT(payroll.name, ' ', payroll.lname) AS fullName, payroll.id as doctorID")
        ->join('position', 'payroll.positionid', '=', 'position.positionid')
        ->join('department', 'payroll.department', '=', 'department.id')
        ->where('payroll.status', 'A')
        ->where(function ($query) {
            $query->whereBetween('payroll.positionid', [47, 57])
                ->orWhere('payroll.positionid', 34)
                ->orWhereBetween('payroll.positionid', [23, 25]);
        })
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
            ->where('status',1)
            ->get();
        } else if ($request->has('hciID')) {
            $hciID = $request->hciID;
            $data = referringHCI::where('HealthFacilityCodeShort', '!=', $hciID)
            ->orWhereNull('HealthFacilityCodeShort')
            ->where('status',1)
            ->get();
            } else {
                $data = referringHCI::where('status',1)->get();
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

    public function createHCI(Request $request){
        $referringHCI = referringHCI::create([
            'FacilityName' => $request->hciName,
            'HealthFacilityCode' => $request->hciDOHCode,
            'HealthFacilityCodeShort' => $request->hciDOHCodeShort,
            'status' => 1,
        ]);
    
        $referringHCIID = $referringHCI->id;
       
        return response()->json(["message" => "HCI Created", "HCI" => $referringHCIID], 200);
    }

    public function removeHCI(Request $request){  
        $removeHCI = referringHCI::where("ID", $request-> ID)
        ->update(['status'=> 0]);

        return response()->json(["message" => "Success"], 200);
    }
}
