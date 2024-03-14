<?php

namespace App\Models\referral;


use Illuminate\Database\Eloquent\Model;

class referralHistory extends Model
{
    protected $fillable = [
        'referralID',
        'receivingHospital',
        'referralStatus',
        
    ];
    protected $table = 'patientreferralhistory';
    protected $connection = 'mysql';
}
