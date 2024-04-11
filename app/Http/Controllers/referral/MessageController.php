<?php

namespace App\Http\Controllers\referral;

use Illuminate\Http\Request;

use App\Events\NewChatMessage;
use App\Events\NewNotification;
use App\Http\Controllers\Controller;
use App\Models\Message;
use App\Models\referral\Notifications;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

use Illuminate\Support\Facades\Auth;

class MessageController extends Controller
{
    public function sendChat(Request $request)
    {
        $request->validate([
            'message' => 'required|string',
            'referralID' => 'required|integer',
        ]);

        $user = Auth::user();
        $user_id = $user->id;
        $username = $user->username;
        $dateTime = Carbon::now();
        $date = $dateTime->format('F j, Y'); 
        $time = $dateTime->format('g:i A');
        
        $message = new Message();
        $message->message = $request->message;
        $message->referralID = $request->referralID;
        $message->user_id = $user->id; 
        $message->sent_at = $dateTime;
        $message->save();

        Log::info('New Chat Message Event Triggered', [
            'message' => $request->message,
            'user_id' => $user->id,
            'referralID' => $request->referralID,
            'date' => $date,
            'time' => $time
        ]);
        event(new NewChatMessage($request->message, $user_id, $username, $request->referralID, $date, $time));

        $user = Auth::user();
        $user_id = $user->id;

        if ($request->sendingUser == strval($request->referringHospital)) {
            $sent_to = $request->receivingHospital;
        } else {
            $sent_to = $request->referringHospital;
        }
        
        $notification = sprintf("You have a new message for referral %s.", $request->fullName);
        $dateTime = Carbon::now();
        $date = $dateTime->format('F j, Y'); 
        $time = $dateTime->format('g:i A');
        
        $notif = new Notifications();
        $notif->notification = $notification;
        $notif->notificationType = 7;
        $notif->referralID = $request->referralID;
        $notif->user_id = $user->id; 
        $notif->sent_to = $sent_to;
        $notif->sent_at = $dateTime;
        $notif->save();

        event(new NewNotification($notification, $user_id, 7, $request->referralID, $sent_to, $date, $time));

        return response()->json([], 200);
    }
}
