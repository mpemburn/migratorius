<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DbMigration extends Model
{
    use HasFactory;

    public $table = 'db_migrations';
    public $fillable = [
        'sourceDatabase',
        'destDatabase',
        'subsiteUrl',
        'sourceSubsiteId',
        'destSubsiteId',
        'created',
    ];

}
