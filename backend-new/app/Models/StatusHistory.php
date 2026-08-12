<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StatusHistory extends Model
{
    protected $fillable = ['colis_id','status','message','city','updated_by'];
    public function colis() { return $this->belongsTo(Colis::class); }
}
