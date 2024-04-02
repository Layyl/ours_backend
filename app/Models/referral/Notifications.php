<?php

namespace App\Models\referral;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Notifications extends Model
{
    public $timestamps = false;
    
    protected $fillable = ['notification', 'notificationType', 'referralHistoryID', 'sent_at'];

 

    public function user(){
        return $this->belongsto(User::class);
    }
}

