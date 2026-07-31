<?php

namespace App\Models\Permission;

use Illuminate\Database\Eloquent\Model;

class Action extends Model
{
    protected $table = 'actions';
    protected $fillable = ['key', 'name'];
}