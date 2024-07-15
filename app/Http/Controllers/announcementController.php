<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Announcements;
use Carbon\Carbon;

date_default_timezone_set('Asia/Manila');

class announcementController extends Controller
{
    public function fetchAllAnnouncements(){
        $data = Announcements::orderBy('id', 'DESC')->get();
        return $data;
    }

    public function fetchActiveAnnouncements(){
        $data = Announcements::where('Status', 1)->first();
        return $data;
    }

    public function createNewAnnouncement(Request $request){
        $setToExpire = Announcements::where('status', '1')
        ->update(['status' => '0']);


       $announcement = Announcements::create([
            'announcement' => $request->announcement,
            'created_by' => $request->created_by,
            'status' => '1'
        ]);

        return response()->json(["message" => "Referral Created"], 200);
    }      

    public function setAnnouncementToExpired(){
        $now = Carbon::now();
        $next7am = Carbon::now()->copy()->setTime(7, 0, 0);
    
        if ($now->hour >= 7) {
            $next7am->addDay();
        }
    
        $setToExpire = Announcements::where('created_at', '<=', $next7am->subDay())
            ->where('status', '1')
            ->update(['status' => '0']);
    }
    
    public function removeAnnouncement(Request $request){
        $update = Announcements::where('id', $request->id)
            ->update(['status' => 0]);
    }
}



