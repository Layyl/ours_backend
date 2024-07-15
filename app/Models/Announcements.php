<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Announcements extends Model
{
    protected $fillable = [
        'announcement',
        'created_by',
        'status'
    ];

    protected $table = 'announcements';
    protected $connection = 'mysql';
}
