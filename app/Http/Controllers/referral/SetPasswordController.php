<?php

namespace App\Http\Controllers\referral;

use App\Http\Controllers\Controller;
use App\Http\Requests\ResetPasswordRequest;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

class SetPasswordController extends Controller
{
    public function setPassword(Request $request)
    {
        try {
            $credentials = $this->credentials($request);
            $credentials['email'] = Crypt::decryptString($credentials['email']);
    
            $response = $this->broker()->reset(
                $credentials,
                function ($user, $password) {
                    $this->resetPassword($user, $password);
                }
            );
    
            return $response == Password::PASSWORD_RESET
                ? $this->sendResetResponse($request, $response)
                : $this->sendResetFailedResponse($request, $response);
        } catch (\Illuminate\Contracts\Encryption\DecryptException $e) {
            return response()->json([
                "message" => 'Error decrypting email',
                "error" => $e->getMessage()
            ], 500);
        } catch (\Exception $e) {
            return response()->json([
                "message" => 'Unexpected error',
                "error" => $e->getMessage()
            ], 500);
        }
    }
    

    protected function credentials(Request $request)
    {
        return $request->only(
            'email', 'password', 'password_confirmation', 'token'
        );
    }

    protected function resetPassword($user, $password)
    {
        $user->password = Hash::make($password);

        if ($user->markEmailAsVerified()) {
            event(new Verified($user));
        }
        
        $user->setRememberToken(Str::random(60));

        $user->save();
        // event(new PasswordReset($user));
    }

    public function broker()
    {
        return Password::broker();
    }

    protected function sendResetResponse(Request $request, $response)
    {
        return response()->json([
            "message" => 'Password reset succeeded',
            "response" => $response
        ], 200);
    }

    protected function sendResetFailedResponse(Request $request, $response)
    {
        return response()->json([
            "message" => 'Password reset failed',
            "response" => $response
        ], 500);
    }
}
