<?php

namespace App\Models;

use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory;

    protected $fillable = [
        'name','email','phone','password','role','city',
        'cin','license_number','vehicle_type','vehicle_plate',
        'rating','rating_count','is_online','is_verified',
        'verification_status','warnings','suspended_until','is_banned',
    ];

    protected $hidden = ['password','remember_token'];

    protected $casts = [
        'is_online'   => 'boolean',
        'is_verified' => 'boolean',
        'is_banned'   => 'boolean',
        'rating'      => 'float',
        'suspended_until' => 'datetime',
    ];
}
