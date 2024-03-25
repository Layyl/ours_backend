<?php

namespace App\Http\Controllers\referral;

use Illuminate\Http\Request;

use App\Events\NewChatMessage;
use App\Http\Controllers\Controller;
use App\Models\Message;
use Illuminate\Support\Facades\Log;

class MessageController extends Controller
{
    public function broadcast(Request $request)
    {

        $request->validate([
            'message' => 'required|string',
            'referralHistoryID' => 'required|integer',
        ]);


        $user = auth()->user();


        $message = new Message();
        $message->message = $request->message;
        $message->referralHistoryID = $request->referralHistoryID;
        $message->user_id = $user->id; 
        $message->save();
        Log::info('New Chat Message Event Triggered', ['message' => $request->message, 'user_id' => $user->id, 'referralHistoryID' => $request->referralHistoryID]);

        event(new NewChatMessage($request->message, $request->user, $request->referralHistoryID));

        return response()->json([], 200);
    }

    }