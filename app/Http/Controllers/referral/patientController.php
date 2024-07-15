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
use App\Models\referral\patientHistory;
use App\Models\PatientInfo;
use App\Models\referral\Rooms;
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
                ->selectRaw("DATE_FORMAT(patientreferralhistory.arrivalDateTime, '%b %d, %Y %h:%i %p') as formatted_arrivalDateTime")
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
        $department = $request->input('department');
        $referralID = $request->input('referralID');
        $limit = $request->input('limit');
    
        $query = referralHistory::query()
            ->where('patientreferralhistory.receivingHospital', '=', $hciID)
            ->where('pr.isPosted', '=', 1)
            ->whereBetween('patientreferralhistory.created_at', [now()->subDays(1), now()])
            ->where('pr.status','1')
            ->leftJoin('patientreferrals as pr', 'patientreferralhistory.referralID', '=', 'pr.referralID')
            ->leftJoin('servicetypes as st', 'pr.receivingDepartment', '=', 'st.serviceTypeID')
            ->leftJoin('activefacilities as referringHospitalInst', 'pr.referringHospital', '=', 'referringHospitalInst.HealthFacilityCodeShort')
            ->leftJoin('activefacilities as receivingHospitalInst', 'patientreferralhistory.receivingHospital', '=', 'receivingHospitalInst.HealthFacilityCodeShort')
            ->selectRaw("CONCAT_WS(' ', pr.firstName, pr.middleName, pr.lastName, pr.suffix) as fullName")
            ->addSelect('patientreferralhistory.*','referringHospitalInst.FacilityName as referringHospitalDescription', 'receivingHospitalInst.FacilityName as receivingHospitalDescription', 'pr.*', 'st.serviceType as serviceType')          
            ->selectRaw("DATE_FORMAT(patientreferralhistory.created_at, '%b %d, %Y %h:%i %p') as formatted_created_at")
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
                if ($department) {
                    $query->where('pr.receivingDepartment', 'like', '%' . $department . '%');
                }
            }
        
            if ($limit) {
                $query->limit($limit);
            }
        
            $referrals = $query->get();
            foreach ($referrals as $referral) {
                $givenDateTime = $referral->birthDate;
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
                
                $referral->Age = trim($ageString);
                
                $referral->encryptedReferralID = Crypt::encrypt($referral->referralID);
                $referral->encryptedReferralHistoryID = Crypt::encrypt($referral->referralHistoryID);
            }
            return response()->json(['referrals' => $referrals], 200);
    
    } 

    public function fetchInboundPatientsOB(Request $request){

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
            ->where('pr.receivingDepartment', 'like', '%2%')
            ->where('pr.status','1')
            ->leftJoin('patientreferrals as pr', 'patientreferralhistory.referralID', '=', 'pr.referralID')
            ->leftJoin('servicetypes as st', 'pr.receivingDepartment', '=', 'st.serviceTypeID')
            ->leftJoin('activefacilities as referringHospitalInst', 'pr.referringHospital', '=', 'referringHospitalInst.HealthFacilityCodeShort')
            ->leftJoin('activefacilities as receivingHospitalInst', 'patientreferralhistory.receivingHospital', '=', 'receivingHospitalInst.HealthFacilityCodeShort')
            ->selectRaw("CONCAT_WS(' ', pr.firstName, pr.middleName, pr.lastName, pr.suffix) as fullName")
            ->addSelect('patientreferralhistory.*','referringHospitalInst.FacilityName as referringHospitalDescription', 'receivingHospitalInst.FacilityName as receivingHospitalDescription', 'pr.*', 'st.serviceType as serviceType')          
            ->selectRaw("DATE_FORMAT(patientreferralhistory.created_at, '%b %d, %Y %h:%i %p') as formatted_created_at")
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
                
                $referral->Age = trim($ageString);
                
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
        ->where('patientreferrals.isPosted','1')
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
                
                $referral->Age = trim($ageString);
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
    
    public function fetchUnpostedReferrals(Request $request){

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
        ->where('patientreferrals.isPosted','0')
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
                
                $referral->Age = trim($ageString);
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
    
        $lastName = $request->input('lastName');
        $firstName = $request->input('firstName');
        $middleName = $request->input('middleName');
        $referralID = $request->input('referralID');
    
        $patientReferrals = $this->getPatientReferrals($hciID, $lastName, $firstName, $middleName, $referralID);
    
        foreach ($patientReferrals as $referral) {
            $referralHistories = $this->getReferralHistories($referral->referralID);
    
            foreach ($referralHistories as $history) {
                $history->encryptedReferralHistoryID = Crypt::encrypt($history->referralHistoryID);
            }
    
            $referral->EncryptedReferralID = Crypt::encrypt($referral->referralID);
            $referral->referralHistory = $referralHistories;
    
            $referral->Age = $this->calculateAgeString($referral->birthDate);
        }
    
        return response()->json(['referrals' => $patientReferrals], 200);
    }
    
    private function getPatientReferrals($hciID, $lastName, $firstName, $middleName, $referralID){
        $query = Referrals::leftJoin('activefacilities as referringHospitalInst', 'patientreferrals.referringHospital', '=', 'referringHospitalInst.HealthFacilityCodeShort')
            ->addSelect('referringHospitalInst.FacilityName as referringHospitalDescription', 'patientreferrals.*')
            ->selectRaw("DATE_FORMAT(patientreferrals.created_at, '%b %d, %Y %h:%i %p') as formatted_created_at")
            ->selectRaw("CONCAT_WS(' ', patientreferrals.firstName, patientreferrals.middleName, patientreferrals.lastName, patientreferrals.suffix) as fullName")
            ->where('patientreferrals.status', '1')
            ->where('patientreferrals.isPosted', '1')
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
            });
    
        if (!is_null($hciID) && $hciID != '0') {
            $query->leftJoin('patientreferralhistory as prh', 'patientreferrals.referralID', '=', 'prh.referralID')
                  ->where('prh.receivingHospital', $hciID);
        }
    
        return $query->orderBy('patientreferrals.created_at', 'desc')->get();
    }
    
    private function getReferralHistories($referralID){
        return referralHistory::where('referralID', $referralID)
            ->join('activefacilities as receivingHospitalInst', 'patientreferralhistory.receivingHospital', '=', 'receivingHospitalInst.HealthFacilityCodeShort')
            ->addSelect('receivingHospitalInst.FacilityName as receivingHospitalDescription', 'patientreferralhistory.*')
            ->selectRaw("DATE_FORMAT(patientreferralhistory.created_at, '%b %d, %Y %h:%i %p') as formatted_created_at")
            ->orderBy('patientreferralhistory.created_at', 'desc')
            ->get();
    }

    private function calculateAgeString($birthDate){
        $diff = $this->getDateDifference($birthDate);
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
    
        return trim($ageString);
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

        event(new NewNotification($notification, $user_id, 6, $encryptedReferralID, $request->referralID , $encryptedReferralHistoryID, $sent_to, $date, $time, ''));

        return response()->json(["message" => "Success"], 200);
    }
    
    public function acceptPatient(Request $request){
        $updateMain = referrals::where("referralID", $request->referralID)
        ->update(['receivingDepartment' => $request->receivingDepartment,
        'assignedDoctor' => $request->assignedDoctor,'secondaryReceivingDepartment' => $request->secondaryReceivingDepartment,
        'secondaryAssignedDoctor' => $request->secondaryAssignedDoctor]);    

        $updateHistory = referralHistory::where("referralHistoryID", $request-> referralHistoryID)
        ->update(['referralStatus'=> 3, 'accepted'=> 1]);

        $updateReferral = Referrals::where("referralID", $request->referralID)
        ->update(['emInCharge'=> $request->emInCharge]);

        $receivingDepartment = $request->receivingDepartment;
        $receivingDoctor = $request->assignedDoctor;
        $emResident = $request->emInCharge;
        $referrerContactNo = $request->referrerContact;
        $fullName = $request->lastName . ', ' . $request->firstName . ' ' . $request->middleName;
        $receivingDepartmentName = $this->getServiceTypeName($receivingDepartment);
        $receivingDoctorName = $this->fetchDoctorName($receivingDoctor);
        $assignedEMDoc = $this->fetchDoctorName($emResident);

        $this->sendSMSAccept($referrerContactNo, $fullName, $receivingDepartmentName, $receivingDoctorName, $assignedEMDoc);

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
        event(new NewNotification($notification, $user_id, 1, Crypt::encrypt($request->referralID), $request->referralID, Crypt::encrypt($request->referralHistoryID), $sent_to, $date, $time, ''));
        return response()->json(["message" => "Success"], 200);
    }

    private function getServiceTypeName($receivingDepartment){
        $data = servicetypes::select('Description')
        ->where('ForERPatient', 1)
        ->where('ServiceTypeID', $receivingDepartment)
        ->first();
        return $data->Description;
    }

    private function fetchDoctorName($receivingDoctor){
        $query = doctors::query();
        $doctors = $query->select('payroll.lname')
        ->join('position', 'payroll.positionid', '=', 'position.positionid')
        ->join('department', 'payroll.department', '=', 'department.id')
        ->where('payroll.status', 'A')
        ->where('payroll.id', $receivingDoctor)
        ->where(function ($query) {
            $query
                ->orWhere('payroll.positionid', 47);
        })
        ->first();
        return $doctors->lname;
    }

    private function sendSMSAccept($referringContactNo, $fullName, $receivingDepartment, $receivingDoctor, $assignedEMDoc){
        $endpoint = 'https://messagingsuite.smart.com.ph/cgphttp/servlet/sendmsg';
        $username = 'kvzcatz@gmail.com';
        $password = 'JBLmgh2020!';
        $message = 'Thank you for using OURS!' . "\n" . 
        'You may transfer patient ' . $fullName . ' decked under the department of ' . $receivingDepartment . "\n" . 
        'to be received by Dr. ' . $receivingDoctor. "\n" . 'Remarks: EM Resident facilitating coordination - Dr. ' . $assignedEMDoc;

        $contact = '9458874836';
        
        $queryParams = http_build_query([
            'username' => $username,
            'password' => $password,
            'destination' => $contact,
            'text' => $message
        ]);
        $url = $endpoint . '?' . $queryParams;

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);
        curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/4.0 (compatible; MSIE 6.0; Windows NT 5.1)");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true); 

        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
        ]); 

        $result = curl_exec($ch); 

        if(curl_errno($ch)){
            echo 'Curl error: ' . curl_error($ch);
        } 
        curl_close($ch);    
        echo $message;
        echo $contact;
    }

    public function setDepartment(Request $request){
        $updateMain = referrals::where("referralID", $request->referralID)
        ->update(['receivingDepartment' => $request->receivingDepartment]);    

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

        event(new NewNotification($notification, $user_id, 10, Crypt::encrypt($request->referralID), $request->referralID, Crypt::encrypt($request->referralHistoryID), $sent_to, $date, $time, ''));

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
        event(new NewNotification($notification, $user_id, 5, Crypt::encrypt($request->referralID), $request->referralID, Crypt::encrypt($request->referralHistoryID), $sent_to, $date, $time, ''));

        return response()->json(["message" => "Success"], 200);
    }

    public function setToIntransit(Request $request){  
        $setToInTransit = referrals::where("referralID", $request->referral['referralID'])
        ->update(['inTransit'=> 1, 'inTransitDateTime'=> NOW(), 'vehicleNumber'=>$request->vehicleNumber,'vehicleType'=>$request->vehicleType, 'eta'=>$request->eta]); //

        $user = Auth::user();
        $user_id = $user->id;
        $sent_to = 271;
        
    
        $notification = sprintf("Patient %s %s %s is now ON THE WAY to your facility.", $request->referral['firstName'], $request->referral['middleName'], $request->referral['lastName']);
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
        event(new NewNotification($notification, $user_id, 5, Crypt::encrypt($request->referralID), $request->referralID, Crypt::encrypt($request->referralHistoryID), $sent_to, $date, $time, ''));

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

        event(new NewNotification($notification, $user_id, 8, Crypt::encrypt($request->referralID), $request->referralID, Crypt::encrypt($request->referralHistoryID), $sent_to, $date, $time, ''));

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

        event(new NewNotification($notification, $user_id, 11, Crypt::encrypt($request->referralID), $request->referralID, Crypt::encrypt($request->referralHistoryID), $sent_to, $date, $time,''));

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
            'isSignore' => $request->isSignore,
            "lastName" => strtoupper($request->lastName),
            "firstName" => strtoupper($request->firstName),
            "middleName" => strtoupper($request->middleName),
            "suffix" => strtoupper($request->suffix),
            "birthDate" => $birthdate,
            "gender" => $request->gender,
            "isSignore" => $request->isSignore,
            "isCritical" => $request->isCritical,
            'referringHospital' => $request->referringHospital,
            'referringDoctor' => $request->referringDoctor,
            'referrerContact' => $referrerContact,
            'transferReason' => $request->transferReason,
            'otherTransferReason' => $request->otherTransferReason,
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
            'isRabies' => $request->isRabies,
            'isPosted' => $request->isPosted,
            'status' => 1,
        ]);
        $referralHistory = referralHistory::create([
            'referralID' => $referral->id,
            'receivingHospital' => $request->receivingHospital,
            'referralStatus' => 1,
        ]);
    
        $referralID = $referral->id;
        $referralHistoryID = $referralHistory->id;
        $referringHospital = $request->referringHospital;
        $fullName = $referral->lastName . ', ' . $referral->firstName . ' ' .  $referral->middleName;
        if($request->isPosted == '1'){

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

            event(new NewNotification($notification, $user_id, 4, Crypt::encrypt($referralID), $request->referralID, Crypt::encrypt($referralHistoryID), $sent_to, $date, $time, ''));
        }
        if($request->isPosted == '1'){
           $referralHospitalName = $this->getReferringHospitalName($referringHospital);
            // $this->sendSMS($fullName, $referralHospitalName);
        }
        return response()->json(["message" => "Referral Created", "referralID" => $referralID, "encryptedReferralID" => Crypt::encrypt($referralID), "encryptedReferralHistoryID" => Crypt::encrypt($referralHistoryID)], 200);

    }

    private function getReferringHospitalName($referringHospital){
            $data = referringHCI::select('FacilityName')
            ->where('HealthFacilityCodeShort', $referringHospital)
            ->where('status',1)
            ->first();
        return $data->FacilityName;
    }

    private function sendSMS($fullName, $referralHospitalName){
        $endpoint = 'https://messagingsuite.smart.com.ph/cgphttp/servlet/sendmsg';
        $username = 'kvzcatz@gmail.com';
        $password = 'JBLmgh2020!';
        $message = 'New referral from ' . $referralHospitalName . ': ' . $fullName;
        $contact = '9458874836';
        
        $queryParams = http_build_query([
            'username' => $username,
            'password' => $password,
            'destination' => $contact,
            'text' => $message
        ]);
        $url = $endpoint . '?' . $queryParams;
    
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);
        curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/4.0 (compatible; MSIE 6.0; Windows NT 5.1)");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true); 

        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
        ]); 

        $result = curl_exec($ch); 

        if(curl_errno($ch)){
            echo 'Curl error: ' . curl_error($ch);
        } 
        curl_close($ch);    
        echo $message;
        echo $contact;
    }

    public function updateReferral(Request $request){
        $birthdate = date('Y-m-d', strtotime($request->birthDate));
        $referrerContact = (int)preg_replace('/[^0-9]/', '', $request->referrerContact);
        $patientContact = (int)preg_replace('/[^0-9]/', '', $request->patientContact);
        $informantContact = (int)preg_replace('/[^0-9]/', '', $request->informantContact);

        $updateReferral = referrals::where("referralID", $request->referralID)
        ->update(['isSignore' => $request->isSignore,
            "lastName" => strtoupper($request->lastName),
            "firstName" => strtoupper($request->firstName),
            "middleName" => strtoupper($request->middleName),
            "suffix" => strtoupper($request->suffix),
            "birthDate" => $birthdate,
            "gender" => $request->gender,
            "isSignore" => $request->isSignore,
            "isCritical" => $request->isCritical,
            'referringHospital' => $request->referringHospital,
            'referringDoctor' => $request->referringDoctor,
            'referrerContact' => $referrerContact,
            'transferReason' => $request->transferReason,
            'otherTransferReason' => $request->otherTransferReason,
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
            'isRabies' => $request->isRabies,   
            'isPosted' => $request->isPosted]);    

            $referralID = $request->id;
            $referralHistoryID = $request->id;
            $referringHospital = $request->referringHospital;
            $fullName = $request->lastName . ', ' . $request->firstName . ' ' .  $request->middleName;
            
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
            $notif->referralHistoryID = Crypt::encrypt($request->referralHistoryID);
            $notif->user_id = $user->id; 
            $notif->sent_to = $sent_to;
            $notif->sent_at = $dateTime;
            $notif->save();

        event(new NewNotification($notification, $user_id, 4, Crypt::encrypt($request->referralID), $request->referralID, Crypt::encrypt($request->referralHistoryID), $sent_to, $date, $time, ''));
       
        if($request->isPosted == '1'){
            $referralHospitalName = $this->getReferringHospitalName($referringHospital);
            //  $this->sendSMS($fullName, $referralHospitalName);
         }
        return response()->json(["message" => "Success"], 200);
    }

    public function createReferralSafru(Request $request){

        $birthdate = date('Y-m-d', strtotime($request->birthDate));
        $referrerContact = (int)preg_replace('/[^0-9]/', '', $request->referrerContact);
        $patientContact = (int)preg_replace('/[^0-9]/', '', $request->patientContact);
        $informantContact = (int)preg_replace('/[^0-9]/', '', $request->informantContact);

        $referral = referrals::create([
            'isSignore' => $request->isSignore,
            "lastName" => strtoupper($request->lastName),
            "firstName" => strtoupper($request->firstName),
            "middleName" => strtoupper($request->middleName),
            "suffix" => strtoupper($request->suffix),
            "gender" => $request->gender,
            'referringHospital' => $request->referringHospital,
            'street' => $request->street,
            'provinceID' => $request->provinceID,
            'municipalityID' => $request->municipalityID,
            'barangayID' => $request->barangayID,
            'impression' => $request->impression,
            'locationOfAccident' => $request->locationOfAccident,
            'typeOfInjury' => $request->typeOfInjury,
            'isCritical' => $request->isCritical,
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
        $notif->safru = 1;
        $notif->save();

        event(new NewNotification($notification, $user_id, 13, Crypt::encrypt($referralID), $referralID, Crypt::encrypt($referralHistoryID), $sent_to, $date, $time, 1));
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

        event(new NewNotification($notification, $user_id, 3, Crypt::encrypt($request->referralID), $request->referralID, Crypt::encrypt($request->referralHistoryID), $sent_to, $date, $time, ''));


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

        event(new NewNotification($notification, $user_id, 2, Crypt::encrypt($request->referralID), $request->referralID, Crypt::encrypt($request->referralHistoryID), $sent_to, $date, $time, ''));
        
        $user = Auth::user();
        $user_id = $user->id;
        $sent_to = 100000;
        $notification = sprintf("You have a new referral for patient %s %s %s", $request->firstName, $request->middleName, $request->lastName);
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

        event(new NewNotification($notification, $user_id, 12, Crypt::encrypt($referralID), $referralID, Crypt::encrypt($referralHistoryID), $sent_to, $date, $time, ''));

        return response()->json(["message" => "Referral Created", "referralID" => $referralID], 200);
    }

    public function transferToJBLMGHOPCEN(Request $request){

        $referralID = $request->referralID;
        $referralHistoryID = $request->referralHistoryID;

        $update = referralHistory::where("referralHistoryID", $referralHistoryID)
        ->update(['referralStatus' => 6]);    

        $referralHistory = referralHistory::create([
            'referralID' => $referralID,
            'receivingHospital' => 100002,
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

        event(new NewNotification($notification, $user_id, 2, Crypt::encrypt($request->referralID), $request->referralID, Crypt::encrypt($request->referralHistoryID), $sent_to, $date, $time, ''));
        
        $user = Auth::user();
        $user_id = $user->id;
        $sent_to = 100002;
        $notification = sprintf("You have a new referral for patient %s %s %s", $request->firstName, $request->middleName, $request->lastName);
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

        event(new NewNotification($notification, $user_id, 12, Crypt::encrypt($referralID), $referralID, Crypt::encrypt($referralHistoryID), $sent_to, $date, $time, ''));

        return response()->json(["message" => "Referral Created", "referralID" => $referralID], 200);
    }

    public function returnToJBLMGH(Request $request){

        $referralID = $request->referralID;
        $referralHistoryID = $request->referralHistoryID;

        $update = referralHistory::where("referralHistoryID", $referralHistoryID)
        ->update(['referralStatus' => 10]);    

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

        event(new NewNotification($notification, $user_id, 9, Crypt::encrypt($request->referralID), $request->referralID, Crypt::encrypt($request->referralHistoryID), $sent_to, $date, $time, ''));
       
        $user = Auth::user();
        $user_id = $user->id;
        $sent_to = 271;
        $notification = sprintf("Referral for patient %s %s %s has been returned to JBLMGH", $request->firstName, $request->middleName, $request->lastName);
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

        event(new NewNotification($notification, $user_id, 9, Crypt::encrypt($referralID), $referralID, Crypt::encrypt($referralHistoryID), $sent_to, $date, $time, ''));
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

        event(new NewNotification($notification, $user_id, 3, Crypt::encrypt($request->referralID), $request->referralID, Crypt::encrypt($request->referralHistoryID), $sent_to, $date, $time, ''));

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

        event(new NewNotification($notification, $user_id, 12, Crypt::encrypt($newreferralID), $newreferralID, Crypt::encrypt($referralHistoryID), $sent_to, $date, $time, ''));
        return response()->json(["message" => "Referral Created", "referralID" => $newreferralID], 200);
    }

    public function setToExpired(){
        $update = referralHistory::leftJoin('patientreferrals as pr', 'patientreferralhistory.referralID', '=', 'pr.referralID')
            ->where('patientreferralhistory.created_at', '<=', Carbon::now()->subHours(24))
            ->whereNull('arrived')
            ->whereIn('referralStatus', [1, 2, 3])
            ->where('pr.isPosted', '1')
            ->update(['referralStatus' => '8']);
    }
   
    public function fetchCivilStatus(){
        $data = civilStatus::where('Status', 1)->get();
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
            $query
                ->orWhere('payroll.positionid', 47);
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
        ->where("status",1)
        ->get();
        return $data;
    }

    public function fetchBarangay(Request $request){
        $data = barangay::where("MunicipalityID", $request->MunicipalityID)
        ->where("status",1)
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
        $data = servicetypes::where('ForERPatient', 1)
        ->get();
        return $data;
    }
   
    public function countProcessing(){
        $processingCount = referralHistory::query()
            ->where('receivingHospital', '=', 271)
            ->whereIn('referralStatus', [1, 2])
            ->count();
        
            return response()->json([
                'processingCount' => $processingCount,
            ], 200);
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
        ->whereDate('created_at', Carbon::today())
        ->count();
    
        // Outbound Patients Count
        $outboundCount = Referrals::query()
            ->where('referringHospital', '=', $hciID)
            ->where('status', '<>', 0)
            ->whereDate('created_at', Carbon::today())
            ->count();
        
        // Accepted Patients Count
        $acceptedCount = referralHistory::query()
            ->where('receivingHospital', '=', $hciID)
            ->where('referralStatus', '=', 2)
            ->whereDate('created_at', Carbon::today())
            ->count();
        
        // Deferred Patients Count
        $deferredCount = referralHistory::query()
            ->where('receivingHospital', '=', $hciID)
            ->whereIn('referralStatus', [4, 5])
            ->whereDate('created_at', Carbon::today())
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

    public function getDashboardStats(Request $request){
        try{
            $data = ServiceTypes::selectRaw("ServiceTypeID,Description")
                ->whereIn('ServiceTypeID', [1,2,4,9,10,13,28,41,45,22])
                ->orderBy('Description', 'ASC')
                ->get();
        
                $sum_less = 0;
                $sum_more = 0;
                $sum_ac = 0;
                $sum_new = 0;
                $sum_fiveHrs = 0;
                $sum_tenHrs = 0;
                $sum_twentyHrs = 0;
                $sum_acu = 0;
                $sum_total = 0;
                $sum_totalAdmitted = 0;
                $sum_grandtotal = 0;

                $data->each(function ($serviceType) use (
                    &$sum_less, &$sum_more, &$sum_ac, &$sum_new, &$sum_fiveHrs, 
                    &$sum_tenHrs, &$sum_twentyHrs, &$sum_acu, &$sum_total, 
                    &$sum_totalAdmitted, &$sum_grandtotal
                ) {
                    $departmentTotal = $this->getDepartmentTotal($serviceType->ServiceTypeID);
                    $totalAdmitted = $this->getTotalAdmitted($serviceType->ServiceTypeID);
                    $consultDuration = $this->getConsultDuration($serviceType->ServiceTypeID);
                    $totalER = $this->getTotalER($serviceType->ServiceTypeID);
                
                    $serviceType->total = optional($departmentTotal)->total ?? 0;
                    $serviceType->totalAdmitted = optional($totalAdmitted)->total ?? 0;
                
                    $serviceType->less = $consultDuration['less'] ?? 0;
                    $serviceType->more = $consultDuration['more'] ?? 0;
                    $serviceType->ac = $consultDuration['ambuCare'] ?? 0;
                    $serviceType->acu = $totalER['ambuCareUnit'] ?? 0;
                    $serviceType->new = $totalER['new'] ?? 0;
                    $serviceType->fiveHrs = $totalER['fiveHrs'] ?? 0;
                    $serviceType->tenHrs = $totalER['tenHrs'] ?? 0;
                    $serviceType->twentyHrs = $totalER['twentyHrs'] ?? 0;
                
                    $serviceType->depTotal = $serviceType->total + $serviceType->totalAdmitted;
                
                    $sum_less += $serviceType->less;
                    $sum_more += $serviceType->more;
                    $sum_ac += $serviceType->ac;
                    $sum_new += $serviceType->new;
                    $sum_fiveHrs += $serviceType->fiveHrs;
                    $sum_tenHrs += $serviceType->tenHrs;
                    $sum_twentyHrs += $serviceType->twentyHrs;
                    $sum_acu += $serviceType->acu;
                    $sum_total += $serviceType->total;
                    $sum_totalAdmitted += $serviceType->totalAdmitted;
                    $sum_grandtotal += ($serviceType->total + $serviceType->totalAdmitted);
                });
                
                $totalData = [
                    'data' => $data->map(function($d){
                        return [
                            'id' => $d->ServiceTypeID,
                            'department' => $d->Description,
                            'totalpatient' => $d->depTotal,
                            'consultation' => [
                                'lessthan4hrs' => $d->less,
                                'morethan4hrs' => $d->more,
                                'ambucare' => $d->ac,
                                'total' => $d->total
                            ],
                            'admission' => [
                                'newlyadmitted' => $d->new,
                                'five_hrs' => $d->fiveHrs,
                                'ten_hrs' => $d->tenHrs,
                                'twenty_hrs' => $d->twentyHrs,
                                'ambucareunit' => $d->acu,
                                'total' => $d->totalAdmitted
                            ]
                        ];
                    })->toArray(),
                    'sum_lessthan4hrs' => $sum_less,
                    'sum_morethan4hrs' => $sum_more,
                    'sum_ambucare' => $sum_ac,
                    'sum_newlyadmitted' => $sum_new,
                    'sum_5hrs' => $sum_fiveHrs,
                    'sum_10hrs' => $sum_tenHrs,
                    'sum_20hrs' => $sum_twentyHrs,
                    'sum_ambucareunit' => $sum_acu,
                    'sum_totalconsultation' => $sum_total,
                    'sum_totaladmission' => $sum_totalAdmitted,
                    'sum_grandtotal' => $sum_grandtotal
                ];
                
                // Adding the summary row
                $totalData['data'][] = [
                    'id' => null,
                    'department' => 'Total',
                    'totalpatient' => $totalData['sum_grandtotal'],
                    'consultation' => [
                        'lessthan4hrs' => $totalData['sum_lessthan4hrs'],
                        'morethan4hrs' => $totalData['sum_morethan4hrs'],
                        'ambucare' => $totalData['sum_ambucare'],
                        'total' => $totalData['sum_totalconsultation']
                    ],
                    'admission' => [
                        'newlyadmitted' => $totalData['sum_newlyadmitted'],
                        'five_hrs' => $totalData['sum_5hrs'],
                        'ten_hrs' => $totalData['sum_10hrs'],
                        'twenty_hrs' => $totalData['sum_20hrs'],
                        'ambucareunit' => $totalData['sum_ambucareunit'],
                        'total' => $totalData['sum_totaladmission']
                    ]
                ];
                

            if(!$data){
                return response()->json(['message' => 'No result found', 'status' => 404]);
            }
            return response()->json($totalData);
        } catch (\Exception $e) {
            return response()->json(['message' => 'An error occurred', 'error' => $e->getMessage(), 'status' => 500]);
        }
    }

    private function getDepartmentTotal($ServiceTypeID){
        try{
            $data = patientHistory::join('Patients AS PAT', 'PatientHistory.PatientID', '=', 'PAT.PatientID')
                    ->join('Persons AS PER', 'PAT.PersonID', '=', 'PER.PersonID')
                    ->leftJoin('Admissions AS AD', 'PatientHistory.AdmissionID', '=', 'AD.AdmissionID')
                    ->leftJoin('AdmissionsOPD AS ADOPD', 'PatientHistory.AdmissionOPDID', '=', 'ADOPD.AdmissionID')
                    ->leftJoin('ServiceTypes AS SAD', 'AD.ServiceTypeID', '=', 'SAD.ServiceTypeID')
                    ->leftJoin('ServiceTypes AS SADOPD', 'ADOPD.ServiceTypeID', '=', 'SADOPD.ServiceTypeID')
                    ->join('PatientInfo AS pain', 'PatientHistory.PatientHistoryID', '=', 'pain.PatientHistoryID')
                    ->join('Users AS u', 'PatientHistory.UserID', '=', 'u.UserID')
                    ->join('Persons AS uper', 'u.PersonID', '=', 'uper.PersonID')
                    ->selectRaw("
                        count(PatientHistory.PatientHistoryID) as total
                    ")
                    ->where('PatientHistory.PatientTypeID', 4)
                    ->whereNotIn('PatientHistory.PatientStatusID', [6, 7, 8, 9, 10, 11, 13, 12, 18])
                    ->whereRaw('CAST(PatientHistory.TransactionDateTime AS DATE) between dateadd(DD, -20,CAST(GETDATE() AS DATE)) and CAST(GETDATE() AS DATE)')
                    ->where(function($query){
                        $query->whereNotNull('PatientHistory.AdmissionID')
                            ->orWhereNotNull('PatientHistory.AdmissionOPDID');
                    })
                    ->where(function($query){
                        $query->whereNull('PatientHistory.SetToInPatient')
                            ->orWhere('PatientHistory.SetToInPatient', 0);
                    })
                    ->where('PatientHistory.Status', 1)
                    ->where('SADOPD.ServiceTypeID', $ServiceTypeID)
                    ->groupByRaw('CASE PatientHistory.PatientTypeID WHEN 1 THEN SAD.[Description] ELSE SADOPD.[Description] END')
                    ->first();
                
            if(!$data){
                return false;
            }
            
            return $data;
        } catch (\Exception $e) {
            return response()->json(['message' => 'An error occurred', 'error' => $e->getMessage(), 'status' => 500]);
        } 
    }

    private function getConsultDuration($ServiceTypeID){
        try{
            $data = PatientHistory::join('Patients AS PAT', 'PatientHistory.PatientID', '=', 'PAT.PatientID')
            ->join('Persons AS PER', 'PAT.PersonID', '=', 'PER.PersonID')
            ->leftJoin('Admissions AS AD', 'PatientHistory.AdmissionID', '=', 'AD.AdmissionID')
            ->leftJoin('AdmissionsOPD AS ADOPD', 'PatientHistory.AdmissionOPDID', '=', 'ADOPD.AdmissionID')
            ->leftJoin('ServiceTypes AS SAD', 'AD.ServiceTypeID', '=', 'SAD.ServiceTypeID')
            ->leftJoin('ServiceTypes AS SADOPD', 'ADOPD.ServiceTypeID', '=', 'SADOPD.ServiceTypeID')
            ->join('PatientInfo AS pain', 'PatientHistory.PatientHistoryID', '=', 'pain.PatientHistoryID')
            ->join('Users AS u', 'PatientHistory.UserID', '=', 'u.UserID')
            ->join('Persons AS uper', 'u.PersonID', '=', 'uper.PersonID')
            ->selectRaw("
                DATEDIFF(minute, PatientHistory.TransactionDateTime, GETDATE()) AS HoursNumber,
                CASE PatientHistory.PatientTypeID WHEN 1 THEN SAD.[Description] ELSE SADOPD.[Description] END AS Department,
                PatientHistory.PatientHistoryID
            ")
            ->where('PatientHistory.PatientTypeID', 4)
            ->whereNotIn('PatientHistory.PatientStatusID', [6, 7, 8, 9, 10, 11, 13, 12, 18])
            ->whereRaw('CAST(PatientHistory.TransactionDateTime AS DATE) between dateadd(DD, -20,CAST(GETDATE() AS DATE)) and CAST(GETDATE() AS DATE)')
            ->where(function($query){
                $query->whereNotNull('PatientHistory.AdmissionID')
                    ->orWhereNotNull('PatientHistory.AdmissionOPDID');
            })
            ->where(function($query){
                $query->whereNull('PatientHistory.SetToInPatient')
                    ->orWhere('PatientHistory.SetToInPatient', 0);
            })
            ->where('PatientHistory.Status', 1)
            ->where('SADOPD.ServiceTypeID', $ServiceTypeID)
            ->get();
                
            if(!$data){
                return false;
            }

            $less4hrs = 0;
            $more4hrs = 0;
            $ambuCareCount = 0;
            
            $data->each(function($d) use (&$less4hrs, &$more4hrs, &$ambuCareCount){
                $ambuCare = PatientInfo::selectRaw("COUNT(PatientHistoryID) AS count")
                ->where('PatientHistoryID', $d->PatientHistoryID)
                ->where('ward', 5)
                ->first();

                if($ambuCare->count > 0){
                    $ambuCareCount++;
                }
                if($d->HoursNumber > 240){
                    $more4hrs++;
                } else{
                    $less4hrs++;
                }
            });

            

            return ["less" => $less4hrs, "more" => $more4hrs, 'ambuCare' => $ambuCareCount];
        } catch (\Exception $e) {
            return response()->json(['message' => 'An error occurred', 'error' => $e->getMessage(), 'status' => 500]);
        } 
    }
    
    private function getTotalAdmitted($ServiceTypeID){
        try{
            $ServiceTypeID = $ServiceTypeID == 45 ? 6 : $ServiceTypeID;

            $data = PatientHistory::join('Patients AS PAT', 'PatientHistory.PatientID', '=', 'PAT.PatientID')
            ->join('Persons AS PER', 'PAT.PersonID', '=', 'PER.PersonID')
            ->leftJoin('Admissions AS AD', 'PatientHistory.AdmissionID', '=', 'AD.AdmissionID')
            ->leftJoin('ServiceTypes AS SAD', 'AD.ServiceTypeID', '=', 'SAD.ServiceTypeID')
            ->join('PatientInfo AS pain', 'PatientHistory.PatientHistoryID', '=', 'pain.PatientHistoryID')
            ->join('Users AS u', 'PatientHistory.UserID', '=', 'u.UserID')
            ->join('Persons AS uper', 'u.PersonID', '=', 'uper.PersonID')
            ->leftJoin('PatientHistory AS pher', 'PatientHistory.PatientHistoryID', '=', 'pher.outpatienthistoryid')
            ->leftJoin('AdmissionsOPD AS ADOPD', 'pher.AdmissionOPDID', '=', 'ADOPD.AdmissionID')
            ->selectRaw("
                COUNT(PatientHistory.PatientHistoryID) as total
            ")
            ->where('PatientHistory.PatientTypeID', 1)
            ->where('pher.PatientTypeID', 4)
            ->whereNotIn('PatientHistory.PatientStatusID', [6, 7, 8, 9, 10, 11, 13, 12])
            ->whereRaw('CAST(PatientHistory.TransactionDateTime AS DATE) between dateadd(DD, -10,CAST(GETDATE() AS DATE)) and CAST(GETDATE() AS DATE)')
            ->where(function($query){
                $query->whereNotNull('PatientHistory.AdmissionID')
                    ->orWhereNotNull('PatientHistory.AdmissionOPDID');
            })
            ->where(function($query){
                $query->whereNotIn('SAD.ServiceTypeID', [3,74,75,76])
                    ->orWhereNull('SAD.ServiceTypeID');
            })
            ->where(function($query){
                $query->whereNull('PatientHistory.SetToInPatient')
                    ->orWhere('PatientHistory.SetToInPatient', 0);
            })
            ->where('PatientHistory.Status', 1)
            ->where('AD.alert', '!=', '1')
            ->whereNull('AD.dateofdeath')
            ->whereNull('PatientHistory.mergedwithid')
            ->where('SAD.ServiceTypeID', $ServiceTypeID)
            ->groupByRaw('CASE PatientHistory.PatientTypeID WHEN 1 THEN SAD.Description ELSE SAD.Description END')
            ->first();
                
            if(!$data){
                return false;
            }

            return $data;
        } catch (\Exception $e) {
            return response()->json(['message' => 'An error occurred', 'error' => $e->getMessage(), 'status' => 500]);
        }
    }

    private function getTotalER($ServiceTypeID){
        try{
            $ServiceTypeID = $ServiceTypeID == 45 ? 6 : $ServiceTypeID;

            $data = PatientHistory::join('Patients AS PAT', 'PatientHistory.PatientID', '=', 'PAT.PatientID')
            ->join('Persons AS PER', 'PAT.PersonID', '=', 'PER.PersonID')
            ->leftJoin('Admissions AS AD', 'PatientHistory.AdmissionID', '=', 'AD.AdmissionID')
            ->leftJoin('ServiceTypes AS SAD', 'AD.ServiceTypeID', '=', 'SAD.ServiceTypeID')
            ->join('PatientInfo AS pain', 'PatientHistory.PatientHistoryID', '=', 'pain.PatientHistoryID')
            ->join('Users AS u', 'PatientHistory.UserID', '=', 'u.UserID')
            ->join('Persons AS uper', 'u.PersonID', '=', 'uper.PersonID')
            ->leftJoin('PatientHistory AS pher', 'PatientHistory.PatientHistoryID', '=', 'pher.outpatienthistoryid')
            ->leftJoin('AdmissionsOPD AS ADOPD', 'pher.AdmissionOPDID', '=', 'ADOPD.AdmissionID')
            ->selectRaw("
                DATEDIFF(hh, PatientHistory.TransactionDateTime, GETDATE()) AS totaler,
                CASE PatientHistory.PatientTypeID WHEN 1 THEN SAD.Description ELSE SAD.Description END AS Department,
                PatientHistory.PatientHistoryID
            ")
            ->where('PatientHistory.PatientTypeID', 1)
            ->where('pher.PatientTypeID', 4)
            ->whereNotIn('PatientHistory.PatientStatusID', [6, 7, 8, 9, 10, 11, 13, 12])
            ->whereRaw('CAST(PatientHistory.TransactionDateTime AS DATE) between dateadd(DD, -10,CAST(GETDATE() AS DATE)) and CAST(GETDATE() AS DATE)')
            ->where(function($query){
                $query->whereNotNull('PatientHistory.AdmissionID')
                    ->orWhereNotNull('PatientHistory.AdmissionOPDID');
            })
            ->where(function($query){
                $query->whereNull('PatientHistory.SetToInPatient')
                    ->orWhere('PatientHistory.SetToInPatient', 0);
            })
            ->where('PatientHistory.Status', 1)
            ->where('AD.alert', '!=', '1')
            ->whereNull('AD.dateofdeath')
            ->whereNull('PatientHistory.mergedwithid')
            ->where('SAD.ServiceTypeID', $ServiceTypeID)
            ->get();
                
            if(!$data){
                return false;
            }

            $new = 0;
            $fiveHrs = 0;
            $tenHrs = 0;
            $twentyHrs = 0;
            $ambuCareUnitCount = 0;

            $data->each(function($d) use (&$new, &$fiveHrs, &$tenHrs, &$twentyHrs, &$ambuCareUnitCount ){
               
                $ambuCareUnit = Rooms::selectRaw("COUNT('rooms.id') AS count")
                    ->join('patient AS p', 'rooms.id', '=', 'p.roomid')
                    ->where('rooms.id', 2539)
                    ->where('p.patienthistoryid', $d->PatientHistoryID)
                    ->first();

                if($ambuCareUnit->count > 0){
                    $ambuCareUnitCount++;
                }

                if($d->totaler >=5 && $d->totaler <=9) {
                    $fiveHrs++;
                }
                elseif($d->totaler >=10 && $d->totaler <=20){
                    $tenHrs++;
                }
                elseif($d->totaler >=20) {
                    $twentyHrs++;
                }
                else {
                    $new++;
                }
            });

            return [
                'new' => $new,
                'fiveHrs' => $fiveHrs,
                'tenHrs' => $tenHrs,
                'twentyHrs' => $twentyHrs,
                'ambuCareUnit' => $ambuCareUnitCount,
            ];
        } catch (\Exception $e) {
            return response()->json(['message' => 'An error occurred', 'error' => $e->getMessage(), 'status' => 500]);
        }
    }

    public function updatePatientFiles(Request $request){

        $updateHistory = Referrals::where("referralID", $request-> referralID)
        ->update(['patientFiles'=> $request-> patientFiles]);

        return response()->json(["message" => "Success"], 200);
            }

}
