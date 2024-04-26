<?php

namespace App\Http\Controllers\referral;

use App\Http\Controllers\Controller;
use App\Models\referral\Notifications;
use App\Models\referral\persons;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Cookie;
use App\Notifications\CustomVerifyEmailNotification;
use Carbon\Carbon;

class AuthenticationController extends Controller
{
    public function createAccount(Request $request){

        $user = User::create([
            "hciID" => $request->hciID,
            "email" => $request->email,
            "contactno" => $request->contactno,
            "username" => $request->username,
            "password" => bcrypt('jblOURS'),
            "status" => 1,
        ]);

        $notification = new CustomVerifyEmailNotification($user);
        $user->notify($notification);
        return response()->json(["message" => "Success"], 200);
    }
  
    public function login(Request $request){
        try {
            $credentials = $request->only('username', 'password');
    
            // Check if the user exists and the status is 1
            $user = User::where('username', $credentials['username'])
                        ->where('status', 1)
                        ->first();
    
            if (!$user || !Hash::check($credentials['password'], $user->password)) {
                return response()->json([
                    'message' => 'Invalid username or password.',
                ], 401); // Unauthorized
            }
    
            if (Auth::loginUsingId($user->id)) {
                $token = $user->createToken("API TOKEN")->plainTextToken;
    
                return response()->json([
                    'message' => 'User Logged In Successfully',
                    'token' => $token,
                    'user' => $user,
                    'status' => 200,
                ], 200); // OK
            }
    
            return response()->json([
                'message' => 'Unable to authenticate user.',
            ], 401); // Unauthorized
        } catch (\Exception $e){
            Log::error("Error: " . $e->getMessage());
            return response()->json([
                "message" => "An error occurred.",
                "error" => $e->getMessage(),
            ], 500); // Internal Server Error
        }
    }
    
    
    public function logout(){
       try{
        Cookie::queue(Cookie::forget('token'));
             /** @var \App\Models\MyUserModel $user **/
            $user = Auth::user();
            if(!$user){
                return response()->json(['No user is authenticated'], 500);
            }
            Cookie::queue(Cookie::forget('token'));
            $user->tokens()->delete();
            return response()->json([
                'message' => 'Successfully logged out',
            ]);
       } catch (\Exception $e){
            Log::error("Error: " . $e->getMessage());
            return response()->json([
                "message" => "An error occurred.",
                "error" => $e->getMessage(),
                "status" => 500 
            ]);
        }
    }

    public function fetchUsers(Request $request){

        $hciID = $request->input('hospital');
    
        $query = User::join('activefacilities', 'users.hciID', '=', 'activefacilities.HealthFacilityCodeShort')
                ->select('users.*','activefacilities.FacilityName')
                ->where('users.status', '=', 1);
        if ($hciID !== null) {
            $query->where('activefacilities.HealthFacilityCodeShort', $hciID);
        }
    
        $data = $query->get();
        return $data;
    }

    public function fetchNotifications(Request $request){
        $user_id = $request->input('user_id');
        $notifications = Notifications::where('sent_to', $user_id)
        ->where('notificationType', '<>', 7)
        ->orderBy('sent_at', 'desc')
        ->get()
        ->map(function ($notification) {
            $notification->formatted_timestamp = Carbon::parse($notification->sent_at)->format('F j, Y h:i A'); 
            return $notification; 
        });

        return response()->json(["notifications" => $notifications, "message" => "Success"], 200);
    }

    public function fetchMessageNotifications(Request $request){
        $user_id = $request->input('user_id');
        $notifications = Notifications::where('sent_to', $user_id)
        ->where('notificationType', 7)
        ->orderBy('sent_at', 'desc')
        ->get()
        ->map(function ($notification) {
            $notification->formatted_timestamp = Carbon::parse($notification->sent_at)->format('F j, Y h:i A'); 
            return $notification; 
        });

        return response()->json(["notifications" => $notifications, "message" => "Success"], 200);
    }

    public function updatePassword(Request $request){
    
        $updated = User::where("id", $request->userID)
        ->update(['password' => bcrypt($request->password),
        'tempPassChanged' => 1]);    

        return response()->json(["message" => "Success"], 200);
    }

    public function removeUser(Request $request){  
        $removeHCI = User::where("id", $request-> userID)
        ->update(['status'=> 0]);

        return response()->json(["message" => "Success"], 200);
    }
}