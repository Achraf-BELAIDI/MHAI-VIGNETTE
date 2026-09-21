<?php

namespace App\Http\Controllers\Tbge;

use Auth;
use DB;
use Request;
use View;
use Response;
use URL;
use Session;
use Redirect;
use Validator;
use Str;

use App\Models\DataTableResponse;

use App\Models\Tbge\Compteurs;
use App\Models\Tbge\Compteurbatiments;
use App\Models\Tbge\Batiments;
use App\Services\Tbge\CompteurSrmService;

class CompteurTbgeController extends BaseController {
	
	public function __construct() {
	  parent::__construct ( [ 
	      "dataName" => "compteurs",
	      "importViewName" => "tbge.compteur.importcsv",
	      "importedViewName" => "tbge.compteur.importcsvimported",
	      "importFormUrl" => "tbge/compteur/import/csv",
	      "importPostUrl" => "tbge/compteur/import/csv/post",
	      "importColumns" => []
	  ] );
	  
	}

	public static $typeCompteurs = array (
			'CONSO' => "Consommation d'énergie",
			'CONSOEAU' => "Consommation d'eau",
			'CONSOLIEPROD' => "Consommation liée à un de vos postes de production",
			'MP' => "Consommation en matière première pour fabrication",
			'PROD' => "Production  d'énergie",
			'PRODEAU' => "Production d'eau chaude",
			'FABRICATION' => "Fabrication de produits manufacturés" 
	);
	
	public function viewCompteur($id) {
		
		$compteur = Compteurs::with([
			"compteurBatiments" => function ($bQuery) {
				$bQuery	->with(['batiment' => function ($bq) {
				$bq->select([
					
					DB::raw("BatimentID"), 
					DB::raw("Num_National"),
					DB::raw("Nom"), 

					DB::raw("BatimentID AS \"BatimentID\""), 
					DB::raw("Nom AS \"Nom\""), 
					DB::raw("Num_National AS \"NumeroNational\"")
				]);
				}])->select([
				DB::raw("BatimentID"), 
				DB::raw("CompteurID"),
				DB::raw("BatimentID AS \"BatimentID\""), 
				DB::raw("CompteurID AS \"CompteurID\"")
				]);
			}
		])
		->with ( 'energie' )
		->with ( 'fournisseur' )
		->find ( $id );
		
		return View::make ( 'tbge.tbge.compteur.view' )->with ( 'compteur', $compteur );
	}

	public function index() {
		
		$canEdit = $this->canEdit ();
		$canCreate = $this->canCreate ();
		$canDelete = $this->canDelete ();

		$BatimentID = Request::has("BatimentID") ? Request::input("BatimentID") : 0;
		
		return View::make ( 'tbge.tbge.compteur.index' )
					->with ( 'BatimentID', $BatimentID )
					->with ( "canEdit", $canEdit )
                    ->with ( "canCreate", $canCreate )
                    ->with ( "canDelete", $canDelete );
	}

	public function datatable() {
		
		$draw = Request::input ( 'draw' );
		$start = Request::input ( 'start', 0 );
		$length = Request::input ( 'length', 10 );
		$search = Request::input ( 'search' );
		$order = Request::input ( 'order' );
		$columns = Request::input ( 'columns' );
		
		$query = Compteurs::with([
				'Energie',
				'Fournisseur',
				'compteurBatiments' => function ($QCb) {
					$QCb->with("batiment");
				}
			])->has("compteurBatiments"); 

		$user = Auth::user ();
		$communeId = static::getMyCommuneID();
		
		if (!$user->role->isMulticommunes) {
			// Vérifier si l'utilisateur a des délégations assignées dans utilisateur_mouvrage
			$hasAssignedDelegations = DB::connection('tbge')->table('utilisateur_mouvrage')
				->where("utilisateurid", "=", $user->utilisateurid)
				->where("entiteid", "=", 1) // 1 = délégations
				->where("operationid", "=", 2) // 2 = voir (R)
				->exists();
			
			\Log::info('CompteurTbgeController::datatable - Checking utilisateur_mouvrage', [
				'user_id' => $user->utilisateurid,
				'communeId' => $communeId,
				'hasAssignedDelegations' => $hasAssignedDelegations ? 'true' : 'false'
			]);
			
			// Récupérer la délégation de l'utilisateur pour déterminer si c'est une DRAI ou provinciale
			$userDelegation = DB::connection('tbge')->table('mouvrage')
				->where("mouvrageid", "=", $communeId)
				->select([
					DB::raw("mouvrageid"),
					DB::raw("COALESCE(mouvrage_parent_id, 0) AS mouvrage_parent_id")
				])
				->first();
			
			if($hasAssignedDelegations) {
				// Si l'utilisateur a des délégations assignées, utiliser les délégations de utilisateur_mouvrage
				// Récupérer toutes les délégations assignées à l'utilisateur
				$assignedMouvrageIds = DB::connection('tbge')->table('utilisateur_mouvrage')
					->where("utilisateurid", "=", $user->utilisateurid)
					->where("entiteid", "=", 1)
					->where("operationid", "=", 2)
					->pluck('mouvrageid')
					->toArray();
				
				if(!empty($assignedMouvrageIds)) {
					$query->whereHas('compteurBatiments.batiment', function($q) use ($assignedMouvrageIds) {
						$q->whereIn('batiment.mouvrageid', $assignedMouvrageIds);
					});
				} else {
					// Fallback : filtrer par la délégation de l'utilisateur
					$query->whereHas('compteurBatiments.batiment', function($q) use ($communeId) {
						$q->where('batiment.mouvrageid', '=', $communeId);
					});
				}
			} else {
				// Si l'utilisateur n'a pas de délégations assignées, inclure aussi la DRAI parente
				if($userDelegation && $userDelegation->mouvrage_parent_id > 0) {
					// Agent provincial : inclure la DRAI parente + la provinciale
					$draiId = $userDelegation->mouvrage_parent_id;
					$query->whereHas('compteurBatiments.batiment', function($q) use ($communeId, $draiId) {
						$q->where(function($subQ) use ($communeId, $draiId) {
							$subQ->where('batiment.mouvrageid', '=', $communeId)
							     ->orWhere('batiment.mouvrageid', '=', $draiId);
						});
					});
				} else {
					// Agent régional (DRAI) : filtrer uniquement par sa délégation
					$query->whereHas('compteurBatiments.batiment', function($q) use ($communeId) {
						$q->where('batiment.mouvrageid', '=', $communeId);
					});
				}
			}
		}

		if (Request::has("BatimentID") and Request::input("BatimentID") > 0) {
		  $bId = Request::input("BatimentID");
		  $query->whereHas('compteurBatiments', function ($cbF) use ($bId) {
		  	$cbF->where(DB::raw("batimentid"), "=", $bId);
		  });
		}

		$total = $query->count ();
		if (!empty($search) && isset($search["value"]) && $search ['value'] != '') {
			$query->where ( DB::raw ( 'LOWER(Reference)' ), 'ILIKE', Str::lower ( '%' . trim ( $search ['value'] ) . '%' ) );
			$query->orwhere ( DB::raw ( 'LOWER(Numero)' ), 'ILIKE', Str::lower ( '%' . trim ( $search ['value'] ) . '%' ) );
		}
		$total_search = $query->count ();
		if (! is_null ( $start ) && ! is_null ( $length )) {
			$query = $query->skip ( $start )->take ( $length );
		}
		if (is_array ( $order ) && count ( $order ) > 0) {
			for($i = 0, $c = count ( $order ); $i < $c; $i ++) {
				$order_col = ( int ) $order [$i] ['column'];
				if (isset ( $columns [$order_col] )) {
					if($columns [$order_col] ['name'] == "Mosque"){
						//$query->orderBy ( "compteur_batiments.batiment.Nom", $order [$i] ['dir'] );
					} else if ($columns [$order_col] ['orderable'] == "true") {
						$query->orderBy ( DB::raw($columns [$order_col] ['name']), $order [$i] ['dir'] );
					}
				}
			}
		}
		$list = $query->get ();
		
		$datatable = new DataTableResponse ( $draw, $total, $total_search, $list, null );
		return Response::json ( $datatable );
	}
	public function select2() {
		
		$user = Auth::user ();
		$communeId = static::getMyCommuneID();
		
		$page = Request::input ( 'page', 0 );
		$length = Request::input ( 'length', 10 );
		$search = Request::input ( 'q' );
		$order = Request::input ( 'order', DB::raw('compteur.Reference') );

		if($order == "Mosque")
			$order = "NomMosquee";


		$query2 = DB::table("compteur")
					  ->select([
					  		DB::raw("compteur.CompteurID AS id"),
					  		DB::raw("compteur.CompteurID"),
					  		DB::raw("compteur.Reference AS contratOrPolice"),
					  		DB::raw("compteur.Numero AS numeroCompteur"),
					  		
					  		DB::raw("compteur.TypeTarificationID"),
					  		DB::raw("compteur.TypeFactureID"),

					  		DB::raw("batiment.Nom AS NomMosquee"),
					  		DB::raw("batiment.Nom_Alternatif"),
					  		DB::raw("batiment.Num_National"),
					  		DB::raw("energie.Nom AS NomEnergie"),
					  		DB::raw("energie.TauxTVA")
					  	])
					  ->leftJoin("compteurbatiments", DB::raw("compteurbatiments.CompteurID"),"=", DB::raw("compteur.CompteurID"))
					  ->leftJoin("batiment", DB::raw("batiment.BatimentID"), "=", DB::raw("compteurbatiments.BatimentID"))
					  ->leftJoin("energie", DB::raw("energie.EnergieID"), "=", DB::raw("compteur.EnergieID"));
		
		if (!$user->role->isMulticommunes) {
			// Vérifier si l'utilisateur a des délégations assignées dans utilisateur_mouvrage
			$hasAssignedDelegations = DB::connection('tbge')->table('utilisateur_mouvrage')
				->where("utilisateurid", "=", $user->utilisateurid)
				->where("entiteid", "=", 1) // 1 = délégations
				->where("operationid", "=", 2) // 2 = voir (R)
				->exists();
			
			// Récupérer la délégation de l'utilisateur pour déterminer si c'est une DRAI ou provinciale
			$userDelegation = DB::connection('tbge')->table('mouvrage')
				->where("mouvrageid", "=", $communeId)
				->select([
					DB::raw("mouvrageid"),
					DB::raw("COALESCE(mouvrage_parent_id, 0) AS mouvrage_parent_id")
				])
				->first();
			
			if($hasAssignedDelegations) {
				// Si l'utilisateur a des délégations assignées, utiliser les délégations de utilisateur_mouvrage
				// Récupérer toutes les délégations assignées à l'utilisateur
				$assignedMouvrageIds = DB::connection('tbge')->table('utilisateur_mouvrage')
					->where("utilisateurid", "=", $user->utilisateurid)
					->where("entiteid", "=", 1)
					->where("operationid", "=", 2)
					->pluck('mouvrageid')
					->toArray();
				
				if(!empty($assignedMouvrageIds)) {
					$query2->whereIn(DB::raw("batiment.mouvrageid"), $assignedMouvrageIds);
				} else {
					// Fallback : filtrer par la délégation de l'utilisateur
					$query2->where(DB::raw("batiment.mouvrageid"), "=", $communeId);
				}
			} else {
				// Si l'utilisateur n'a pas de délégations assignées, inclure aussi la DRAI parente
				if($userDelegation && $userDelegation->mouvrage_parent_id > 0) {
					// Agent provincial : inclure la DRAI parente + la provinciale
					$draiId = $userDelegation->mouvrage_parent_id;
					$query2->where(function($q) use ($communeId, $draiId) {
						$q->where(DB::raw("batiment.mouvrageid"), "=", $communeId)
						  ->orWhere(DB::raw("batiment.mouvrageid"), "=", $draiId);
					});
				} else {
					// Agent régional (DRAI) : filtrer uniquement par sa délégation
					$query2->where(DB::raw("batiment.mouvrageid"), "=", $communeId);
				}
			}
		}

		$query2->groupBy(DB::raw("compteur.CompteurID"));
		$query2->groupBy(DB::raw("compteur.Reference"));
		$query2->groupBy(DB::raw("compteur.Numero"));

		$query2->groupBy(DB::raw("compteur.TypeTarificationID"));
		$query2->groupBy(DB::raw("compteur.TypeFactureID"));
		
		$query2->groupBy(DB::raw("batiment.Nom"));
		$query2->groupBy(DB::raw("batiment.Nom_Alternatif"));
		$query2->groupBy(DB::raw("batiment.Num_National"));
		
		$query2->groupBy(DB::raw("energie.Nom"));
		$query2->groupBy(DB::raw("energie.TauxTVA"));

		$total = $query2->count();

		if(is_array($search) && isset($search["term"]) && is_string($search["term"])) {
			$search = trim(strtolower($search['term']));
		}

		$search =  "'%".$search."%'";

		$query2->where(function ($q) use ($search) {
			$q->where(DB::raw("compteur.Reference"), "ILIKE", $search)
			  ->orWhere(DB::raw("compteur.Numero"), "ILIKE", $search)
			  ->orWhere(DB::raw("batiment.Nom"), "ILIKE", $search)
			  ->orWhere(DB::raw("batiment.Nom_Alternatif"), "ILIKE", $search)
			  ->orWhere(DB::raw("batiment.Num_National"), "ILIKE", $search);
		});

		$query2->whereNotNull(DB::raw("batiment.BatimentID"));
		$query2->whereNotNull(DB::raw("compteur.CompteurID"));

		$total_search = $query2->count();

		if (! is_null ( $page ) && ! is_null ( $length )) {
			$start = ( int ) (($page - 1) * $length);
			$query2 = $query2->skip ( $start )->take ( $length );
		}
		
		$query2->orderBy ( $order, 'ASC' );
		
		$rawQuery = Str::replaceArray('?', $query2->getBindings(), $query2->toSql());
		$list = DB::select($rawQuery); 
		//$list = $query2->get();
		
		$datatable = new DataTableResponse ( 1, $total, $total_search, $list, null );
		
		return Response::json ( $datatable );
	}
	public function create() {
		
		$energies = DB::select ( "select EnergieID AS \"EnergieID\", Nom AS \"Nom\" from energie order by Nom" );
		$energies = $this->objectsToArray ( $energies, 'EnergieID', 'Nom' );
		
		$fournisseurs = DB::select ( "select fournisseurid AS \"CoordonneeID\", Nom AS \"Societe\" from fournisseur order by Nom" );
		$fournisseurs = $this->objectsToArray ( $fournisseurs, 'CoordonneeID', 'Societe' );
		
		$selectedBatiment = Request::has("BatimentID") ? Request::input("BatimentID") : NULL;
		$selectedBatimentName = Request::has("BatimentID") ? Batiments::find(Request::input("BatimentID"))->nom : NULL;
		$selectedEnergie = Request::has("EnergieID") ? Request::input("EnergieID") : NULL;

		$batiments = [];
		
		$view = View::make ( 'tbge.tbge.compteur.create' )
					->with ( 'energies', $energies )
					->with ( 'fournisseurs', $fournisseurs )
					//->with ( 'compteurexistants', $compteurexistants )
					->with ( 'batiments', $batiments )
					->with ( 'typeCompteurs', self::$typeCompteurs );
		
		
		$view->with('selectedBatiment', $selectedBatiment);
		$view->with('selectedBatimentName', $selectedBatimentName);
		$view->with('selectedEnergie', $selectedEnergie);
		
		return $view;
	}
	public function store() {
		$fournisseurID = (int) Request::input('FournisseurID');
		$isSrm = $this->isSrmFournisseurId($fournisseurID);
		$idLieuSaisi = trim((string) Request::input('Reference'));
		$contratSrm = trim((string) Request::input('ContratSrm', ''));

		// Référence en base (= n° facture) : SRM → ContratSrm, sinon → ID Lieu
		$referenceCourante = $isSrm ? $contratSrm : $idLieuSaisi;
		$referenceAncienne = ($isSrm && $idLieuSaisi !== '' && strcasecmp($idLieuSaisi, $contratSrm) !== 0)
			? $idLieuSaisi
			: null;

		$rules = [
			'Numero' => 'required',
			'FournisseurID' => 'required',
			'EnergieID' => 'required',
			'OldBatimentID' => 'required',
		];
		$messages = [
			'Numero.required' => "Le numéro de compteur est obligatoire !",
			'FournisseurID.required' => "Veuillez préciser le fournisseur du compteur!",
			'EnergieID.required' => "Le type d'énergie est obligatoire !",
			'OldBatimentID.required' => "Le patrimoine est obligatoire !",
		];

		if ($isSrm) {
			$rules['ContratSrm'] = 'required|max:50';
			$messages['ContratSrm.required'] = "Le n° de contrat SRM est obligatoire.";
		} else {
			$rules['Reference'] = 'required|max:50';
			$messages['Reference.required'] = "L'ID Lieu (n° contrat / police) est obligatoire.";
		}

		$validation = Validator::make(Request::all(), $rules, $messages);

		if ($validation->fails()) {
			return Redirect::to('tbge/compteur/create')->withErrors($validation)->withInput(Request::all());
		}

		if ($referenceCourante === '') {
			return Redirect::to('tbge/compteur/create')
				->withErrors([$isSrm ? "Le n° de contrat SRM est obligatoire." : "L'ID Lieu est obligatoire."])
				->withInput(Request::all());
		}

		$isUpdate = (Request::has("CompteurID") and Request::input("CompteurID") > 0);
		$patrimoine = Request::input('pFilterMosquee', NULL);

		if ($patrimoine == NULL) {
			$patrimoine = Request::input('OldBatimentID', NULL);
		}

		if ($patrimoine <= 0 || $patrimoine == NULL) {
			return Redirect::to('tbge/compteur/create')->withErrors(["Veuillez préciser la mosquée!"])->withInput(Request::all());
		}

		$patrimoineId = $patrimoine;
		$numeroCompteur = trim((string) Request::input('Numero'));
		$energieID = Request::input('EnergieID');
		$typeFacturation = Request::input('TypeFacturationID');
		$typeTarif = Request::input('TypeTarificationID');

		$isMulticommunes = static::isMulticommunes();

		if (!$isMulticommunes) {
			$delegationID = static::getMyCommuneID();
		} else {
			$delegationID = Request::has('DelegationID') ? Request::input('DelegationID') : -1;

			if ($delegationID == -1) {
				$delegationID = Batiments::find($patrimoineId)->mouvrageid;
			}
		}

		$etatCompteur = 1 * Request::input("etat_compteur", '1');

		$previousFournisseurId = null;
		if ($isUpdate) {
			$existing = Compteurs::find((int) Request::input('CompteurID'));
			if ($existing) {
				$previousFournisseurId = (int) ($existing->fournisseurid ?? 0);
			}
		}

		if ($isUpdate) {
			$compteurID = Request::input("CompteurID");
			DB::statement("CALL sk_tbge.compteur_update(?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)", [$compteurID, $referenceCourante, $numeroCompteur, $energieID, $fournisseurID, $delegationID, $typeFacturation, $typeTarif, $patrimoineId, 100, true, $etatCompteur]);
			if ($isSrm) {
				$this->applyLegacyMigrationFields((int) $compteurID, $referenceAncienne, $previousFournisseurId);
			}
			Session::flash('success', "<p>Mise à jour du compteur effectuée avec succès.</p>");
		} else {
			DB::statement("CALL sk_tbge.compteur_insert(?, ?, ?, ?, ?, ?, ?, ?, ?, ?)", [$referenceCourante, $numeroCompteur, $energieID, $fournisseurID, $delegationID, $typeFacturation, $typeTarif, $patrimoineId, 100, $etatCompteur]);
			if ($isSrm && $referenceAncienne !== null) {
				$created = Compteurs::where('reference', $referenceCourante)
					->where('numero', $numeroCompteur)
					->where('energieid', $energieID)
					->where('fournisseurid', $fournisseurID)
					->orderBy('compteurid', 'desc')
					->first();
				if ($created) {
					$this->applyLegacyMigrationFields((int) $created->compteurid, $referenceAncienne, null);
				}
			}
			Session::flash('success', "<p>Création du compteur effectuée avec succès.</p>");
		}

		return Redirect::to('/compteurs?BatimentID=' . $patrimoineId);
	}

	private function isSrmFournisseurId(int $fournisseurId): bool {
		return app(CompteurSrmService::class)->isSrmFournisseurId($fournisseurId);
	}

	private function applyLegacyMigrationFields(int $compteurId, ?string $ancienne, ?int $previousFournisseurId): void {
		if ($compteurId <= 0) {
			return;
		}
		$service = app(CompteurSrmService::class);
		if (!$service->hasLegacyColumn()) {
			return;
		}
		$compteur = Compteurs::find($compteurId);
		if ($compteur == null) {
			return;
		}
		$service->applyLegacyFields($compteur, $ancienne, $previousFournisseurId);
	}
	public function edit($id) {
		
		$communeId = static::getMyCommuneID();

		$user = Auth::user();

		$communeFilter = "1=1";
		if (!$user->role->isMulticommunes) {
		  $communeFilter .= " AND {{placeholder}}.MouvrageID = $communeId";
		}

		$filter = str_replace("{{placeholder}}", "batiment", $communeFilter);
		
		$batiments = DB::select ( "SELECT BatimentID AS \"BatimentID\", Nom AS \"Nom\" FROM batiment WHERE $filter ORDER BY Nom" );

		$energies = DB::select ( "SELECT EnergieID AS \"EnergieID\", Nom AS \"Nom\" FROM energie ORDER BY Nom" );
		$energies = $this->objectsToArray ( $energies, 'EnergieID', 'Nom' );
		
		$fournisseurs = DB::select ( "SELECT fournisseurid AS \"CoordonneeID\", Nom AS \"Societe\" FROM fournisseur ORDER BY Nom" );
		$fournisseurs = $this->objectsToArray ( $fournisseurs, 'CoordonneeID', 'Societe' );
		
		$filter = str_replace("{{placeholder}}", "compteur", $communeFilter);
		
		$compteurRequest = <<<EOD
				
				SELECT 
					compteur.CompteurID AS "CompteurID",  
					energie.Nom || compteur.Reference || compteur.Localisation as compteur 
				FROM compteur 
				LEFT JOIN energie  ON compteur.EnergieID = energie.EnergieID 
				
				WHERE $filter 

				ORDER BY compteur.Nom
		EOD;
		
		$compteurexistants = DB::select ( $compteurRequest );
		$compteurexistants = $this->objectsToArray ( $compteurexistants, 'CompteurID', 'compteur' );
		
		$compteur = Compteurs::with([
				"compteurBatiments" => function ($bQuery) {
					$bQuery	->with(['batiment' => function ($bq) {
						$bq->select([
							
							DB::raw("BatimentID"), 
							DB::raw("Num_National"),
							DB::raw("Nom"), 

							DB::raw("BatimentID AS \"BatimentID\""), 
							DB::raw("Nom AS \"Nom\""), 
							DB::raw("Num_National AS \"NumeroNational\"")
						]);
					}])->select([
						DB::raw("BatimentID"), 
						DB::raw("CompteurID"),
						DB::raw("BatimentID AS \"BatimentID\""), 
						DB::raw("CompteurID AS \"CompteurID\"")
					]);
				}
		])->find ( $id );
		
		return View::make ( 'tbge.tbge.compteur.create' )
					//->with ( 'batiments', $batiments )
					->with ( 'compteur', $compteur )
					->with ( 'energies', $energies )
					->with ( 'fournisseurs', $fournisseurs )
					->with ( 'compteurexistants', $compteurexistants )
					//->with ( 'compteurprods', $compteurprods )
					->with ( 'typeCompteurs', self::$typeCompteurs );
	}
	public function update($id) {
		$validation = Validator::make ( Request::all (), array (
				'Reference' => 'required',
				'Type' => 'required',
				'EnergieID' => 'required' 
		), array (
				'Reference.required' => "La référence du compteur est obligatoire !",
				'Type.required' => "Le type est obligatoire !",
				'EnergieID.required' => "Le type d'énergie est obligatoire !" 
		) );
		
		if ($validation->fails ()) {
			return Redirect::to ( 'tbge/compteur/' . $id . '/edit' )->withErrors ( $validation )->withInput ( Request::all () );
		} else {
			$compteur = Compteurs::find ( $id );
			
			$compteur->mouvrageid = static::getInsertionMouvrageID(); // Config::get ( 'enertrack.MouvrageID' );

			$compteur->reference = Request::input ( 'Reference' );
			$compteur->numero = Request::input ( 'Numero' );
			$compteur->energieid = Request::input ( 'EnergieID' );
			$compteur->type = $compteur->EnergieID == 5 ? 'CONSOEAU' : 'CONSO';
			//$compteur->Type = Request::input ( 'Type' );
			$compteur->fournisseurid = Request::input ( 'FournisseurID' );
			$compteur->typefactureid = Request::input ( 'TypeFactureID' );
			$compteur->seuil = Request::input ( 'Seuil' );
			$compteur->objectif = Request::input ( 'Objectif' );
			$compteur->clos = 0;
			
			$compteur->etat_compteur = $etatCompteur; 
			
			$compteur->save ();
			
			$modifierUrl = URL::to ( 'tbge/compteur/' . $compteur->compteurid . '/edit' );
			Session::flash ( 'success', "<p>Mise-à-jour du compteur effectuée avec succès ! <a href='{$modifierUrl}' class='btn btn-success'>Modifier le compteur</a></p>" );
			return Redirect::to ( 'tbge/compteur' );
		}
	}
	public function destroy($id) {
		$compteur = Compteurs::find ( $id );
		$compteur->delete ();
		$cbr = Compteurbatiments::where(DB::raw("CompteurID"), $id)->delete();
		// redirect
		Session::flash ( 'success', "Compteur supprimé avec succès ! $cbr" );
		return Redirect::to ( 'tbge/compteur' );
	}
	
	public function importCsv() {
		return View::make ( 'tbge.tbge.compteur.importcsv' );
	}

	public function importCsvPosted() {
		Session::flash ( 'error', "<p>Fonction obsolète</p>" );
		return Redirect::to ( '/tbge/compteur' );
	}
	
	public function doImport() {
		Session::flash ( 'error', "<p>Fonction obsolète</p>" );
		return Redirect::to ( '/tbge/compteur' );
	}
	
	private function preserveLegacyReferenceAfterUpdate($previousCompteur, $compteurId, $newReference) {
		if ($previousCompteur == null) {
			return;
		}

		$service = app(CompteurSrmService::class);
		if (!$service->hasLegacyColumn()) {
			return;
		}

		$oldReference = trim((string) ($previousCompteur->reference ?? ''));
		$newReference = trim((string) $newReference);
		$legacyReference = trim((string) ($previousCompteur->reference_ancienne ?? ''));

		if ($legacyReference !== '' || $oldReference === '' || strcasecmp($oldReference, $newReference) === 0) {
			return;
		}

		Compteurs::where('compteurid', $compteurId)->update([
			'reference_ancienne' => $oldReference,
		]);
	}

	private function objectsToArray($objs, $key, $val) {
		$arr = array ();
		foreach ( $objs as $obj ) {
			$arr [$obj->$key] = $obj->$val;
		}
		return $arr;
	}

}