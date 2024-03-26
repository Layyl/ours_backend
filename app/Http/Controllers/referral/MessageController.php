<?php

namespace App\Http\Controllers\referral;

use Illuminate\Http\Request;

use App\Events\NewChatMessage;
use App\Http\Controllers\Controller;
use App\Models\Message;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

use Illuminate\Support\Facades\Auth;

class MessageController extends Controller
{
    public function broadcast(Request $request)
    {
        $request->validate([
            'message' => 'required|string',
            'referralHistoryID' => 'required|integer',
        ]);

        // Get the authenticated user
        $user = Auth::user();
        $user_id = $user->id;
        $username = $user->username;
        $dateTime = Carbon::now();
        $date = $dateTime->format('F j, Y'); 
        $time = $dateTime->format('g:i A');
        
        $message = new Message();
        $message->message = $request->message;
        $message->referralHistoryID = $request->referralHistoryID;
        $message->user_id = $user->id; 
        $message->sent_at = $dateTime;
        $message->save();

        Log::info('New Chat Message Event Triggered', [
            'message' => $request->message,
            'user_id' => $user->id,
            'referralHistoryID' => $request->referralHistoryID,
            'date' => $date,
            'time' => $time
        ]);
        event(new NewChatMessage($request->message, $user_id, $username, $request->referralHistoryID, $date, $time));

        return response()->json([], 200);
    }
}
