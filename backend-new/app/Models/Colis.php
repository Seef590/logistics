<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Colis extends Model
{
    protected $hidden = ['pin_code'];

    protected $fillable = [
        'tracking_id','expediteur_id','destinataire_id','livreur_id','voyageur_id',
        'status','from_city','to_city','from_address','to_address',
        'recipient_name','recipient_phone','weight','description','price',
        'is_paid','payment_method','pin_code','pin_validated','qr_code',
        'notes','estimated_delivery','is_voyageur_eligible',
    ];

    protected $casts = ['is_paid' => 'boolean','pin_validated' => 'boolean','is_voyageur_eligible' => 'boolean'];

    public function expediteur() { return $this->belongsTo(User::class, 'expediteur_id'); }
    public function livreur()    { return $this->belongsTo(User::class, 'livreur_id'); }
    public function voyageur()   { return $this->belongsTo(User::class, 'voyageur_id'); }
    public function destinataire() { return $this->belongsTo(User::class, 'destinataire_id'); }
    public function statusHistory() { return $this->hasMany(StatusHistory::class); }
}
