<?php

namespace App\Http\Controllers\Tbge;

use Auth;
use Request;
use DB;
use View;
use Validator;
use Response;
use URL;
use Session;
use Redirect;
use Config;
use Log;
use Str;

use App\Models\Tbge\Action;
use App\Models\Tbge\Commune;
use App\Models\Tbge\Region;
use App\Models\Tbge\Liste;

use App\Models\DataTableResponse;

use App\Models\Tbge\Factures;
use App\Models\Tbge\Batiments;
use App\Models\Tbge\Compteurs;
use App\Models\Tbge\Compteurbatiments;
use App\Models\Tbge\Mos;
use App\Models\Tbge\Contacts;
use App\Models\Tbge\Energies;

use Excel;
use App\Metadata\Excel\FacturesExport;

use App\Services\PostgreSqlFunctionCaller;

class FactureTbgeController extends BaseController {

    public function __construct() {
      parent::__construct ( [ 
          "dataName" => "factures",
          "importViewName" => "tbge.tbge.facture.importcsv",
          "importedViewName" => "tbge.tbge.facture.importcsvimported",
          "importFormUrl" => "tbge/factures/import/csv",
          "importPostUrl" => "tbge/factures/import/csv/post",
          "importColumns" => [
            
            "Ignore" => [
              "display" => "Ignorer",
              "mysqlColumn" => "",
              "mysqlRank" => -1,
              "help" => "",
              "required" => false
            ],
            "Numero" => [
              "display" => "Numéro de facture",
              "mysqlColumn" => "Nom",
              "mysqlRank" => -1,
              "help" => "",
              "required" => true
            ],
            "EnergieID" => [
              "display" => "Type de Consommation",
              "mysqlColumn" => "EnergieID",
              "mysqlRank" => -1,
              "help" => "",
              "required" => true
            ],
            "Reference" => [
              "display" => "CIL",
              "mysqlColumn" => "Reference",
              "mysqlRank" => -1,
              "help" => "",
              "required" => true
            ],
            "Numero du compteur" => [
              "display" => "Numéro",
              "mysqlColumn" => "Numero",
              "mysqlRank" => -1,
              "help" => "",
              "required" => true
            ],
            "Fournisseur" => [
              "display" => "FournisseurID",
              "mysqlColumn" => "FournisseurCompteur",
              "mysqlRank" => -1,
              "help" => "",
              "required" => true
            ],
            "Debutperiode" => [
              "display" => "Date: Début Consommation",
              "mysqlColumn" => "Debutperiode",
              "mysqlRank" => -1,
              "help" => "",
              "required" => true
            ],
            "Finperiode" => [
              "display" => "Date: Fin Consommation",
              "mysqlColumn" => "Finperiode",
              "mysqlRank" => -1,
              "help" => "",
              "required" => true
            ],
            "Consommation" => [
              "display" => "Consommation",
              "mysqlColumn" => "Consommation",
              "mysqlRank" => -1,
              "help" => "",
              "required" => true
            ],
            "Totalht" => [
              "display" => "Coût HT",
              "mysqlColumn" => "Totalht",
              "mysqlRank" => -1,
              "help" => "",
              "required" => true
            ],
            "Totalttc" => [
              "display" => "Coût TTC",
              "mysqlColumn" => "Totalttc",
              "mysqlRank" => -1,
              "help" => "",
              "required" => true
            ],
            "Remarques" => [
              "display" => "Remarques",
              "mysqlColumn" => "Remarques",
              "mysqlRank" => -1,
              "help" => "",
              "required" => false
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

    public function index() {
      $baseid = Auth::user()->BaseID; 

      $canEdit = $this->canEdit ();
      $canCreate = $this->canCreate ();
      $canDelete = $this->canDelete ();

      $fournisseurs = DB::select ( "select fournisseurid AS CoordonneeID,
                                    Nom
                                    FROM fournisseur
                                    order by CoordonneeID, Nom" );
      $listes = Liste::select([
          DB::raw("ListeID"),
          DB::raw("Nom")
      ])->get();
      
      $delegations = MoController::delegationDropdownList();

      return  View::make('tbge.tbge.facture.index')
                    ->with ( 'fournisseurs', $fournisseurs )
                    ->with ( 'delegations', $delegations )
                    ->with ( 'listes', $listes )
                    ->with ( "canEdit", $canEdit )
                    ->with ( "canCreate", $canCreate )
                    ->with ( "canDelete", $canDelete );
    }

    // Datatable search
    public function search($draw, $start, $length, $search, $order, $request, $forExport = FALSE) {

      $numFacture     = NULL; //$request[""];     //NULL;
      $energieID      = $request["energieid"];    //NULL;
      $fournisseurID  = $request["fournisseurid"];
      $delegationID   = $request["mouvrageid"];       //NULL;
      
      $user = Auth::user();
      $isMulticommunes = static::isMulticommunes();
      $userID = NULL;
      
      if(!$isMulticommunes){
      	// Vérifier si l'utilisateur a des délégations assignées dans utilisateur_mouvrage
      	$hasAssignedDelegations = DB::connection('tbge')->table('utilisateur_mouvrage')
      		->where("utilisateurid", "=", $user->utilisateurid)
      		->exists();
      	
      	// Si aucune délégation n'est passée, utiliser la délégation de l'utilisateur
      	if($delegationID == NULL || $delegationID == 0) {
      		$delegationID = static::getMyCommuneID();
      	}
      	
      	// Si l'utilisateur n'a pas de délégations assignées, passer p_mouvrageid à NULL
      	// pour permettre à la procédure de retourner les données de sa délégation de base
      	if(!$hasAssignedDelegations) {
      		$delegationID = NULL;
      	}
      	
      	// Toujours passer l'utilisateur ID pour le filtrage des permissions
      	$userID = $user->utilisateurid;
      } 
      
      $etatFacture    = $request["etat_facture"];
      
      // Export is done in one fell swope without pagination
      $dataLength     = $forExport == TRUE ? 10000000 : $length;
      
      $dataIndex      = $forExport == TRUE ? 0 : $start;
      
      $dataOrder      = NULL;
      $criteria       = NULL;

      if($order != NULL && count($order) > 0 ){
        foreach ($order as $ordre) {
          if(!isset($ordre["column"]) || empty($ordre["column"]))
            continue;
          $orderByColumn = $ordre["column"];
          $orderByDirection = isset($ordre["dir"]) ? $ordre["dir"] : "ASC";
          $newOrderBy = ($orderByColumn ." ". $orderByDirection);
          $criteria = $criteria == NULL ? $newOrderBy : $criteria . ", " . $newOrderBy; 
        }
      }

      $dataOrder = $criteria;
      if(empty($dataOrder)){
        $dataOrder = "debutperiode DESC";
      }
      
      $pageIndex      = $dataIndex == 0 ? '0' : round($dataIndex/$length);

      $mosqueeID      = $request["batimentid"];         //NULL; p_batimentid integer
      $listeID        = $request["listeid"];          //NULL; p_listeid integer
      $estInclusProg  = $request["estincludansleprogramme"];  //NULL; p_is_inclus_prog_mv smallint

      $trimestreFacturation   = $request['pFilterTrimestre'];
      $anneeFacturation     = $request['pFilterAnnee'];

      $params = [
        
        "p_num_facture"                 => [0, 'varchar'  , NULL],
        "p_compteurid"                  => [1, 'integer'  , NULL],
        
        "p_energieid"                   => [2, 'integer'  , $energieID],
        "p_fournisseurid"               => [3, 'integer'  , $fournisseurID],
        
        "p_batimentid"                  => [4, 'integer'  , $mosqueeID],
        
        "p_listeid"                     => [5, 'integer'  , $listeID],
        "p_is_inclus_prog_mv"           => [6, 'smallint' , $estInclusProg],
        
        "p_trimestrefacturation"        => [7, 'smallint' , $trimestreFacturation],         
        "p_anneefacturationtrimestre"   => [8, 'smallint' , $anneeFacturation],       
        
        "p_mouvrageid"                  => [9, 'integer'  , $delegationID],
        "p_utilisateurid"               => [10, 'integer' , $userID],
        "p_etat_facture"                => [11, 'integer' , $etatFacture],
        
        "p_lib_search"                  => [12, 'varchar' , $search],
        "p_sorting_criteria"            => [13, 'varchar' , $dataOrder],
        "p_page_index"                  => [14, 'integer' , $pageIndex],
        "p_page_size"                   => [15, 'integer' , $dataLength]
      ];
      $functionName = PostgreSqlFunctionCaller::$PROCEDURE_NAME_FACTURE_SEARCH;
      $selection = [
        "_src.*",
        "_src.total_restant_du AS montant_payable"
      ];

      $extraJoinSql = "";

      $caller = new PostgreSqlFunctionCaller($functionName, $params, implode(", ", $selection), FALSE, FALSE, $extraJoinSql);
      $result = $caller->call();

      // Export CSV streamé (évite le crash mémoire PhpSpreadsheet / 128 Mo)
      if($forExport == TRUE){
        $filename = 'ListeDesFactures_' . date('Y-m-d_Hi') . '.csv';
        $rows = $result ?: [];

        return response()->streamDownload(function () use ($rows) {
          $out = fopen('php://output', 'w');
          // BOM UTF-8 pour ouverture correcte dans Excel
          fwrite($out, "\xEF\xBB\xBF");
          fputcsv($out, array_values(FacturesExport::COLUMNS_MAPPING), ';');
          foreach ($rows as $facture) {
            fputcsv($out, FacturesExport::rowFromFacture($facture), ';');
          }
          fclose($out);
        }, $filename, [
          'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
      }

      if(empty($result) || count($result) == 0){
        $recordsFiltered = 0 ;
        $result = [];
      } else {
        $recordsFiltered = $result[0]->nb_total;
      }

      $recordsTotal = $recordsFiltered; // Factures::count(); // $this->mainModelClass::count();

      $datatable = (object)[
        'draw'            => $draw,
        'recordsTotal'    => $recordsTotal,
        'recordsFiltered' => $recordsFiltered,
        'data'            => $result,
        'error'           => null,
      ];
      
      return response()->json($datatable);

    }

    public function datatable($action="datatable"){
      
      $draw = \Request::input('draw');
      $start = \Request::input('start', 0);
      $length = \Request::input('length', 100);
      $order = \Request::input('order');
      $columns = \Request::input('columns');

      $search = \Request::input('search');
      
      if($search != null && isset($search["value"])){
          $search = $search["value"];
      } else
          $search = NULL;
          
      if($order != null && count($order) > 0){
         
         $order = array_map(function ($ordre) use ($columns) {
            
            $column = $ordre['column'];
            $direction = $ordre['dir'];
            
            if(is_numeric($column)){
               $column = $columns[$column]["data"];
            }

            // Return extracted name
            return [
               "column" => $column,
               "dir"    => $direction
            ];

         }, $order) ;
      }

      $requestData = [
        "mouvrageid"              => request()->input('pFilterDelegation'),
        "listeid"                 => request()->input('pFilterListe'),
        "batimentid"              => request()->input('pFilterMosquee'),
        "estincludansleprogramme" => request()->input("pFilterInclusProgrammeMosqueesVertes"),
        "energieid"               => request()->input('pFilterEnergie'),
        "pFilterTrimestre"        => request()->input('pFilterTrimestre'),
        "pFilterAnnee"            => request()->input('pFilterAnnee'),
        "fournisseurid"           => request()->input('pFilterFournisseur'),
        "etat_facture"            => request()->input('pFilterEtat'),
      ];

      $forExport = $action == "export";

      $result = $this->search($draw, $start, $length, $search, $order, $requestData, $forExport);
      return $result;

    }

    public function create() {
      return View::make('tbge.tbge.facture.create');
    }

    public function store(){
      
      $baseid = "8e0910e0-cdee-70a1-55c3-b0f48ee8127f"; // Auth::user()->baseid;

      $validation = Validator::make(Request::all(), 
        
        array(
          'pFilterCompteur' => 'required',
          'Debutperiode' => 'required|date_format:"d/m/Y"',
          'Finperiode' => 'required|date_format:"d/m/Y"|after:' . \Carbon\Carbon::createFromFormat('d/m/Y', Request::input('Debutperiode'))->subDay()->toDateString(),
          'Totalttc' => 'required|numeric',
          'Consommation' => 'required|numeric'
          ), 
        
        array(
          'pFilterCompteur.required' => "Merci de sélectionner le compteur associé à la facture",
          'Debutperiode.required' => "Merci de remplir le champ Du (date de début)",
          'Debutperiode.date_format' => "La date de début n'est pas une date valide au format (DD/MM/YYYY)",
          'Finperiode.required' => "Merci de remplir le champ Au",
          'Finperiode.date_format' => "La date de début n'est pas une date valide au format (DD/MM/YYYY)",
          'Finperiode.after' => "La date de fin n'est pas ultérieure à la date de début de la facture",
          'Totalttc.required' => "Merci de remplir le champ Cout TTC",
          'Consommation.required' => "Merci de remplir le champ Consommation",
          'Totalttc.numeric' => "Le Coût TTC doit-être au format (#0,00) avec deux chiffres après la virgule",
          'Consommation.numeric' => "La consommation doit-être au format (#0) sans virgule"
        )

      );

      if ($validation->fails()) {
          return Redirect::to('tbge/factures/create')
              ->withErrors($validation)
              ->withInput(Request::all());
        } else {
          
          $dateDebut = \Carbon\Carbon::createFromFormat('d/m/Y', Request::input('Debutperiode'));
          $dateFin = \Carbon\Carbon::createFromFormat('d/m/Y', Request::input('Finperiode'));
          
          $numeroFacture = \Request::input('Nom');     
          $compteurID = \Request::input('pFilterCompteur');
          
          if(\Request::has ( 'FactureID' ) && \Request::input ( 'FactureID' ) > 0){
            $facture = Factures::find(\Request::input ( 'FactureID' ));
          } else {
            $recordExists = Factures::where("nom", "=", $numeroFacture)
                                      ->whereHas("Compteur", function ($cQ) use($compteurID) {
                                        $cQ->where("compteurid", "=", $compteurID);
                                      })->first();
            
            $facture = /*$recordExists != NULL ? $recordExists : */ new Factures;
          }
          
          $isMulticommunes = static::isMulticommunes();

          if(!$isMulticommunes){
            $delegationID = static::getMyCommuneID();
          } else {
            $delegationID = Request::has ( 'DelegationID' ) ? Request::input ( 'DelegationID' ) : -1;

            if($delegationID == -1){
              $delegationID = Compteurs::find(\Request::input('pFilterCompteur'))->mouvrageid;
            }
          }

          $isUpdate = $facture->factureid > 0;
          
          $facture->mouvrageid = $delegationID;// static::getInsertionMouvrageID(); // Config::get('enertrack.MouvrageID');

          $facture->baseid = $baseid;
          $facture->nom = \Request::input('Nom');
          $facture->compteurid = \Request::input('pFilterCompteur');
          
          // Add FournisseurID
          $fournisseurID = Compteurs::find(\Request::input('pFilterCompteur'))->fournisseurid;
          $facture->fournisseurid = $fournisseurID;

          $facture->debutperiode = $dateDebut->toDateString();
          $facture->finperiode = $dateFin->toDateString();
          
          $facture->totalht = \Request::input('Totalht');
          $facture->totalttc = \Request::input('Totalttc');    
          
          $facture->tauxtva = \Request::input('TauxTVA');
          
          $facture->taxes = \Request::input('Taxes');

          $facture->remarques = \Request::input('Remarques');
          $facture->consommation = \Request::input('Consommation');
          
          if(!$isUpdate){
            $facture->total_regle = 0;
            $facture->total_restant_du = \Request::input('Totalttc');
          }
          
          $facture->save();

          DB::statement("CALL sk_tbge.facture_batiment_insert(?,?)", [$facture->factureid, $facture->compteurid]);
          DB::statement("CALL sk_tbge.calculer_periode_facturation_v1(?)", [$facture->factureid]);

          $modifierUrl = URL::to('tbge/factures/' . $facture->factureid . '/edit');
          Session::flash('success', "<p>Création de la facture effectuée avec succès ! <a href='{$modifierUrl}' class='btn btn-info btn-raised'>Modifier la facture</a></p>");

          return Redirect::to('tbge/factures');
        
        }
    }

    public function viewFacture($id) {
      return $this->edit($id, "tbge.tbge.facture.view");
    }
    
    public function edit($id, $viewTemplate="tbge.tbge.facture.create") {
      
      $factureQuery = Factures::with([
          "Compteur" => function ($cQ) {
            $cQ->with(["Energie"])
              ->with(["compteurbatiments.batiment" => function ($qB) {
                $qB->select([
                    DB::raw("batiment.BatimentID"),
                    DB::raw("batiment.Nom"),
                    DB::raw("batiment.Nom_Alternatif"),
                    DB::raw("batiment.Num_National"),
                  ]);
              }])
                ->select([
                  DB::raw("CompteurID"),
                  DB::raw("EnergieID"),
                  DB::raw("Nom"),
                  DB::raw("Numero"),
                  DB::raw("Reference")
                ]);
          }
        ]);

      $factureQuery->select([
        
        DB::raw("FactureID"),
        DB::raw("Nom"),
        DB::raw("CompteurID"),

        DB::raw("Debutperiode"),
        DB::raw("Finperiode"),

        DB::raw("Consommation"),
        DB::raw("Totalht"),
        DB::raw("TauxTVA"),
        DB::raw("Taxes"),
        DB::raw("Totalttc"),
        
        DB::raw("etat_facture"),
        DB::raw("total_restant_du AS montant_payable"),
        DB::raw("Remarques")  ,
      ]);

      $facture = $factureQuery->find($id);
      
      $view = View::make($viewTemplate)->with('facture', $facture);
      $payments = [];

      if($facture->etat_facture > 0){
        $payments = $this->getPayments($id); //dd($payments);
        $view->with('payments', $payments);
      }

      return $view;
    
    }

    public function getPayments($factureId) {
      $rawQuery = <<<QUERY
        SELECT 
          
          transaction.transactionid,
          transaction.cd_transaction,
          transaction.ref_transaction,
          transaction.date_transaction,
          
          compte.cd_comptetresorerie AS cd_comptetresorerie_sortant,
          compte.lib_comptetresorerie AS lib_comptetresorerie_sortant,
            
          transaction.devise_sortant_id,
          devise.cd_devise AS cd_devise_sortant,
          devise.lib_devise AS lib_devise_sortant,
            
          transaction.montant_transaction,
          sortietresorerie_facture.montant_regle,

          transaction.etat_transaction,
          CASE transaction.etat_transaction
              WHEN 0 THEN ( SELECT 'Non validée'::text AS text)
              WHEN 1 THEN ( SELECT 'Validée'::text AS text)
          ELSE NULL::text END AS etat_transaction_lib

        FROM sk_treso.sortietresorerie AS sortietresorerie

        INNER JOIN sk_treso.transaction  AS transaction  ON sortietresorerie.transactionid=transaction.transactionid
        INNER JOIN sk_treso.comptetresorerie  AS compte  ON transaction.comptetresorerie_sortant_id=compte.comptetresorerieid
        INNER JOIN sk_treso.devise                       ON transaction.devise_sortant_id = devise.deviseid
        
        INNER JOIN sk_treso.sortietresorerie_facture ON sortietresorerie_facture.factureid=$factureId
             AND sortietresorerie_facture.sortietresorerieid = sortietresorerie.transactionid

      QUERY;

      $result = DB::select($rawQuery);

      return $result;
    }

    public function getPaymentsNotWorking($factureId) {

      $params = [
        "p_factureid"                   => [0, 'integer', $factureId],
        "p_is_added_to_facture"         => [1, 'boolean', NULL],
        "p_cd_sortietresorerie"         => [2, 'varchar', NULL],
        "p_date_transaction_min"        => [3, 'timestamp', NULL],
        "p_date_transaction_max"        => [4, 'timestamp', NULL],
        "p_mouvrage_sortant_id"         => [5, 'integer', NULL],
        "p_comptetresorerie_sortant_id" => [6, 'integer', NULL],
        "p_devise_sortant_id"           => [7, 'smallint', NULL],
        "p_montant_transaction_min"     => [8, 'numeric', NULL],
        "p_montant_transaction_max"     => [9, 'numeric', NULL],
        "p_typebudgetid"                => [10, 'smallint', NULL],
        "p_energieid"                   => [11, 'integer', NULL],
        "p_fournisseurid"               => [12, 'integer', NULL],
        "p_mouvrageid"                  => [13, 'integer', NULL],
        "p_utilisateurid"               => [14, 'integer', NULL],
        "p_etat_transaction"            => [15, 'integer', NULL],
        "p_lib_search"                  => [16, 'varchar', NULL],
        "p_sorting_criteria"            => [17, 'varchar', NULL],
        "p_page_index"                  => [18, 'integer', '0'],
        "p_page_size"                   => [19, 'integer', 1000000]
      ];

      $functionName = PostgreSqlFunctionCaller::$PROCEDURE_NAME_FACTURE_PAYMENTS_SEARCH;
      $selection = ["*"];

      $caller = new PostgreSqlFunctionCaller($functionName, $params, implode(", ", $selection), FALSE, FALSE);
      $result = $caller->call();

      if(empty($result) || count($result) == 0){
        $result = [];
      }
      
      return $result;
    }

    public function update($id){

    }

    public function destroy($id) {
      $facture = Factures::find($id);
      $facture->delete();

      DB::table("facture_batiment")->where("factureid", $id)->delete();
      // redirect
      Session::flash('success', "Facture supprimée avec succès !");
      return Redirect::back();
      //return Redirect::to('tbge/factures');
    }

    public function deleteMultiple() {
      
      $factures = \Request::input("ids");
      
      Factures::destroy($factures);

      return Response::json([
        "status" => is_array($factures) ? "success" : "erreur",
      ]);
    }

    public function importCsv() {
      //$baseid =  Auth::user()->BaseID; 
      $baseid = "8e0910e0-cdee-70a1-55c3-b0f48ee8127f"; // Auth::user()->baseid;

      return  View::make ( $this->importViewName )
          ->with ( 'importViewName', $this->importViewName )
          ->with ( 'importedViewName', $this->importedViewName )
          ->with ( 'importPostUrl', $this->importPostUrl )
          ->with ( 'importFormUrl', $this->importFormUrl );
    }
    
    public function doImport() {
      try {
        return $this->reallyDoImport();
      } catch (\Exception $e) {
        
        $importError = "IMPORT ERROR : " . $e->getMessage();
        Log::error($importError);

        return Response::json([
                    "status" =>  "error",
                    "message" => $importError 
                ]) ;
      }
    }

    public function reallyDoImport() {
      
      $isMulticommunes = static::isMulticommunes();

      $energieElectriciteID = Config::get("enertrack.electriciteID");
      $energieEauID = Config::get("enertrack.eauID");

      $columnsConfig = \Request::input ( 'columnsConfig' );
      

      $records = \Request::input ( 'records' ); // Excel records to import
      
      $justValidate = \Request::input ( 'justValidate' );

      $importedFactureIDs = [];
      $importedRecords = [];
      $notImportedRecords = [];
      $noImportReasons = [];

      if(!is_array($columnsConfig)){
        return [
          "status" => "success",
          "report" => [
            "ok" => [],
            "nok" => array_map(function ($entry) {
            	return $entry['recid'];	
            }, $records) ,
            "reasons" => [],
            "justValidate" => $justValidate
          ]
        ];
      }

      array_filter($columnsConfig, function ($conf) use (&$NumeroFactureAccessor) {
        if($conf['mysqlColumn'] == "Nom"){
          $NumeroFactureAccessor = $conf['excelColumn'];
        } 
        return $conf['mysqlColumn'] == "Nom";
      });

      array_filter($columnsConfig, function ($conf) use (&$EnergieIDAccessor) {
        if($conf['mysqlColumn'] == "EnergieID"){
          $EnergieIDAccessor = $conf['excelColumn'];
        } 
        return $conf['mysqlColumn'] == "EnergieID";
      });

      array_filter($columnsConfig, function ($conf) use (&$FournisseureIDAccessor) {
        if($conf['mysqlColumn'] == "FournisseurCompteur"){
          $FournisseureIDAccessor = $conf['excelColumn'];
        } 
        return $conf['mysqlColumn'] == "FournisseurCompteur";
      });

      array_filter($columnsConfig, function ($conf) use (&$IDLieuAccessor) {
        if($conf['mysqlColumn'] == "IDLieu"){
          $IDLieuAccessor = $conf['excelColumn'];
        } 
        return $conf['mysqlColumn'] == "IDLieu";
      });

      array_filter($columnsConfig, function ($conf) use (&$NumeroCompteurAccessor) {
        if($conf['mysqlColumn'] == "NumeroCompteur"){
          $NumeroCompteurAccessor = $conf['excelColumn'];
        } 
        return $conf['mysqlColumn'] == "NumeroCompteur";
      });

      array_filter($columnsConfig, function ($conf) use (&$DebutperiodeAccessor) {
        if($conf['mysqlColumn'] == "Debutperiode"){
          $DebutperiodeAccessor = $conf['excelColumn'];
        } 
        return $conf['mysqlColumn'] == "Debutperiode";
      });

      array_filter($columnsConfig, function ($conf) use (&$FinperiodeAccessor) {
        if($conf['mysqlColumn'] == "Finperiode"){
          $FinperiodeAccessor = $conf['excelColumn'];
        } 
        return $conf['mysqlColumn'] == "Finperiode";
      });
            
      $delegationID = -1;
      
      if(!$isMulticommunes){
        $delegationID = static::getMyCommuneID();
      }

      foreach ($records as $row) {
        
        $energieID = -1;
        $fournisseurID = -1;
        $compteurCIL = NULL;
        $compteurNumber = NULL;
        
        $compteurID = -1;

        $Debutperiode = isset($row[$DebutperiodeAccessor]) ? trim($row[$DebutperiodeAccessor]) : NULL; //"0000-00-00";
        $Finperiode = isset($row[$FinperiodeAccessor]) ? trim($row[$FinperiodeAccessor]) : NULL; // "0000-00-00";
        
        $shouldImport = true;
        $noImportReason = NULL;

        try {
          if($Debutperiode != NULL){
            $Debutperiode = \Carbon\Carbon::createFromFormat("d/m/Y", $Debutperiode)->format("Y-m-d");
          } else {
            $Debutperiode = "0000-00-00";
          }
        } catch(\Exception $e){
          $shouldImport = false;
          $noImportReason = "Mauvaise date $DebutperiodeAccessor: $Debutperiode";
          //continue;
        }
        
        try {
          if($Finperiode != NULL){
          	$Finperiode = \Carbon\Carbon::createFromFormat("d/m/Y", $Finperiode)->format("Y-m-d");
          } else {
          	$Finperiode = "0000-00-00";
          }
        } catch(\Exception $e){
          $shouldImport = false;
          $noImportReason = "Mauvaise date $FinperiodeAccessor: $Finperiode";
          //continue;
        }

        //echo "DEBUT: Debutperiode= $Debutperiode ; Finperiode = $Finperiode";die();

        $numeroFacture = isset($row[$NumeroFactureAccessor]) ? trim($row[$NumeroFactureAccessor]) : -1;
        $numeroCompteur = isset($row[$NumeroCompteurAccessor]) ? trim($row[$NumeroCompteurAccessor]) : -1;
        $idLieu = isset($row[$IDLieuAccessor]) ? trim($row[$IDLieuAccessor]) : -1;
        $energieID = isset($row[$EnergieIDAccessor]) ? trim($row[$EnergieIDAccessor]) : -1;
        $fournisseurName = isset($row[$FournisseureIDAccessor]) ? trim($row[$FournisseureIDAccessor]) : -1;
        //dd("numeroFacture = $numeroFacture, numeroCompteur = $numeroCompteur, idLieu=$idLieu, energie = $energieID, fournisseur = $fournisseurName");
        
        $recordExists = Factures::where("nom", "=", DB::raw("'" . $numeroFacture."'"))
                                  ->where("debutperiode", "=", DB::raw("'" . $Debutperiode . "'"))
                                  ->where("finperiode", "=", DB::raw("'" . $Finperiode . "'") )
                                  ->whereHas("Compteur", function ($cQ) use($numeroCompteur, $idLieu, $energieID, $fournisseurName) {
                                    $eid = -1;
                                    
                                    if($energieID == "EAU")
                                      $eid = 5;
                                    if($energieID == "ELECTRICITE")
                                      $eid = 1;

                                    $cQ->matchingReference($idLieu)
                                        ->where("numero", "=", DB::raw("'". $numeroCompteur ."'") )
                                        ->whereHas("Fournisseur", function ($fQ) use ($fournisseurName) {
                                          $fQ->where("nom", "=", DB::raw("'". $fournisseurName ."'") ) 
                                              ->where("type", "=", DB::raw("'Fournisseur'"));
                                        })
                                        ->where("energieid", "=", $eid);
                                  })->first();
        
        //dd("Debut: ". $Debutperiode . " Fin: ");

        $eloquentRecord = $recordExists != NULL ? $recordExists : new Factures;
        //$eloquentRecord = new Factures;
        
        if($shouldImport)
        foreach ($columnsConfig as $excelColumn) {
          
          if(!$shouldImport) // the record won't be imported
            break;
          
          if(!isset($excelColumn['excelColumn']) || empty($excelColumn['excelColumn'])) {
            
            if(isset($excelColumn['required']) && $excelColumn['required'] === true) {
              $shouldImport = false; // a required data is missing
              $noImportReason = "La colonne {$excelColumn['display']} est requise mais non spécifiée!!";
            }
            continue;
          }

          $column = trim($excelColumn['excelColumn']);
          $mysqlColumn = trim($excelColumn['mysqlColumn']);

          $columnType = $excelColumn['columnType'];

          if(!isset($row[$column]) || empty($row[$column])) {
            
            if(isset($excelColumn['required']) && $excelColumn['required'] === true) {
              $shouldImport = false;
              $noImportReason = "La colonne {$column} est introuvable!!";
            }
            
            continue;
          }

          if(in_array($columnType, ["text", "string", "int", "integer", "float", "decimal"])) {
            $theValue = $row[$column];
            
            if("float" == $columnType || "decimal" == $columnType|| "int" == $columnType|| "integer" == $columnType){
              $theValue = str_replace(",", ".", $theValue);
              $theValue = trim($theValue);

              if(!is_numeric($theValue)){
                $shouldImport = false;
                $noImportReason = "({$column}) Veuillez fournir une valeur numérique!!";
                continue;
              }
            }

            $eloquentRecord->{strtolower($mysqlColumn)} = trim($theValue);
            
          } elseif("DelegationID" == $mysqlColumn) {
            // Handle related data here : delegation
            if($isMulticommunes){
              
              $delegation = !isset($row[$column]) ? NULL : Mos::where("societe", "=", $row[$column])->orWhere("mouvrageid", "=", $row[$column])->first();
              
              if(!$delegation ||$delegation->mouvrageid <= 0){
                $shouldImport = false;
                $noImportReason = "Colonne Délégation non spécifiée!!";
                continue;
              }
              $delegationID = $delegation->mouvrageid;
            }
          } elseif ("EnergieID" == $mysqlColumn && !empty($column)) {
            
            if($row[$column] && trim($row[$column]) == "EAU" )
              $energieID = $energieEauID;

            if($row[$column] && trim($row[$column]) == "ELECTRICITE" )
              $energieID = $energieElectriciteID;

            if($energieID <= 0){
                $shouldImport = false;
                $noImportReason = "Colonne Type de consommation inconnue. Valeurs possibles: EAU, ELECTRICITE";
                continue;
              }
            
          } elseif ("IDLieu" == $mysqlColumn && !empty($column)) {
            $compteurCIL = $row[$column];
          } elseif ("NumeroCompteur" == $mysqlColumn && !empty($column)) {
            $compteurNumber = $row[$column];
          } elseif ("FournisseurCompteur" == $mysqlColumn && !empty($column)) {
            $fournisseurCompteurNom = trim($row[$column]);
            
            $fournisseurCompteur = Contacts::where("type", "=", "Fournisseur")->where("nom", "=", $fournisseurCompteurNom)->first();
            
            if($fournisseurCompteur == NULL || $fournisseurCompteur->fournisseurid <= 0 ){
              $shouldImport = false;
                $noImportReason = "Fournisseur $fournisseurCompteurNom introuvable dans le système";
                continue;
            }

            $fournisseurID = $fournisseurCompteur->fournisseurid;

          } elseif ("Debutperiode" == $mysqlColumn && !empty($column)) {
            $Debutperiode = trim($row[$column]);

            if(empty($Debutperiode)){
              $shouldImport = false;
              $noImportReason = "Veuillez vérifier la date de début de consommation";
              continue;
            } else {
              
              try {
                $carbonDate = \Carbon\Carbon::createFromFormat('d/m/Y', $Debutperiode);
                
                $minDate = \Carbon\Carbon::createFromFormat('d/m/Y', "01/01/2015");
                $maxDate = \Carbon\Carbon::createFromFormat('d/m/Y', date("d/m/Y"));

                if($carbonDate->lessThan($minDate)){
                  $shouldImport = false;
                  $noImportReason = "La date de début de consommation est antérieure à 2015!";
                  continue;
                }

                if($carbonDate->greaterThan($maxDate->subDays(30))){
                  $shouldImport = false;
                  $noImportReason = "La date de début de consommation est ultérieure à la période courante!";
                  continue;
                }

                $tt = $carbonDate->subDay()->toDateString();
              } catch (\Exception $e) {
                $shouldImport = false;
                $noImportReason = "Veuillez vérifier la date de début de consommation";
                continue; 
              }
            }

          } elseif ("Finperiode" == $mysqlColumn && !empty($column)) {
            $Finperiode = trim($row[$column]);

            if(empty($Finperiode)){
              $shouldImport = false;
              $noImportReason = "Veuillez vérifier la date de fin de consommation";
              continue;
            } else {
              
              try {
                $carbonDate = \Carbon\Carbon::createFromFormat('d/m/Y', $Finperiode);
                
                $minDate = \Carbon\Carbon::createFromFormat('d/m/Y', "01/01/2015");
                $maxDate = \Carbon\Carbon::createFromFormat('d/m/Y', date("d/m/Y"));

                if($carbonDate->lessThan($minDate)){
                  $shouldImport = false;
                  $noImportReason = "La date de fin de consommation est antérieure à 2015!";
                  continue;
                }

                if($carbonDate->greaterThan($maxDate->subDays(1))){
                  $shouldImport = false;
                  $noImportReason = "La date de fin de consommation est dans la période courante!";
                  continue;
                }

                $tt = $carbonDate->subDay()->toDateString();
              } catch (\Exception $e) {
                $shouldImport = false;
                $noImportReason = "Veuillez vérifier la date de fin de consommation";
                continue; 
              }
            }
          }

        }

        if (!$shouldImport) {
          $notImportedRecords[] = $row['recid'];
          $noImportReasons[$row['recid']] = ["recid" => $row['recid'], "reason" => $noImportReason];
          continue;
        }

        $eloquentRecord->fournisseurid = $fournisseurID;
        $eloquentRecord->mouvrageid = $delegationID;

        // Validate CompteurID (fournisseur SRM ; repli si facture encore au nom Redal)
        $compteurQuery = Compteurs::matchingReference($compteurCIL)
                                ->where("energieid", "=", $energieID)
                                ->where("numero", "=", DB::raw("'" . $compteurNumber . "'") )
                                ->where("mouvrageid", "=", $delegationID);

        $compteur = (clone $compteurQuery)->where("fournisseurid", "=", $fournisseurID);
        $compteurExists = $compteur->count() > 0;
        if (!$compteurExists) {
            $compteur = $compteurQuery;
            $compteurExists = $compteur->count() > 0;
        }
        $compteurID = $compteurExists ? $compteur->first()->compteurid : -1;
        // Toujours l'ID fournisseur actuel du compteur (ex. SRM après migration),
        // même si l'Excel cite encore l'ancien nom (Redal, ONEE…).
        if ($compteurExists && $compteurID > 0) {
          $matchedFournisseurId = (int) ($compteur->first()->fournisseurid ?? 0);
          if ($matchedFournisseurId > 0) {
            $fournisseurID = $matchedFournisseurId;
            $eloquentRecord->fournisseurid = $fournisseurID;
          }
        }

        if (!$compteurExists || $compteurID <= 0) {
          $shouldImport = false;
          $notImportedRecords[] = $row['recid'];
          $noImportReason = "Vérifiez qu'un compteur " . ($energieID == 1 ? "électrique": "d'eau ") . " avec les informations suivants existe: Fournisseur: $fournisseurCompteurNom, ID Lieu: $compteurCIL, Numéro: $compteurNumber";
          $noImportReasons[$row['recid']] = ["recid" => $row['recid'], "reason" => $noImportReason];
          continue;
        }

        // Validate Mosquee exists
        $compteurBatimentExists = Compteurbatiments::where("compteurid", "=", $compteurID)->count() == 1;
        $batimentID = $compteurBatimentExists ? Compteurbatiments::where("compteurid", "=", $compteurID)->first()->batimentid : -1;

        $mosqueeExiste = Batiments::where("batimentid", "=", $batimentID)->count() == 1;

        if (!$mosqueeExiste || !$compteurBatimentExists || $batimentID <= 0 ) {
          $shouldImport = false;
          $notImportedRecords[] = $row['recid'];
          $noImportReason = "Vérifiez le compteur $compteurNumber : Apparemment, aucune mosquée n'y correspond";
          $noImportReasons[$row['recid']] = ["recid" => $row['recid'], "reason" => $noImportReason];
          continue;
        }
        
        // Assign amount to the counter
        $eloquentRecord->compteurid = $compteurID;
        //error_log("CompteurReference = $contratOrCompteurNumber");
        // Validate Debutperiode And Finperiode
        $validation = Validator::make ( [
              "Debutperiode" => $Debutperiode,
              "Finperiode" => $Finperiode
        ], [
              "Debutperiode" => "required|date_format:d/m/Y",
              'Finperiode' => 'required|date_format:d/m/Y|after:' . \Carbon\Carbon::createFromFormat('d/m/Y', $Debutperiode)->subDay()->toDateString(),
        ]);
        
        if ($validation->fails()) {
          $shouldImport = false;
          $notImportedRecords[] = $row['recid'];
          
          $messages = $validation->messages();
          $errorDebut = $messages->has('Debutperiode') ? $messages->first('Debutperiode') : "";
          $errorFin = $messages->has('Finperiode') ? $messages->first('Finperiode') : "";

          $noImportReason = "Problème de date (début et/ou fin): $errorDebut $errorFin";
          
          $noImportReasons[$row['recid']] = ["recid" => $row['recid'], "reason" => $noImportReason];
          continue;
        }
        
        $Debutperiode = \Carbon\Carbon::createFromFormat("d/m/Y", $Debutperiode);
        $Finperiode = \Carbon\Carbon::createFromFormat("d/m/Y", $Finperiode);

        $eloquentRecord->debutperiode = $Debutperiode;
        $eloquentRecord->finperiode = $Finperiode;
        
        // COMPUTE Montant HT and Taxes
        $tva = (1.0*Energies::find($energieID)->tauxtva);
        $taux = (1.0*Energies::find($energieID)->tauxtva)/100;
        
        $eloquentRecord->tauxtva = $tva;

        $eloquentRecord->totalht = $eloquentRecord->totalttc/(1+$taux);
        $eloquentRecord->taxes = $eloquentRecord->totalttc - $eloquentRecord->totalht;

        // Set total_regle and total_restant_du during import
        if($recordExists == NULL) { // Only new Factures are concerned
          $eloquentRecord->total_regle = 0;
          $eloquentRecord->total_restant_du = $eloquentRecord->totalttc;
        }

        // Really do the import the entry here
        
        try {

          DB::beginTransaction();
          
          $eloquentRecord->save();
          DB::statement("CALL sk_tbge.calculer_periode_facturation_v1(?)", [$eloquentRecord->factureid]);          
          DB::statement("CALL sk_tbge.facture_batiment_insert(?,?)", [$eloquentRecord->factureid, $eloquentRecord->compteurid]);

          DB::commit();
          
          $importedRecords[] = $row['recid'];
          $importedFactureIDs[] = $eloquentRecord->factureid;

        } catch (\Exception $e) {
           
          DB::rollBack();

          $shouldImport = false;
          $notImportedRecords[] = $row['recid'];
          
          $message = $e->getMessage();
          $noImportReason = $message;
                    
          $noImportReasons[$row['recid']] = ["recid" => $row['recid'], "reason" => $noImportReason];
          continue;
        
        }

      }

      return [
        "status" => "success",
        "report" => [
          "ok" => $importedRecords,
          "nok" => $notImportedRecords,
          "reasons" => $noImportReasons,
          "justValidate" => $justValidate
        ]
      ];

    }
    
    private function objectsToArray($objs, $key, $val){
      $arr = array();
      foreach($objs as $obj){
        $arr[$obj->$key] = $obj->$val;
      }
      return $arr;
    }

    public function export() {
      @ini_set('memory_limit', '512M');
      @set_time_limit(300);

      // Sans filtre année, l'export tente des centaines de milliers de lignes → crash mémoire PHP (128 Mo).
      $annee = request()->input('pFilterAnnee');
      if ($annee === null || $annee === '') {
        return redirect('/tbge/factures')
          ->with('error', "Pour exporter, choisissez au moins une Année (idéalement aussi Délégation / Trimestre), cliquez RECHERCHER, puis l'icône Excel.");
      }

      return $this->datatable("export");
    }

  public function listForPayment() {
    
    $exclusions = \Request::input("exclusions", []);

    $displayText = <<<EOD
              ' N°: '|| facture.Nom || 
              (CASE WHEN energie.energieid = 1 THEN ' (ELECT. ' ELSE '(EAU ' END) ||
               TO_CHAR(facture.Debutperiode::DATE, 'dd/mm/yy') || 
              ' - ' || TO_CHAR(facture.Finperiode::DATE, 'dd/mm/yy') || ': <strong style=''color:red;''>' ||
              facture.Totalttc || '</strong> Dirhams' || 
              (CASE WHEN facture.etat_facture = 1 THEN ' Payée' WHEN facture.etat_facture = 2 THEN ' Payée Partiellement' ELSE ' Non Payée' END) || ')' ||
              ' N° CPT: ' || facture_batiment.CompteurNumero || 
              ' - ID Lieu: ' || facture_batiment.CompteurReference ||
              ' ('|| batiment.Nom  || ')' AS text
    EOD;

    $factures = DB::table("sk_tbge.facture")
                
              ->join('sk_tbge.facture_batiment', DB::raw('facture_batiment.FactureID'), '=', DB::raw('facture.FactureID'))
              ->join('sk_tbge.batiment', DB::raw('batiment.BatimentID'), '=', DB::raw('facture_batiment.BatimentID'))
                  
              ->leftJoin('sk_tbge.mouvrage', DB::raw('mouvrage.MouvrageID'), '=', DB::raw('facture.MouvrageID'))
              ->leftJoin('sk_tbge.energie', DB::raw('energie.EnergieID'), '=', DB::raw('facture_batiment.CompteurEnergieID'))
              ->leftJoin('sk_tbge.compteur', DB::raw('facture.CompteurID'), '=', DB::raw('compteur.CompteurID'))
              ->leftJoin('sk_tbge.fournisseur', DB::raw('compteur.FournisseurID'), '=', DB::raw('fournisseur.fournisseurid'))
                  
                // For payment status
              ->leftJoin('sk_treso.sortietresorerie_facture', DB::raw('sortietresorerie_facture.FactureID'), '=', DB::raw('facture.FactureID'))
                  
              ->select([
                  
                DB::raw("facture.FactureID AS id"),
                DB::raw("facture.nom AS cd_facture"),
                DB::raw("compteur.FournisseurID AS fournisseur"),
                DB::raw("compteur.EnergieID AS energieid"),
                    
                DB::raw($displayText),
                DB::raw("'N° CPT: ' || facture_batiment.CompteurNumero || 
              ' - ID Lieu: ' || facture_batiment.CompteurReference AS lib_compteur"),
                DB::raw("mouvrage.Societe AS lib_mouvrage"),
                DB::raw("'N°: ' || batiment.Num_National || ' - ' || batiment.Nom AS lib_mosquee"),

                DB::raw("TO_CHAR(facture.Debutperiode::DATE, 'dd/mm/yyyy') AS Debutperiode"),
                DB::raw("TO_CHAR(facture.Finperiode::DATE, 'dd/mm/yyyy') AS Finperiode"),
                            
                DB::raw("consommation AS quantite"),
                DB::raw("totalttc"),

                DB::raw("facture.total_regle"),
                DB::raw("facture.total_restant_du"),
                
                DB::raw("facture.etat_facture"),
                DB::raw("(CASE WHEN facture.etat_facture = 1 THEN 'Payée' WHEN facture.etat_facture = 2 THEN 'Payée Partiellement' ELSE 'Non Payée' END) AS lib_etat_facture"),

                DB::raw("sortietresorerie_facture.sortietresorerieid AS payment_id"),

                DB::raw("COALESCE(sortietresorerie_facture.montant_regle, 0) AS montant_regle"),
                DB::raw("facture.total_restant_du AS montant_payable"),
              ]);

    if(!empty($exclusions))
      $factures = $factures->whereRaw(DB::raw("facture.factureid NOT IN (" . implode(",", $exclusions) . ")"));
    
    if(!static::isMulticommunes()){
      $user = Auth::user();
      $communeId = static::getMyCommuneID();
      
      // Vérifier si l'utilisateur a des délégations assignées dans utilisateur_mouvrage
      $hasAssignedDelegations = DB::connection('tbge')->table('utilisateur_mouvrage')
      	->where("utilisateurid", "=", $user->utilisateurid)
      	->where("entiteid", "=", 1) // 1 = délégations
      	->where("operationid", "=", 2) // 2 = voir (R)
      	->exists();
      
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
      		$factures->whereIn("facture.mouvrageid", $assignedMouvrageIds);
      	} else {
      		// Fallback : filtrer par la délégation de l'utilisateur
      		$factures->where("facture.mouvrageid", "=", $communeId);
      	}
      } else {
      	// Si l'utilisateur n'a pas de délégations assignées, inclure aussi la DRAI parente
      	$userDelegation = DB::connection('tbge')->table('mouvrage')
      		->where("mouvrageid", "=", $communeId)
      		->select([
      			DB::raw("mouvrageid"),
      			DB::raw("COALESCE(mouvrage_parent_id, 0) AS mouvrage_parent_id")
      		])
      		->first();
      	
      	if($userDelegation && $userDelegation->mouvrage_parent_id > 0) {
      		// Agent provincial : inclure la DRAI parente + la provinciale
      		$draiId = $userDelegation->mouvrage_parent_id;
      		$factures->where(function($q) use ($communeId, $draiId) {
      			$q->where("facture.mouvrageid", "=", $communeId)
      			  ->orWhere("facture.mouvrageid", "=", $draiId);
      		});
      	} else {
      		// Agent régional (DRAI) : filtrer uniquement par sa délégation
      		$factures->where("facture.mouvrageid", "=", $communeId);
      	}
      }
    }

    // TODO: Filter here
    $search = \Request::input('q');
    if(!empty($search) && isset($search['term']) && $search['term'] != ''){
    
      $factures->where(function($q) use($search){
        
        $searchValue = Str::lower('%' . trim($search['term']) . '%' );

        $q->where(DB::raw('LOWER(facture.Nom)'), 'LIKE', $searchValue);
        $q->orwhere(DB::raw('LOWER(facture_batiment.CompteurReference)'), 'LIKE', $searchValue);
        $q->orwhere(DB::raw('LOWER(facture_batiment.CompteurNumero)'), 'LIKE', $searchValue);
        $q->orwhere(DB::raw('LOWER(batiment.Num_National)'), 'LIKE', $searchValue);
        $q->orwhere(DB::raw('LOWER(batiment.Nom)'), 'LIKE', $searchValue);
        $q->orwhere(DB::raw('LOWER(mouvrage.Societe)'), 'LIKE', $searchValue);
        $q->orwhere(DB::raw('LOWER(batiment.Nom_Alternatif)'), 'LIKE', $searchValue);

      });
    
    }


    $factures = $factures->limit(15)->get();

    return [
      "data" => $factures
    ];
  }

    
}