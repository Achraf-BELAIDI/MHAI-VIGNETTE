<?php

namespace App\Metadata\Excel;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\Exportable;

use DB;

class FacturesExport implements FromCollection, WithMapping, WithHeadings {
    
    use Exportable;

    protected $entries;

    public function __construct($entries) {
    	$this->entries = $entries;
    }

    public function headings(): array {
        return array_values(self::COLUMNS_MAPPING);
    }

    public function collection() {
    	return collect($this->entries);
    }

    public function map($facture): array {
        return self::rowFromFacture($facture);
    }

    public static function rowFromFacture($facture): array {
        $etat = (int) ($facture->etat_facture ?? 0);
        $etatLib = $etat === 1 ? 'Payée' : ($etat === 2 ? 'Payée Partiellement' : 'Non Payée');

        return [
            $facture->factureid ?? '',
            $facture->compteurid ?? '',
            $facture->debutperiode ?? '',
            $facture->finperiode ?? '',
            $facture->num_facture ?? '',
            $facture->totalttc ?? '',
            $facture->consommation ?? '',
            $facture->num_compteur ?? '',
            $facture->ref_compteur ?? '',
            $facture->lib_batiment ?? '',
            $facture->num_national ?? ($facture->num_national_batiment ?? ''),
            $facture->lib_mouvrage ?? '',
            $facture->lib_energie ?? '',
            $facture->lib_fournisseur ?? '',
            $etatLib,
        ];
    }

    const COLUMNS_MAPPING = [
    	"A1" => "factureid",
    	"B1" => "compteurid",
    	"C1" => "debutperiode", 
    	"D1" => "finperiode", 
    	"E1" => "nom", 
    	"F1" => "totalttc",
    	"G1" => "consommation",
    	"H1" => "compteur_numero",
    	"I1" => "compteur_reference",
    	"J1" => "nom_mosquee",
    	"K1" => "numero_mosquee",
    	"L1" => "delegation",
    	"M1" => "compteur_energie",
    	"N1" => "fournisseur",
    	"O1" => "etat_facture",
    	//"P1" => "lib_etat_facture"    
    ];

}
