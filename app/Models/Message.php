<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Message extends Model
{

    protected $fillable = ['message', 'referralHistoryID'];
    public function user(){
        return $this->belongsto(User::class);
    }
}
