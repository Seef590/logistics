<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Ticket extends Model
{
    protected $fillable = ['user_id','colis_id','subject','message','status','priority'];
    public function responses() { return $this->hasMany(TicketResponse::class); }
    public function user()      { return $this->belongsTo(User::class); }
}
