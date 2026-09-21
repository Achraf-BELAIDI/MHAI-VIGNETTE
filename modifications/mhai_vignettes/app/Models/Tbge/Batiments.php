<?php

namespace App\Models\Tbge;

use Eloquent;
use Config;
//use App\Models\Tbge\Compteurs;

class Batiments extends Eloquent {

  protected $table = 'batiment';
  protected $primaryKey = 'batimentid';
  
  protected $casts = [
    'created_at' => 'datetime',
    'updated_at' => 'datetime',
    'deleted_at' => 'datetime',
  ];

  // Ne pas forcer d/m/Y ici : PostgreSQL stocke Y-m-d[ H:i:s]
  // (sinon Carbon 3 → InvalidFormatException "Not enough data…")
  // Affichage d/m/Y géré dans le contrôleur.

  //public $timestamps = false;
  
  public function sourceFinancement(){
    return $this->hasOne(BatimentFinancementView::class, "batimentid", "batimentid");
  }
  
  public function actionsEngagees(){
  	return $this->hasMany(ActionEngagee::class, "batimentid", "batimentid");
  }

  public function commune(){
    return $this->belongsTo(Commune::class, "communeid", "communeid");
  }

  public function mhaiCommune(){
    return $this->belongsTo(MhaiCommuneLocal::class, "mos_commune_id", "commune_id");
  }
  public function mhaiDelegation(){
    return $this->belongsTo(MhaiDelegationLocal::class, "mos_delegation_id", "delegation_id");
  }

  public function ajustements(){
    return $this->hasMany(Ajustement::class, "batimentid", "batimentid");
  }

  public function ajustementsTEE(){
    return $this->hasMany(AjustementTEE::class, "batimentid", "batimentid");
  }

  public function delegation(){
  	return $this->hasOne(Mos::class, "mouvrageid", "mouvrageid");
  }

  public function compteurs(){
    return $this->hasManyThrough(Compteurs::class, "Compteurbatiments", "compteurid", "compteurid", "batimentid");
  }

  
  public function compteursElectricite(){
    $electriciteID = Config::get("enertrack.electriciteID");
    
    if($electriciteID == NULL || !is_int($electriciteID) || $electriciteID <= 0)
      $electriciteID = 1;

    return $this->belongsToMany(Compteurs::class, "compteurbatiments", "batimentid", "compteurid")->where("energieid", "=", $electriciteID);
  }

  public function compteursEau(){
    $eauID = Config::get("enertrack.eauID");

    if($eauID == NULL || !is_int($eauID) || $eauID <= 0)
      $eauID = 5;
    
    return $this->belongsToMany(Compteurs::class, "compteurbatiments", "batimentid", "compteurid")->where("energieid", "=", $eauID);
  }

  public function listes(){
    return $this->belongsToMany(Liste::class, "liste_batiment", "batimentid", "listeid");
  }
  
}