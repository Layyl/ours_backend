<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Message extends Model
{
    public $timestamps = false;
    
    protected $fillable = ['message', 'referralHistoryID', 'sent_at'];

 

    public function user(){
        return $this->belongsto(User::class);
    }
}
