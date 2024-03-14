<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;

use App\Notifications\ResetPasswordNotification;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasApiTokens, Notifiable;
    
    protected $fillable = [
        'username',
        'password',
        'hciID',
        'email',
        'contactno',
        'status',
        'tempPassChanged',
    ];
    // protected $hidden = [
    //     'password',
    //     'remember_token',
    // ];

    // protected $casts = [
    //     'email_verified_at' => 'datetime',
    //     'password' => 'hashed',
    // ];
    protected $table = 'users';
    protected $connection = 'mysql';


public function sendPasswordResetNotification($token){
        $this->notify(new ResetPasswordNotification($token));
    }
}
