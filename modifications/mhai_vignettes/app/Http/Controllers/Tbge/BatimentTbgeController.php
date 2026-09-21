<?php

namespace App\Http\Controllers\Tbge;


use Auth;
use Request;
use DB;
use View;
use Response;
use Config;
use File;

use Validator;

use Str;

use App\Models\Tbge\Action;
use App\Models\Tbge\Commune;
use App\Models\Tbge\Region;
use App\Models\Tbge\Liste;

use App\Models\Tbge\DataTableResponse;

use App\Models\Tbge\Batiments;
use App\Models\Tbge\Compteurs;
use App\Models\Tbge\Compteurbatiments;
use App\Models\Tbge\Factures;
use App\Models\Tbge\Mos;
use App\Models\Tbge\Mouvrage;

use App\Models\Tbge\ActionEngagee;

use Excel;
use App\Metadata\Excel\MosqueesExport;

use App\Services\PostgreSqlFunctionCaller;

use Carbon\Carbon;
use Illuminate\Support\Facades\Schema;

class BatimentTbgeController extends BaseController {
	
	public function __construct() {
		parent::__construct ( [ 
				"dataName" => "batiments",
				"importViewName" => "tbge.patrimoine.batiment.importcsv",
				"importedViewName" => "tbge.patrimoine.batiment.importcsvimported",
				"importFormUrl" => "tbge/patrimoine/batiment/import/csv",
				"importPostUrl" => "tbge/patrimoine/batiment/import/csv/post",
				"importColumns" => [
					"Ignore" => [
						"display" => "Ignorer",
						"mysqlColumn" => "",
						"mysqlRank" => -1,
						"help" => "",
						"required" => false
					],
					"Nom" => [
						"display" => "Nom",
						"mysqlColumn" => "Nom",
						"mysqlRank" => 3,
						"help" => "",
						"required" => true
					],
					"NomAlternatif" => [
						"display" => "Nom alternatif",
						"mysqlColumn" => "nom_alternatif",
						"mysqlRank" => 3,
						"help" => "",
						"required" => true
					],
					"Anneeconstruction" => [
						"display" => "Année de construction",
						"mysqlColumn" => "Anneeconstruction",
						"mysqlRank" => 3,
						"help" => "",
						"required" => false
					],
					"Surface" => [
						"display" => "Surface",
						"mysqlColumn" => "Surface",
						"mysqlRank" => 3,
						"help" => "",
						"required" => false
					],
					"NbrEmployee" => [
						"display" => "Nombre d'employées",
						"mysqlColumn" => "NbrEmployee",
						"mysqlRank" => 3,
						"help" => "",
						"required" => false
					],
					"SystemeChauffageEau" => [
						"display" => "Système de Chauffage d'eau",
						"mysqlColumn" => "SystemeChauffageEau",
						"mysqlRank" => 3,
						"help" => "",
						"required" => false
					],
					"NumeroNational" => [
						"display" => "NuméroNational",
						"mysqlColumn" => "num_national",
						"mysqlRank" => 3,
						"help" => "",
						"required" => true
					],
					"Lieu" => [
						"display" => "Lieu",
						"mysqlColumn" =>"Lieu",
						"mysqlRank" => 3,
						"help" => "",
						"required" => false
					],
					"NombreMaisonsAssociees" => [
						"display" => "NombreMaisonsAssociees",
						"mysqlColumn" => "NombreMaisonsAssociees",
						"mysqlRank" => 3,
						"help" => "",
						"required" => false
					],
					"CapaciteAccueil" => [
						"display" => "CapaciteAccueil",
						"mysqlColumn" => "CapaciteAccueil",
						"mysqlRank" => 3,
						"help" => "",
						"required" => false
					],
					"Label" => [
						"display" => "Label",
						"mysqlColumn"  => "Label",
						"mysqlRank" => 3,
						"help" => "",
						"required" => false
					],
					"DateLabelisation" => [
						"display" => "DateLabelisation",
						"mysqlColumn" => "DateLabelisation",
						"mysqlRank" => 3,
						"help" => "",
						"required" => false
					],
					"PresenceEcoleCoranique" => [
						"display" => "Présence d'un école coranique",
						"mysqlColumn" => "PresenceEcoleCoranique",
						"mysqlRank" => 3,
						"help" => "",
						"required" => false
					],
					"AutresRemarques" => [
						"display" => "Autres remarques",
						"mysqlColumn" => "AutresRemarques",
						"mysqlRank" => 3,
						"help" => "",
						"required" => false
					],
					"mesuresEE" => [
						"display" => "mesures d'EE",
						"mysqlColumn" => "mesuresEE",
						"mysqlRank" => 3,
						"help" => "",
						"required" => false
					],
					"datesMesuresEE" => [
						"display" => "mesures d'EE",
						"mysqlColumn" => "datesMesuresEE",
						"mysqlRank" => 3,
						"help" => "",
						"required" => false
					],
					"CompteurEauFournisseur" => [
						"display" => "compteurs d'eau:Fournisseur",
						"mysqlColumn" => "CompteurEauFournisseur",
						"mysqlRank" => 3,
						"help" => "",
						"required" => false
					],
					"CompteurEau:IDLieu" => [
						"display" => "compteurs d'eau:ID Lieu",
						"mysqlColumn" => "CompteurEauIdLieu",
						"mysqlRank" => 3,
						"help" => "",
						"required" => true
					],
					"CompteurEauNumero" => [
						"display" => "compteurs d'eau:Numéro",
						"mysqlColumn" => "CompteurEauNumero",
						"mysqlRank" => 3,
						"help" => "",
						"required" => false
					],
					"CompteurElectriciteFournisseur" => [
						"display" => "compteurs d'électricite:Fournisseur",
						"mysqlColumn" => "CompteurElectriciteFournisseur",
						"mysqlRank" => 3,
						"help" => "",
						"required" => false
					],
					"CompteurElectriciteIdLieu" => [
						"display" => "compteurs d'électricite:ID Lieu",
						"mysqlColumn" => "CompteurElectriciteIdLieu",
						"mysqlRank" => 3,
						"help" => "",
						"required" => false
					],
					"CompteurElectriciteNumero" => [
						"display" => "compteurs d'électricite:Numero",
						"mysqlColumn" => "CompteurElectriciteNumero",
						"mysqlRank" => 3,
						"help" => "",
						"required" => true
					]
				]
		] );
		
		if(static::isMulticommunes()){
			$this->importColumns["Delegation"] = [
						"display" => "Delegation",
						"mysqlColumn" => "Delegation",
						"mysqlRank" => 3,
						"help" => "",
						"required" => false
			];
		}
	}

	public function doStuffs() {
		$stuffID = Request::has("stuffID") ? Request::input('stuffID') : NULL;
		
		if(empty($stuffID)){
			return Response::json([
					"status" => "error",
					"message" => "L'action à faire est indéterminée!!!"
				]);
		}

		if("deleteFicheTechniqueConfirmed" == $stuffID){
			$batimentID = Request::has("BatimentID") ? Request::input('BatimentID') : NULL;
			return Response::json([
					"status" => $this->deleteFicheTechnique($batimentID) ? "success" : "error",
					"message" => $this->deleteFicheTechnique($batimentID) ? "Fichier supprimé" : "Erreur lors de la suppression du fichier",
				]);
		}
		
		if("deleteCompteurConfirmed" == $stuffID){
			
			$batimentID = Request::has("BatimentID") ? Request::input('BatimentID') : NULL;
			$compteurID = Request::has("CompteurID") ? Request::input('CompteurID') : NULL;
			$deleteFacturesToo = Request::has("deleteFacturesToo") ? Request::input('deleteFacturesToo') : NULL;
			
			if($deleteFacturesToo === 'true' || $deleteFacturesToo === true){
				$deleteFacturesToo = true;
			} else {
				$deleteFacturesToo = false;
			}

			$deleteResult = $this->deleteCompteur($compteurID, $batimentID, $deleteFacturesToo);

			return Response::json([
					"status" => $deleteResult ? "success" : "error",
					"message" => $deleteResult ? "Compteur supprimé" : "Erreur lors de la suppression du compteur",
			]);
		}

		if(in_array($stuffID, ["addToList", "removeFromList"])){
			
			$pFilterListeMosquees = Request::input('pFilterListeMosquees', null);
			$mosquees = Request::input("pFilterMosquees", []);

			$actionText = "addToList" == $stuffID ? "ajoutée" : "retirée";
			$actionTextPart2 = "addToList" == $stuffID ? "à" : "de";
			$status = "success";
			$result = "Mosquée" . (count($mosquees) > 1 ? "s": "") . " correctement $actionText" . (count($mosquees) > 1 ? "s": "") . " $actionTextPart2 la liste";

			$list = Liste::find($pFilterListeMosquees);
			$toAdd = array_diff($mosquees, $list->mosquees->pluck("batimentid")->toArray());

			
			if("addToList" == $stuffID && $list != null){
				$list->mosquees()->attach($toAdd);
			} else {
				$list->mosquees()->detach($mosquees);
			}

			return Response::json([
				"status" => $status,
				"message" => $result,
			]);

		}

		if(in_array($stuffID, ["addToProgrammeMv", "removeFromProgrammeMv"])){
			$pFilterDateInclusionAuProgMV = Request::input('pFilterDateInclusionAuProgMV', null);
			$mosquees = Request::input("pFilterMosquees", []);

			$actionText = "addToProgrammeMv" == $stuffID ? "ajoutée" : "retirée";
			$actionTextPart2 = "addToList" == $stuffID ? "au" : "du";
			$status = "success";
			$result = "Mosquée" . (count($mosquees) > 1 ? "s": "") . " correctement $actionText" . (count($mosquees) > 1 ? "s": "") . " $actionTextPart2 programme Mosquées Vertes";

			
			foreach ($mosquees as $mosqueeId) {
				$mosquee = Batiments::find($mosqueeId);
				
				if(!$mosquee)
					continue;

				if("addToProgrammeMv" == $stuffID){
					$mosquee->is_inclus_prog_mv = 1;
					$mosquee->date_inclusion_prog_mv = $pFilterDateInclusionAuProgMV;
				} else {
					$mosquee->is_inclus_prog_mv = 0;
					$mosquee->date_inclusion_prog_mv = NULL;
				}

				$mosquee->save();
			}
			
			return Response::json([
				"status" => $status,
				"message" => $result,
			]);
		}
	}

	public function deleteMultiple() {
	  
	  $batimentIDs = \Request::input("ids");
	  
	  if(is_numeric($batimentIDs))
	  	$batimentIDs = [$batimentIDs];
	  
	  if(!is_array($batimentIDs))
	  	$batimentIDs = [];

	  $toDelete = Batiments::whereIn("batimentid", $batimentIDs)->get();

	  foreach ($toDelete as $setDeleted) {
	  	$setDeleted->mos_supprime = 'Y';
	  	$setDeleted->save();
	  }

	  return Response::json([
	  		"status" => "success",
	  		"message" => count($toDelete) . " Mosquée(s) Marquées comme supprimée(s)"
	  ]);
	}

	public function deleteCompteur($compteurID, $batimentID, $deleteFacturesToo = FALSE) {
		
		if(!is_numeric($compteurID) || !is_numeric($batimentID))
			return false;

		$cbDeleted = Compteurbatiments::where("compteurid", $compteurID)->delete();
		$cDeleted = Compteurs::where("compteurid", $compteurID)->delete();
		$fDeleted = Factures::where("compteurid", $compteurID)->delete();
		
		if($deleteFacturesToo === true){
			// TODO: Delete factureBatiments too????
		}

		return $cbDeleted && $cDeleted && $fDeleted;
	}

	public function deleteFicheTechnique($BatimentID) {
		
		$batiment = Batiments::find($BatimentID);
		if(!$batiment)
			return true;
		
		$path = $batiment->fichetechnique;
		File::delete($path);
		
		$batiment->fichetechnique = "";
		$batiment->save();
		return true;
	}

	public function index() {
		//phpinfo();
		$baseid = "8e0910e0-cdee-70a1-55c3-b0f48ee8127f"; //Auth::user ()->baseid;
		
		$canEdit = $this->canEdit ();
		$canCreate = $this->canCreate ();
		$canDelete = $this->canDelete ();

      	$listes = Liste::select([
      		DB::raw("ListeID"),
      		DB::raw("Nom")
      	])->get();
		$delegations = MoController::delegationDropdownList();

      	//dd($delegations[2]->delegations);
		
		return View::make ( 'tbge.tbge.patrimoine.batiment.index' )->with ( [ 
			//'batiments' => $batiments,
			'delegations' => $delegations,
			"listes" => $listes,
			"canEdit" => $canEdit,
			"canCreate" => $canCreate,
			"canDelete" => $canDelete 
		]);
	}

	public function select2() {
		
		$search = \Request::input ( 'q', [] );
		$searchTerm = NULL;
		
		if(is_array($search) && isset($search["term"]) && is_string($search["term"])) {
			$searchTerm = trim($search['term']);
		}
		
		$delegationID = \Request::input ('pFilterDelegation', NULL);
		
		$userId = Auth::user()->utilisateurid;
		if(static::isMulticommunes()){
			$userId = NULL;
		}
		
		$params = [

			"p_lib_batiment" 				=> [0,   "character", NULL 							],
			"p_listeid" 					=> [1,   "integer", NULL 							],
			"p_is_inclus_prog_mv" 			=> [2,   "smallint", NULL 							],
			"p_communeid" 					=> [3,   "integer", NULL 							],
			"p_mos_sourcefin" 				=> [4,   "character", NULL 							],
			"p_mos_sourcefin_eau" 			=> [5,   "character", NULL 							],
			"p_mos_sourcefin_electricite" 	=> [6,   "character", NULL 							],
			"p_mix_sourcefin" 				=> [7,   "character", NULL 							],
			"p_mouvrageid" 					=> [8,   "integer", $delegationID					],
			"p_utilisateurid" 				=> [9,   "integer", $userId 						],
			"p_etat_batiment" 				=> [10,  "integer", NULL 							],
			"p_is_supprime" 				=> [11,  "boolean", NULL 							],
			"p_lib_search"              	=> [12 , "character varying", $searchTerm 			],
			"p_sorting_criteria"        	=> [13 , "character varying", NULL 	],
			"p_page_index"              	=> [14 , "integer", '0' 						   	],
			"p_page_size"               	=> [15 , "integer", 100 							],
		];

		$procedureName = PostgreSqlFunctionCaller::$PROCEDURE_NAME_BATIMENT_SEARCH;

		$selectionSql = <<<EOD

			_src.batimentid AS id, 
			_src.nom 		AS text,
			_src.nom		AS NomMosquee,
			
			COUNT(DISTINCT compteur.CompteurID) 			AS NbrCompteurs,
			STRING_AGG(DISTINCT compteur.Reference, ', ') 	AS ReferencesCompteurs,
			
			_src.nb_total,

			_src.batimentid,
			_src.nom_alternatif,
			_src.num_national
		EOD;
		
		$extraJoinsSql = <<<EOD
			LEFT JOIN sk_tbge.mouvrage mo ON mo.mouvrageid = _src.mouvrageid
		 	LEFT JOIN sk_tbge.mouvrage mop ON mop.mouvrageid = mo.mouvrage_parent_id
		 	
		 	LEFT JOIN sk_tbge.compteurbatiments ON _src.batimentid = compteurbatiments.batimentid
		 	LEFT JOIN sk_tbge.compteur ON compteur.compteurid = compteurbatiments.compteurid

		 	GROUP BY 	_src.batimentid,
				 		_src.nom,
				 		_src.batimentid,
						_src.nom_alternatif,
						_src.num_national,
						_src.nb_total,
						mop.mouvrageid
			
			ORDER BY mop.mouvrageid

		EOD;

		$caller = new PostgreSqlFunctionCaller($procedureName, $params, $selectionSql, FALSE, FALSE, $extraJoinsSql);
		
		$result = $caller->call();
		$total = 0;

		if(empty($result)){
			$result = [];
		} else {
			$total = $result[0]->nb_total;
		}
		
		if(count($result) == 1){
			$result[0]->selected = true;
		}

		$draw = \Request::input ( 'draw', 1 );

		$datatable = (object)[
			'draw' 				=> $draw,
			'recordsTotal' 		=> $total,
			'recordsFiltered' 	=> $total,
			'data' 				=> $result,
			'error' 			=> null,
		];

		return Response::json ( $datatable );

	}
	
	public function createDev() {
		return View::make ( 'tbge.mosquee.create.main');
	}

	public function updateDev() {
		return View::make ( 'tbge.mosquee.update.main' );
	}

	public function readDev() {
		return View::make ( 'tbge.mosquee.read.main' );
	}

	public function create() {
		return View::make ( 'tbge.tbge.patrimoine.batiment.createNotPermitted' );
	}

	public function store() {
		$baseid = "8e0910e0-cdee-70a1-55c3-b0f48ee8127f"; // Auth::user ()->baseid;
		
		$validation = Validator::make ( \Input::all (), array (
				'NumeroNational' => 'required',
				'Nom' => 'required'
		), array (
				'NumeroNational' => "Le numéro national est obligatoire!",
				'Nom.required' => "Le nom du batiment est obligatoire!" 
		) );
		
		if ($validation->fails ()) {
			return Redirect::to ( 'tbge/patrimoine/batiment/create' )->withErrors ( $validation )->withInput ( \Input::all () );
		} else {
			
			$isMulticommunes = static::isMulticommunes();

			if(!$isMulticommunes){
				$delegationID = static::getMyCommuneID();
			} else {
				$delegationID = \Request::input ( 'DelegationID' );
			}

			$isUpdate = \Request::has ( 'BatimentID' ) && \Request::input ( 'BatimentID' ) > 0;
			$numeroNationalExists = Batiments::where("num_national", \Request::input('NumeroNational'))->first();

			if(!$isUpdate && $numeroNationalExists!=NULL){
				
				$autreDelegationText = "Ce numéro national a déjà été attribué à une mosquée de la Délégation de : " . $numeroNationalExists->delegation->Societe;
				
				$lienText = "<a href='/tbge/patrimoine/batiment/" . $numeroNationalExists->BatimentID ."/edit'>". $numeroNationalExists->num_national . " ["  . $numeroNationalExists->Nom . "] <span class='glyphicon glyphicon-pencil'></span></a>.";
				
				$textErreur = $lienText;

				if(!$isMulticommunes && $delegationID != $numeroNationalExists->MouvrageID){
					$textErreur = $autreDelegationText;
				}
				
				return Redirect::to ( 'tbge/patrimoine/batiment/create' )->withErrors ( ["NumeroNational" => "Le numéro national suivant a déjà été attribué à une autre mosquée. : $textErreur" ] )->withInput ( \Input::all () );
			}

			$batiment = $isUpdate ? Batiments::find(\Request::input ( 'BatimentID' )) : new Batiments;
			
			$batiment->baseid = $baseid;
			$isMulticommunes = static::isMulticommunes();

			if(!$isMulticommunes){
				$delegationID = static::getMyCommuneID();
			} else {
				$delegationID = \Request::input ( 'DelegationID' );
			}

			
			// Inclusion au programme
			$batiment->{strtolower('is_inclus_prog_mv')} = \Request::has("EstIncluDansLeProgramme") ? \Request::input("EstIncluDansLeProgramme") : NULL;
          	$dateInclusion = empty(Request::input('DateInclusionAuProgramme')) ? NULL : Request::input('DateInclusionAuProgramme');
			$batiment->{strtolower('date_inclusion_prog_mv')} = $dateInclusion;

			//$batiment->{strtolower('CommuneID')} = \Request::has("CommuneID") ? \Request::input("CommuneID") : -1;
			if($delegationID > 0 && is_numeric($delegationID)){
				$batiment->{strtolower('MouvrageID')} = $delegationID;// \Request::input ( 'DelegationID' );
				$batiment->{strtolower('DelegationID')} = $delegationID;
			}
			
			$batiment->{strtolower('num_national')} = \Request::input ( 'NumeroNational' );
			
			$batiment->{strtolower('Nom')} = \Request::input ( 'Nom' );
			$batiment->{strtolower('nom_alternatif')} = \Request::input ( 'NomAlternatif' );
			
			$batiment->{strtolower('annee_construction')} = \Request::input ( 'Anneeconstruction' );
			
			$batiment->{strtolower('surface')} = \Request::input ( 'Surface' );
			$batiment->{strtolower('surface_chauffee')} = \Request::input ( 'SurfaceChauffee' );
			
			$batiment->{strtolower('nb_employe')} = \Request::input ( 'NbrEmployee' );
			
			$batiment->{strtolower('adresse')} = \Request::input ( 'Lieu' );
			
			$batiment->{strtolower('nb_maison_associee')} = \Request::input ( 'NombreMaisonsAssociees' );
			$batiment->{strtolower('capacite_accueil')} = \Request::input ( 'CapaciteAccueil' );
			
			$batiment->{strtolower('label')} = \Request::input ( 'Label' );
          	$dateLabel = empty(Request::input('DateLabelisation')) ? NULL : Request::input('DateLabelisation');
			$batiment->{strtolower('date_labelisation')} = $dateLabel;
			
			$batiment->{strtolower('Categorie')} = \Request::input ( 'Categorie' );

			$batiment->{strtolower('has_ecole_coranique')} = \Request::input ( 'PresenceEcoleCoranique' )  == 'on' ? TRUE : FALSE;;
			$batiment->{strtolower('has_puit')} = \Request::input ( 'PresencePuit' ) == 'on' ? TRUE : FALSE;
			
			$batiment->{strtolower('remarque')} = \Request::input ( 'AutresRemarques' );

			// Equipements
			$batiment->{strtolower('nb_pointlumineux')} = \Request::input('NombreDePointsLumineux');
			$batiment->{strtolower('remarque_pointlumineux')} = \Request::input('RemarquesPointsLumineux');

			$batiment->{strtolower('nb_chauffeeau_electrique')} = \Request::input('NombreDeChauffeEauElectriques');
			$batiment->{strtolower('remarque_chauffeeau_electrique')} = \Request::input('RemarquesChauffeEauElectriques');

			$batiment->{strtolower('nb_chauffeeau_gaz')} 		= \Request::input('NombreDeChauffeEauAGaz');
			$batiment->{strtolower('remarque_chauffeeau_gaz')} 	= \Request::input('RemarquesChauffeEauAGaz');
			
			$batiment->{strtolower('nb_chauffeeau_solaire')} 		= \Request::input('NombreDeChauffeEauSolaires');
			$batiment->{strtolower('remarque_chauffeeau_solaire')} 	= \Request::input('RemarquesChauffeEauSolaires');

			$batiment->{strtolower('nb_equipement_autre')} 		 = \Request::input('NombreDAutresEquipements');
			$batiment->{strtolower('remarque_equipement_autre')} = \Request::input('RemarquesAutresEquipements');

			$batiment->{strtolower('date_modification')} = \Request::input('DateDerniereModification');
			
			$batiment->{strtolower('mos_sourcefin_eau')} = (\Request::has('mos_sourcefin_eau') && in_array(\Request::input('mos_sourcefin_eau'), ["H", "B", "D"])) ? \Request::input('mos_sourcefin_eau') : NULL ;
			$batiment->{strtolower('mos_sourcefin_electricite')} = (\Request::has('mos_sourcefin_electricite') && in_array(\Request::input('mos_sourcefin_electricite'), ["H", "B", "D"])) ? \Request::input('mos_sourcefin_electricite') : NULL ;

			$batiment->save();
			
			// Ajouter les mesures d'efficacité énergétiques
			$this->addOrEditMesuresEE($batiment, $baseid, $delegationID);
			
			$modifierUrl = URL::to ( 'tbge/patrimoine/batiment/' . $batiment->{strtolower('BatimentID')} . '/edit' );
			Session::flash ( 'batiment.success', "<p class='alert alert-success'>Création du batiment effectué avec succès ! <a href='{$modifierUrl}' class='btn btn-info'>Modifier le bâtiment</a></p>" );
			//return Redirect::to ( 'tbge/patrimoine/batiment' );
			return Redirect::back ();
		}
	}

	public function detachAjustements($batiment, $isRCC = TRUE) {
		if($isRCC == TRUE) 
			Ajustement::where(DB::raw("BatimentID"), "=", $batiment->BatimentID)->delete();
		else {
			AjustementTEE::where(DB::raw("BatimentID"), "=", $batiment->BatimentID)->delete();
		}
	}

	public function addOrEditAjustements($batiment, $baseid, $delegationID, $isRCC  = TRUE) {

		$ajustementsAnnees = [];
		$ajustementsValeurs = [];

		if (is_array ( \Request::input ( $isRCC == TRUE ? 'ajustementsAnnees' : 'ajustementsTEEAnnees' ) )) {
			$ajustementsAnnees = \Request::input ( $isRCC == TRUE ? 'ajustementsAnnees' : 'ajustementsTEEAnnees' );
		}

		if (is_array ( \Request::input ( $isRCC == TRUE ? 'ajustementsValeurs' : 'ajustementsTEEValeurs' ) )) {
			$ajustementsValeurs = \Request::input ( $isRCC == TRUE ? 'ajustementsValeurs' : 'ajustementsTEEValeurs' );
		}
		
		if(count($ajustementsAnnees) == 0){
			$this->detachAjustements($batiment, $isRCC);
			return;
		}

		$newAjustements = \Request::input ( $isRCC == TRUE ? 'ajustementsAnnees'  : 'ajustementsTEEAnnees' );

		$oldAjustements = $isRCC == TRUE ? Ajustement::select([
				"annee"
			])->where(DB::raw("BatimentID"), "=", $batiment->{strtolower('BatimentID')})->get() : AjustementTEE::select([
				"annee"
			])->where(DB::raw("BatimentID"), "=", $batiment->{strtolower('BatimentID')})->get() ;
		
		if(count($oldAjustements) > 0){
			$oldAjustements = $this->objectsToArray($oldAjustements, "annee", "annee");
		} else{
			$oldAjustements = [];
		}
		$acts = [];
		foreach ($oldAjustements as $key => $value) {
			$acts[] = $key;
		}
		$oldAjustements = $acts;

		$addedAjustements = array_diff($newAjustements, $oldAjustements);
		$removedAjustements = array_diff($oldAjustements, $newAjustements );
		
		
		foreach ($removedAjustements as $removedId) {
			if($isRCC == TRUE)
				Ajustement::where(DB::raw("BatimentID"), "=", $batiment->{strtolower('BatimentID')})->where("annee", "=", $removedId)->delete();
			else 
				AjustementTEE::where(DB::raw("BatimentID"), "=", $batiment->{strtolower('BatimentID')})->where("annee", "=", $removedId)->delete();
		}

		for ( $i = 0; $i < count($ajustementsAnnees); $i++) { 
		 	
		 	$ajustementID = $ajustementsAnnees[$i];
		 	$ajustementValeur = $ajustementsValeurs[$i];
			
			if(empty($ajustementID) || empty($ajustementValeur))
				continue;

			$ajustementExists = $isRCC == TRUE ? 
								Ajustement::where("annee", "=", $ajustementID)->where(DB::raw("BatimentID"), "=", $batiment->{strtolower('BatimentID')})->first() 
								: AjustementTEE::where("annee", "=", $ajustementID)->where(DB::raw("BatimentID"), "=", $batiment->{strtolower('BatimentID')})->first();
			
			$ajustementExists = $ajustementExists == NULL || $ajustementExists->AjustementID <= 0 ? FALSE : $ajustementExists->AjustementID;
			
			$ajustement = $ajustementExists === FALSE ? 
									($isRCC == TRUE ? new Ajustement : new AjustementTEE) 
								  : ($isRCC == TRUE ?  Ajustement::find($ajustementExists) : AjustementTEE::find($ajustementExists));
				
			$ajustement->BatimentID = $batiment->{strtolower('BatimentID')};
			$ajustement->annee = $ajustementID;
			$ajustement->valeur = $ajustementValeur;
			//$ajustement->baseid = $baseid;
			//$ajustement->MouvrageID = $delegationID;// static::getInsertionMouvrageID();

			$ajustement->save ();
		}
	}

	public function detachMesures($batiment) {
		ActionEngagee::where(DB::raw("BatimentID"), "=", $batiment->{strtolower('BatimentID')})->delete();
	}

	public function addOrEditMesuresEE($batiment, $baseid, $delegationID) {
		
		$actives = [];
		$inactives = [];

		if(\Request::input ( 'mesureEE-LED' ) == "on")
			$actives["1"] = \Request::input ( 'mesureEE-LED-date');
		else
			$inactives[] = 1;

		if(\Request::input ( 'mesureEE-CES' ) == "on")
			$actives["6"] = \Request::input ( 'mesureEE-CES-date');
		else
			$inactives[] = 6;

		if(\Request::input ( 'mesureEE-PV' ) == "on")
			$actives["7"] = \Request::input ( 'mesureEE-PV-date');
		else
			$inactives[] = 7;

		foreach ($inactives as $inactiveID) {
			ActionEngagee::where(DB::raw("BatimentID"), "=", $batiment->{strtolower('BatimentID')})->where(DB::raw("ActionID"), "=", $inactiveID)->delete();
		}

		foreach ($actives as $mesureID => $mesureDate) {
			
			if(empty($mesureID) || empty($mesureDate))
				continue;

			$mesureExists = ActionEngagee::where(DB::raw("ActionID"), "=", $mesureID)->where(DB::raw("BatimentID"), "=", $batiment->{strtolower('BatimentID')})->first();
			$mesureExists = $mesureExists == NULL || $mesureExists->actionengageeid <= 0 ? FALSE : $mesureExists->actionengageeid;
			
			$mesure = $mesureExists === FALSE ? new ActionEngagee : ActionEngagee::find($mesureExists);
				
			$mesure->{strtolower('BatimentID')} = $batiment->{strtolower('BatimentID')};
			$mesure->{strtolower('ActionID')} = $mesureID;
			$mesure->{strtolower('BaseID')} = $baseid;
			$mesure->{strtolower('MouvrageID')} = $batiment->{strtolower('MouvrageID')};// static::getInsertionMouvrageID();

			$mesure->{strtolower('Date')} = strpos($mesureDate, "-") !== false  ? \Carbon\Carbon::createFromFormat('Y-m-d', $mesureDate) : \Carbon\Carbon::createFromFormat('d/m/Y', $mesureDate);
			$mesure->save ();
		}

	}
	
	public function addNewFournisseur($name) {
		
		$fournisseur = new Contacts;
		$fournisseur->{strtolower('Nom')} = trim($name);
		$fournisseur->save();

		return $fournisseur->{strtolower('Fournisseurid')};
	}
	
	public function viewMosquee($id) {
		return $this->edit($id, 'read');
	}

	/**
	 * Formate une date pour l'UI (d/m/Y) sans planter si la valeur est incomplète / déjà formatée.
	 */
	private function formatDateSafe($value): string {
		if ($value === null || $value === '') {
			return '';
		}
		if ($value instanceof \DateTimeInterface) {
			return Carbon::instance($value)->format('d/m/Y');
		}
		$raw = trim((string) $value);
		if ($raw === '' || $raw === '0000-00-00' || str_starts_with($raw, '0000-00-00')) {
			return '';
		}
		try {
			if (preg_match('/^\d{2}\/\d{2}\/\d{4}/', $raw)) {
				return Carbon::createFromFormat('d/m/Y', substr($raw, 0, 10))->format('d/m/Y');
			}
			if (preg_match('/^\d{4}-\d{2}-\d{2}/', $raw)) {
				return Carbon::parse($raw)->format('d/m/Y');
			}
			return Carbon::parse($raw)->format('d/m/Y');
		} catch (\Throwable $e) {
			return $raw;
		}
	}

	public function edit($id, $mode="edit") {
		
		$username = Auth::user ()->{strtolower('Username')};
		$baseid = "8e0910e0-cdee-70a1-55c3-b0f48ee8127f"; // Auth::user ()->{strtolower('BaseID')};
		
		$communeFilter =  static::buildPatrimoinesCommuneFilter('compteur');

		$canEdit = $this->canEdit ();
		$canCreate = $this->canCreate ();
		$canDelete = $this->canDelete ();

		// Colonnes legacy SRM (évite Schema::hasColumn dans chaque closure with())
		$hasFournisseurAncien = Schema::connection('tbge')->hasColumn('compteur', 'fournisseur_ancien_id');

		$batiment = Batiments::with([
          "sourceFinancement",
          
          "compteursEau" => function ($cQ) use ($hasFournisseurAncien) {
            $cols = [
                DB::raw("compteur.CompteurID"),
                DB::raw("compteur.Reference"),
                DB::raw("compteur.reference_ancienne"),
                DB::raw("compteur.Numero"),
                DB::raw("compteur.FournisseurID"),
                DB::raw("compteur.TypeFactureID"),
                DB::raw("compteur.TypeTarificationID"),
                DB::raw("EnergieID")
            ];
            if ($hasFournisseurAncien) {
                $cols[] = DB::raw("compteur.fournisseur_ancien_id");
            }
            $cQ->select($cols)->with([
              	"Fournisseur"  => function ($qF) {
                	$qF->select([DB::raw("FournisseurID"), DB::raw("Nom") ]);
                }
              ]);
          },
          
          "compteursElectricite" => function ($cQ) use ($hasFournisseurAncien) {
            $cols = [
                DB::raw("compteur.CompteurID"),
                DB::raw("compteur.Reference"),
                DB::raw("compteur.reference_ancienne"),
                DB::raw("compteur.Numero"),
                DB::raw("compteur.FournisseurID"),
                DB::raw("compteur.TypeFactureID"),
                DB::raw("compteur.TypeTarificationID"),
                DB::raw("EnergieID")
            ];
            if ($hasFournisseurAncien) {
                $cols[] = DB::raw("compteur.fournisseur_ancien_id");
            }
            $cQ->select($cols)->with([
              	"Fournisseur"  => function ($qF) {
                	$qF->select([DB::raw("FournisseurID"), DB::raw("Nom") ]);
                }
              ]);
          },
          
          "mhaiCommune" => function ($qMhaiCommune) {
          	$qMhaiCommune->select([
          			"commune_id",
          			"name",
          		]);
          },
          "mhaiDelegation" => function ($qMhaiDelegation) {
          	$qMhaiDelegation->select([
          			"delegation_id",
          			"name",
          		]);
          }
        ])->find ( $id );

        //dd($batiment->find($id));

		if($batiment == NULL || $batiment->batimentid != $id){
			return View::make ( 'tbge.tbge.patrimoine.batiment.notfound' )
							->with ( 'BatimentID', $id );
		}
		
		if(!static::isMulticommunes() && $batiment->mouvrageid !== static::getMyCommuneID()){
	        return View::make ( 'tbge.tbge.patrimoine.batiment.notfound' )
							->with ( 'BatimentID', $id );
	    }

		$mesuresEE = Action::select([
				DB::raw("ActionID"),
				DB::raw("Nom"),
				DB::raw("Type")
			])->get();
		
		$categories = array (
			2 => "Délégation",
			3 => "Mosquée de vendredi",
			4 => "Salle de prière",
			6 => "5 prières",
			5 => "Autre"
		);
		$fournisseurs = DB::select ( "select fournisseurid AS CoordonneeID, Nom from fournisseur order by fournisseurid, Nom" );
		
		$actions = $batiment->actionsEngagees;
		
		$mesuresSelected = $batiment->actionsEngagees;
		$selectedMesuresEE = [];

		if(count($mesuresSelected) > 0){
			foreach ($mesuresSelected as $mesure) {
				$mesure->date = $this->formatDateSafe($mesure->date);

				$selectedMesuresEE[$mesure->{strtolower('ActionID')}] =  $mesure->{strtolower('ActionID')};
			}
		}
		$delegationSelected = $batiment->delegation;
		$communeSelected = $batiment->commune;
		
		$datesFields = [
			"date_labelisation",
			"date_inclusion_prog_mv",
			"date_modification",
		];

		foreach ($datesFields as $dateField) {
			if(empty($batiment->{$dateField}))
				continue;
			
			$batiment->{$dateField} = $this->formatDateSafe($batiment->{$dateField});
		}

		if(!empty($batiment->date_labelisation)){

		}

		$view = $mode == 'edit' ? 'tbge.tbge.patrimoine.batiment.edit' : "tbge.tbge.patrimoine.batiment.view";

		return View::make ( $view )
						->with ( 'batiment', $batiment )
						->with ( 'categories', $categories )
						->with ( 'fournisseurs', $fournisseurs )
						->with ( 'mesuresEE', $mesuresEE )
						->with ( 'mesuresSelected', $selectedMesuresEE )
						->with ( "canEdit", $canEdit)
						->with ( "canCreate", $canCreate)
						->with ( "canDelete", $canDelete );
	}
	public function update($id) {
		
		$baseid = "8e0910e0-cdee-70a1-55c3-b0f48ee8127f"; // Auth::user ()->baseid;
		
		$validation = Validator::make ( \Input::all (), array (
				'Nom' => 'required' 
		), array (
				'Nom.required' => "Le nom du batiment est obligatoire !" 
		) );
		
		if ($validation->fails ()) {
			return Redirect::to ( 'tbge/patrimoine/batiment/' . $id . '/edit' )->withErrors ( $validation )->withInput ( \Input::all () );
		}

		$divisionConc = "";
		$divisions = \Request::input ( 'Division' );
		
		if (is_array ( $divisions )) {
			foreach ( $divisions as $key => $division ) {
				if ($divisionConc == "") {
					$divisionConc = $division;
				} else {
					$divisionConc = $divisionConc . "-" . $division;
				}
			}
		}
		
		$batiment = Batiments::find ( $id );
		
		$batiment->{strtolower('CommuneID')} = \Request::has("CommuneID") ? \Request::input("CommuneID") : $batiment->{strtolower('CommuneID')};
		
		$batiment->{strtolower('num_national')} = \Request::input ( 'NumeroNational' );
		$batiment->{strtolower('Nom')} = \Request::input ( 'Nom' );
		$batiment->{strtolower('nom_alternatif')} = \Request::input ( 'NomAlternatif' );
		
		$batiment->{strtolower('annee_construction')} = \Request::input ( 'Anneeconstruction' );
		
		$batiment->{strtolower('Surface')} = \Request::input ( 'Surface' );
		$batiment->{strtolower('SurfaceChauffee')} = \Request::input ( 'SurfaceChauffee' );
		$batiment->{strtolower('nb_employe')} = \Request::input ( 'NbrEmployee' );
		
		$batiment->{strtolower('MouvrageID')} = \Request::input ( 'DelegationID' );

		$batiment->{strtolower('Lieu')} = \Request::input ( 'Lieu' );
		$batiment->{strtolower('nb_maison_associee')} = \Request::input ( 'NombreMaisonsAssociees' );
		$batiment->{strtolower('capacite_accueil')} = \Request::input ( 'CapaciteAccueil' );
		$batiment->{strtolower('label')} = \Request::input ( 'Label' );
          
          	$dateLabel = empty(Request::input('Debutperiode')) ? NULL : \Carbon\Carbon::createFromFormat('d/m/Y', Request::input('DateLabelisation'));
		$batiment->{strtolower('DateLabelisation')} = $dateLabel;
		
		$batiment->{strtolower('has_ecole_coranique')} = Request::has ( 'PresenceEcoleCoranique' ) ? (Request::input ( 'PresenceEcoleCoranique' ) ? 1 : 0) : 0;
		$batiment->{strtolower('Categorie')} = \Request::input ( 'Categorie' );
		$batiment->{strtolower('remarque')} = \Request::input ( 'AutresRemarques' );

		//$batiment->save ();
		
		// Modifier les mesures d'efficacité énergétiques
		if (is_array ( \Request::input ( 'mesuresEE' ) )) {
			$newActions = \Request::input ( 'mesuresEE' );
			
			$oldActions = ActionEngagee::select([
					"ActionID"
				])->where(DB::raw("BatimentID"), "=", $batiment->{strtolower('BatimentID')})->get();
			
			if(count($oldActions) > 0){
				$oldActions = $this->objectsToArray($oldActions, "ActionID", "ActionID");
			} else{
				$oldActions = [];
			}
			$acts = [];
			foreach ($oldActions as $key => $value) {
				$acts[] = $key;
			}
			$oldActions = $acts;

			$addedActions = array_diff($newActions, $oldActions);
			$removedActions = array_diff($oldActions, $newActions );
			$untouchedActions = array_intersect($newActions, $oldActions); // Or updated

			foreach ($removedActions as $removedId) {
				ActionEngagee::where(DB::raw("BatimentID"), "=", $batiment->{strtolower('BatimentID')})->where(DB::raw("ActionID"), "=", $removedId)->delete();
			}

			foreach ($addedActions as $addedId) {
				
				$mesure = new ActionEngagee;

				$mesure->{strtolower('BatimentID')} = $batiment->{strtolower('BatimentID')};
				$mesure->{strtolower('ActionID')} = $addedId;
				$mesure->{strtolower('baseid')} = $baseid;
				$mesure->{strtolower('MouvrageID')} = static::getInsertionMouvrageID();

				$mesure->{strtolower('Date')} = \Carbon\Carbon::createFromFormat('d/m/Y', date("d/m/Y"));
				$mesure->save ();
			}
			
		}

		// Mettre à jour les associations compteur
		if (is_array ( \Request::input ( 'compteurEauxID' ) )) {
			
			$compteurbatiment_ids = array ();
			
			$compteur_arr = \Request::input ( 'compteurEauxID' );
			
			foreach ( $compteur_arr as $key => $compteur_id ) {
			
				$size = Compteurbatiments::where ( 'CompteurID', $compteur_id )->where ( 'BatimentID', $batiment->{strtolower('BatimentID')} )->count ();
			
				if ($size <= 0) {
					$compteurbatiment = new Compteurbatiments ();
					$compteurbatiment->{strtolower('BatimentID')} = $batiment->{strtolower('BatimentID')};
					$compteurbatiment->{strtolower('CompteurID')} = $compteur_id;
					$compteurbatiment->{strtolower('baseid')} = $baseid;
					$compteurbatiment->{strtolower('Pourcentage')} = 100;
					$compteurbatiment->save ();
				}

				$compteurbatiment_ids [] = $compteur_id;
			
			}
			
			$existing_compteurbatiments = DB::select ( "SELECT compteur.CompteurID, compteurbatiments.BatimentID FROM compteur inner join compteurbatiments on ( compteur.CompteurID=compteurbatiments.CompteurID) WHERE compteur.BaseID='$baseid' and compteur.Clos=0 and (compteur.Type='CONSOEAU') and compteurbatiments.BatimentID={$batiment->{strtolower('BatimentID')}} ORDER BY compteur.EnergieID, compteur.Nom, compteur.Reference" );
			foreach ( $existing_compteurbatiments as $compteurbatiment ) {
				if (! in_array ( $compteurbatiment->{strtolower('CompteurID')}, $compteurbatiment_ids )) {
					DB::table ( 'compteurbatiments' )->where ( 'CompteurID', $compteurbatiment->{strtolower('CompteurID')} )->where ( 'BatimentID', $compteurbatiment->{strtolower('BatimentID')} )->delete ();
				}
			}
		}
		
		if (is_array ( \Request::input ( 'compteurElectricitesID' ) )) {
			$compteurbatiment_ids = array ();
			$compteur_arr = \Request::input ( 'compteurElectricitesID' );
			foreach ( $compteur_arr as $key => $compteur_id ) {
				$size = Compteurbatiments::where ( 'CompteurID', $compteur_id )->where ( 'BatimentID', $batiment->{strtolower('BatimentID')} )->count ();
				if ($size <= 0) {
					$compteurbatiment = new Compteurbatiments ();
					$compteurbatiment->{strtolower('BatimentID')} = $batiment->{strtolower('BatimentID')};
					$compteurbatiment->{strtolower('CompteurID')} = $compteur_id;
					$compteurbatiment->{strtolower('baseid')} = $baseid;
					$compteurbatiment->{strtolower('Pourcentage')} = 100;
					$compteurbatiment->save ();
				}
				$compteurbatiment_ids [] = $compteur_id;
			}
			
			$existing_compteurbatiments = DB::select ( "SELECT compteur.CompteurID, compteurbatiments.BatimentID FROM compteur inner join compteurbatiments on ( compteur.CompteurID=compteurbatiments.CompteurID) WHERE compteur.BaseID='$baseid' and compteur.Clos=0 and (compteur.Type='CONSO') and compteurbatiments.BatimentID={$batiment->{strtolower('BatimentID')}} ORDER BY compteur.EnergieID, compteur.Nom, compteur.Reference" );
			foreach ( $existing_compteurbatiments as $compteurbatiment ) {
				if (! in_array ( $compteurbatiment->{strtolower('CompteurID')}, $compteurbatiment_ids )) {
					DB::table ( 'compteurbatiments' )->where ( 'CompteurID', $compteurbatiment->{strtolower('CompteurID')} )->where ( 'BatimentID', $compteurbatiment->{strtolower('BatimentID')} )->delete ();
				}
			}
		}
		
		$modifierUrl = URL::to ( 'tbge/patrimoine/batiment/' . $batiment->{strtolower('BatimentID')} . '/edit' );
		Session::flash ( 'batiment.success', "<p>Mise-à-jour du batiment effectué avec succès ! <a href='{$modifierUrl}' class='btn btn-success'>Modifier le bâtiment</a></p>" );
		return Redirect::to ( 'tbge/patrimoine/batiment' );
	}
	public function destroy($id) {
		$batiment = Batiments::find ( $id );
		
		$compteurbatiments = Compteurbatiments::where(DB::raw("BatimentID"), "=", $id)->get();
		foreach ($compteurbatiments as $cb) {
			$compteur = Compteurs::find($cb->{strtolower('CompteurID')});
			
			if(!$compteur or $compteur->{strtolower('CompteurID')} <= 0 )
				continue;

			// delete facture
			$factures = Factures::where(DB::raw("CompteurID"), "=", $compteur->{strtolower('CompteurID')});
			if($factures && count($factures) > 0)
				$factures->delete();
			// Delete compteur
			$compteur->delete();
		}
		if(count($compteurbatiments) > 0)
			Compteurbatiments::where(DB::raw("BatimentID"), "=", $id)->delete();
		
		// Delete actions engagees
		$actions = ActionEngagee::where(DB::raw("BatimentID"), "=", $batiment->{strtolower('BatimentID')});
		
		if($actions->count() > 0){
			$actions->delete();
		}
		
		$batiment->delete();
		
		// redirect
		Session::flash ( 'batiment.success', "Batiment supprimé avec succès !" );
		return Redirect::to ( 'tbge/patrimoine/batiment' );
	}

	public function importCsv() {

		return View::make ( $this->importViewName )
					->with ( 'importViewName', $this->importViewName )
					->with ( 'importedViewName', $this->importedViewName )
					->with ( 'importPostUrl', $this->importPostUrl )
					->with ( 'importFormUrl', $this->importFormUrl );
	}
	
	public function doImport() {
		return false; // No more supported/Needed		
	}

	public function buildExportSelectionSql() {
		
		$exportSelectionSql = <<<EOD
				_src.mouvrageid 				AS mouvrageid,
				_src.batimentid 				AS batimentid,
				_src.num_national 				AS numeronational, 
				_src.adresse					AS lieu, 
				_src.is_inclus_prog_mv 			AS estincludansleprogramme, 
				_src.date_inclusion_prog_mv 	AS dateinclusionauprogramme,
				_src.nom 						AS nom,
				_src.nom_alternatif 			AS nomalternatif,
				
				string_agg(DISTINCT concat("substring"(energie.nom::text, 1, 4), ':', COALESCE(_cpt.reference, _cpt.nom)), ', '::text) AS cil,

				_src.annee_construction			AS anneeconstruction,
				_src.nb_employe 				AS nbremployee,
				_src.nb_maison_associee 		AS nombremaisonsassociees,
				_src.has_ecole_coranique 		AS presenceecolecoranique,
				_src.categorie 					AS categorie,
				_src.surface 					AS surface,
				_src.capacite_accueil 			AS capaciteaccueil,
			    _src.surface_chauffee 			AS surfacechauffee,
			    _src.lib_label					AS label,
			    _src.date_labelisation 			AS datelabelisation,
			    _src.mos_nom 					AS mos_nom,
			    _src.mos_supprime 				AS mos_supprime,
			    _src.mos_sourcefin 				AS mos_sourcefin,
			    _src.mos_sourcefin_eau 			AS mos_sourcefin_eau,
			    _src.mos_sourcefin_electricite 	AS mos_sourcefin_electricite,
			    NULL 							AS mesuresee,
			    _src.lib_mouvrage				AS societe,
			    NULL 							AS contact,
			    _src.lib_mouvrage 				AS delegation,
			    _src.date_modification 			AS datedernieremodification
		EOD;

		return $exportSelectionSql;;
	}

	public function datatable($action = "datatable") {
		
		$communeId = static::getMyCommuneID();
				
		$libBatiment 	= NULL;		
		$listeId 		= Request::input ('pFilterListe', NULL) ;
		$isInProgMv 	= Request::input ('pFilterInclusProgrammeMosqueesVertes', NULL );
		$communeId 		= NULL;

		$sourceFin 				= Request::input ('pFilterGestionnaire', NULL);
		$sourceFinEau 			= Request::input ('pFilterSourceFinancementEau', NULL );
		$sourceFinElectricite 	= Request::input ('pFilterSourceFinancementElectricite', NULL);
		$pMixSourceFin 			= Request::input ('pFilterCombinaisonFinancement', NULL );
		
		$user = Auth::user();
		$delegationID = Request::input ('pFilterDelegation', NULL);
		$delegationID = empty($delegationID) ? NULL : $delegationID;

		$utilisateurId = NULL;
		$isMulticommunes = static::isMulticommunes();

		if(!$isMulticommunes){
			// Si aucune délégation n'est passée, utiliser la délégation de l'utilisateur
			if($delegationID == NULL || $delegationID == 0) {
				$delegationID = static::getMyCommuneID();
			}
			
			// Vérifier si l'utilisateur a des délégations assignées dans utilisateur_mouvrage
			$hasAssignedDelegations = DB::connection('tbge')->table('utilisateur_mouvrage')
				->where("utilisateurid", "=", $user->utilisateurid)
				->where("entiteid", "=", 1) // 1 = délégations
				->where("operationid", "=", 2) // 2 = voir (R)
				->exists();
			
			\Log::info('BatimentTbgeController::datatable - Checking utilisateur_mouvrage', [
				'user_id' => $user->utilisateurid,
				'user_mouvrageid' => $user->mouvrageid ?? 'NULL',
				'hasAssignedDelegations' => $hasAssignedDelegations ? 'true' : 'false',
				'delegationID_before' => $delegationID
			]);
			
			// Si l'utilisateur a des délégations assignées dans utilisateur_mouvrage,
			// passer p_mouvrageid = NULL pour que la fonction PostgreSQL utilise p_utilisateurid
			// pour déterminer les permissions depuis utilisateur_mouvrage
			// Sinon, passer le mouvrageid de l'utilisateur directement
			if($hasAssignedDelegations) {
				$delegationID = NULL; // La fonction utilisera p_utilisateurid pour déterminer les permissions
				// Toujours passer l'utilisateur ID pour le filtrage des permissions
				$utilisateurId = $user->utilisateurid;
			} else {
				// Si l'utilisateur n'a pas d'entrées dans utilisateur_mouvrage,
				// utiliser directement son mouvrageid (pas besoin de p_utilisateurid)
				// Garder $delegationID tel quel (mouvrageid de l'utilisateur)
				$utilisateurId = NULL; // Pas besoin de passer l'utilisateur ID si on utilise directement le mouvrageid
			}
		}
		
		\Log::info('BatimentTbgeController::datatable', [
			'user_id' => $user->utilisateurid,
			'user_mouvrageid' => $user->mouvrageid ?? 'NULL',
			'delegationID' => $delegationID,
			'utilisateurId' => $utilisateurId,
			'isMulticommunes' => $isMulticommunes,
			'hasAssignedDelegations' => isset($hasAssignedDelegations) ? ($hasAssignedDelegations ? 'true' : 'false') : 'N/A'
		]);

		/*
		Quand Active est sélectionnée : Y
		p_etat_batiment = 1 
		p_is_supprime = FALSE

		Quant Supprimée est sélectionnée : N
		p_etat_batiment = NULL
		p_is_supprime = TRUE

		C'est une combinaison, car on utilise "mos_supprime" qui vient du système SIM (Système d'Information des Mosquées)
		*/
		
		$etatBatiment = Request::input ('pFilterEtat', NULL);
		$isSupprime = false;
		
		if($etatBatiment == "N"){ // Active
			$etatBatiment = 1;
			$isSupprime = false;
		}
		if($etatBatiment == "Y"){ // Supprimee
			$etatBatiment = null;
			$isSupprime = true;
		}

		$search = \Request::input('search');
		$search = ($search != null && isset($search["value"])) ? $search["value"]:  NULL;
		    

		$columns = \Request::input('columns');
		$order = \Request::input('order');
		
		$orderBy = NULL;
		$internalOrderBy = NULL;
		
		$_orders = [];
		$_internalOrders = [];
		
		if($order != null && count($order) > 0){

			foreach ($order as $ordre) {
				$column 	= $ordre['column'];
		      	$direction 	= $ordre['dir'];
		      	
		      	if(!(is_numeric($column) && $column > 0)){
		      		continue;
		      	} 
		      	
		      	$column = $columns[$column]["data"];
		      	$_orders[] =  $column . " " . $direction;
				
				if($column == "cil")
					continue;
		      	
		      	$_internalOrders[] =  $column . " " . $direction;

			}
			
			$orderBy = implode(", ", $_orders);
			$internalOrderBy = implode(", ", $_internalOrders);
		
		}

		if(empty($orderBy)){
			$orderBy = "num_national DESC";
		}

		$length = \Request::input('length', 100);
		$start = \Request::input('start', 0);

		$forExport = $action == "export";
		// Export is done in one fell swope without pagination
		$dataLength 				= $forExport == TRUE ? 10000000 : $length;
		$dataIndex 					= $forExport == TRUE ? 0 : $start;
		
		$pageIndex 					= $dataIndex == 0 ? '0' : round($dataIndex/$length);

		$params = [
			"p_lib_batiment" 				=> [0,  "character varying", 	$libBatiment 			],
			"p_listeid" 					=> [1,  "integer", 				$listeId 				],
			"p_is_inclus_prog_mv" 			=> [2,  "smallint", 			$isInProgMv 			],
			"p_communeid" 					=> [3,  "integer", 				$communeId 				],
			"p_mos_sourcefin" 				=> [4,  "character", 			$sourceFin 				],
			"p_mos_sourcefin_eau" 			=> [5,  "character", 			$sourceFinEau 			],
			"p_mos_sourcefin_electricite" 	=> [6,  "character", 			$sourceFinElectricite 	],
			"p_mix_sourcefin" 				=> [7,  "character varying", 	$pMixSourceFin 			],
			"p_mouvrageid" 					=> [8,  "integer", 				$delegationID 			],
			"p_utilisateurid" 				=> [9,  "integer", 				$utilisateurId 			],
			"p_etat_batiment" 				=> [10, "integer", 				$etatBatiment 			],
			"p_is_supprime" 				=> [11, "boolean", 				$isSupprime 			],
			"p_lib_search" 					=> [12, "character varying", 	$search 				],
			"p_sorting_criteria" 			=> [13, "character varying", 	$internalOrderBy		],
			"p_page_index" 					=> [14, "integer", 				$pageIndex 				],
			"p_page_size" 					=> [15, "integer", 				$dataLength 			],
		];

		$selectionSql = <<<EOD
			_src.nb_total,
			_src.batimentid,
			_src.num_national,
			_src.nom,
			_src.mos_nom,
			
			string_agg(DISTINCT concat("substring"(energie.nom::text, 1, 4), ':', COALESCE(_cpt.reference, _cpt.nom)), ', '::text) AS cil,

			_src.nom_alternatif,
			_src.lib_mouvrage,
			_src.adresse
		EOD;

		$nb_totalColumn = "_src.nb_total,";

		if($forExport){
			$selectionSql = $this->buildExportSelectionSql();
			$nb_totalColumn = "";
		}


		$extraJoinsSql = <<<EOD
			LEFT JOIN sk_tbge.compteurbatiments ON _src.batimentid = compteurbatiments.batimentid
		 	LEFT JOIN sk_tbge.compteur _cpt ON _cpt.compteurid = compteurbatiments.compteurid
		 	LEFT JOIN sk_tbge.energie ON _cpt.energieid = energie.energieid
			
			GROUP BY 
				
				$nb_totalColumn
				
				_src.batimentid,
				_src.num_national,
				_src.nom,
				_src.mos_nom,
				_src.nom_alternatif,
				_src.lib_mouvrage,
				_src.adresse,

				_src.mouvrageid,
				_src.adresse,
				_src.is_inclus_prog_mv,
				_src.date_inclusion_prog_mv,
				_src.nom_alternatif,
				_src.annee_construction,
				_src.nb_employe,
				_src.nb_maison_associee,
				_src.has_ecole_coranique,
				_src.categorie,
				_src.surface,
				_src.capacite_accueil,
				_src.surface_chauffee,
				_src.lib_label,
				_src.date_labelisation,
				_src.mos_nom,
				_src.mos_supprime,
				_src.mos_sourcefin,
				_src.mos_sourcefin_eau,
				_src.mos_sourcefin_electricite,
				_src.date_modification

			ORDER BY $orderBy
		EOD;

		$functionName = PostgreSqlFunctionCaller::$PROCEDURE_NAME_BATIMENT_SEARCH;
		$caller = new PostgreSqlFunctionCaller($functionName, $params, $selectionSql, FALSE, FALSE, $extraJoinsSql);
		
		$result = $caller->call();
		
		if(empty($result) || count($result) == 0){
			$recordsFiltered = 0 ;
			$result = [];
		} else {
			$recordsFiltered = empty($nb_totalColumn) ? count($result) : $result[0]->nb_total;
		}
		
		$total = Batiments::count();
		$draw = \Request::input('draw');

		if($action == "datatable"){
		   	$datatable = (object)[
				'draw' 				=> $draw,
				'recordsTotal' 		=> $recordsFiltered,
				'recordsFiltered' 	=> $recordsFiltered,
				'data' 				=> $result,
				'error' 			=> null,
			]; //new DataTableResponse($draw, $total, $total_search, $list, null);
			
			return Response::json ( $datatable );
			
		} 

		if($action == "export"){  	
		  	$myDelegationId = Request::input('myDelegationId', $user->mouvrageid);
			$myDelegationName = Mouvrage::find($myDelegationId)->societe;
			$name = "ListeDesMosquees_{$myDelegationName}_" . date("Y-m-d H_i_s") . ".xlsx";
		  	return [$result, $name];
		}

		return $result; // imprimer
	}

	public function imprimer() {
		return $this->datatable("imprimer");
	}
	
	public function export() {
	  	$result = $this->datatable("export");
	  	$exporter = new MosqueesExport($result[0]);

	  	ob_end_clean(); 
        ob_start();
	  	return Excel::download($exporter, $result[1]);
	}

	public function objectsToArray($objs, $key, $val) {
		
		$arr = array ();
		
		foreach ( $objs as $obj ) {
			$arr [$obj->$key] = $obj->$val;
		}
		
		return $arr;
	
	}

}