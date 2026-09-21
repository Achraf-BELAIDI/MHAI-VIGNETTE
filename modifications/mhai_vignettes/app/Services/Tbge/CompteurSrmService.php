<?php

namespace App\Services\Tbge;

use App\Models\Tbge\Compteurbatiments;
use App\Models\Tbge\Compteurs;
use DB;
use Illuminate\Support\Facades\Schema;

class CompteurSrmService {

	public function hasLegacyColumn(): bool {
		return Schema::connection('tbge')->hasColumn('compteur', 'reference_ancienne');
	}

	public function hasFournisseurAncienColumn(): bool {
		return Schema::connection('tbge')->hasColumn('compteur', 'fournisseur_ancien_id');
	}

	public function isSrmFournisseurName(?string $nom): bool {
		$nom = trim((string) $nom);
		if ($nom === '') {
			return false;
		}
		return (bool) preg_match('/\bsrm\b/i', $nom);
	}

	public function isSrmFournisseurId(int $fournisseurId): bool {
		if ($fournisseurId <= 0) {
			return false;
		}
		$row = DB::connection('tbge')->table('fournisseur')
			->where('fournisseurid', $fournisseurId)
			->first();

		return $row ? $this->isSrmFournisseurName((string) ($row->nom ?? '')) : false;
	}

	public function resolveFournisseurId(string $nom = 'SRM'): int {
		$needle = strtolower(trim($nom));
		$rows = DB::connection('tbge')->table('fournisseur')
			->select(['fournisseurid', 'nom'])
			->orderBy('fournisseurid')
			->get();

		foreach ($rows as $row) {
			$name = (string) ($row->nom ?? '');
			if ($needle === 'srm') {
				if (preg_match('/\bsrm\b/i', $name)) {
					return (int) $row->fournisseurid;
				}
			} elseif (stripos($name, $nom) !== false) {
				return (int) $row->fournisseurid;
			}
		}

		return 0;
	}

	public function resolveFournisseurNom(int $fournisseurId): string {
		if ($fournisseurId <= 0) {
			return '';
		}
		$nom = DB::connection('tbge')->table('fournisseur')
			->where('fournisseurid', $fournisseurId)
			->value('nom');

		return trim((string) ($nom ?? ''));
	}

	public function getForBatiment(int $batimentId): array {
		if (!$this->hasLegacyColumn()) {
			return [];
		}

		$compteurIds = Compteurbatiments::query()
			->where('batimentid', $batimentId)
			->pluck('compteurid');

		if ($compteurIds->isEmpty()) {
			return [];
		}

		$hasAncienFourn = $this->hasFournisseurAncienColumn();

		return Compteurs::query()
			->with(['Fournisseur' => function ($q) {
				$q->select([DB::raw('fournisseurid'), DB::raw('nom')]);
			}])
			->whereIn('compteurid', $compteurIds)
			->orderBy('energieid')
			->orderBy('compteurid')
			->get()
			->map(function ($row) use ($hasAncienFourn) {
				$ancienFournId = $hasAncienFourn ? (int) ($row->fournisseur_ancien_id ?? 0) : 0;

				return [
					'compteurid' => (int) $row->compteurid,
					'reference' => (string) ($row->reference ?? ''),
					'reference_ancienne' => (string) ($row->reference_ancienne ?? ''),
					'numero' => (string) ($row->numero ?? ''),
					'energieid' => (int) $row->energieid,
					'fournisseurid' => (int) ($row->fournisseurid ?? 0),
					'fournisseur_nom' => (string) (optional($row->Fournisseur)->nom ?? ''),
					'fournisseur_ancien_id' => $ancienFournId,
					'fournisseur_ancien_nom' => $ancienFournId > 0
						? $this->resolveFournisseurNom($ancienFournId)
						: '',
				];
			})
			->all();
	}

	/**
	 * Applique la conservation de l'ancien n° + ancien fournisseur.
	 */
	public function applyLegacyFields(
		Compteurs $compteur,
		?string $referenceAncienne = null,
		?int $previousFournisseurId = null
	): Compteurs {
		if ($this->hasLegacyColumn()) {
			$currentLegacy = trim((string) ($compteur->reference_ancienne ?? ''));
			if ($referenceAncienne !== null && trim($referenceAncienne) !== '') {
				$compteur->reference_ancienne = trim($referenceAncienne);
			} elseif ($currentLegacy === '' && $referenceAncienne !== null) {
				$compteur->reference_ancienne = null;
			}
		}

		if ($this->hasFournisseurAncienColumn()) {
			$currentAncienId = (int) ($compteur->fournisseur_ancien_id ?? 0);
			$prevId = (int) ($previousFournisseurId ?? 0);
			if ($currentAncienId <= 0 && $prevId > 0 && !$this->isSrmFournisseurId($prevId)) {
				$compteur->fournisseur_ancien_id = $prevId;
			}
		}

		$compteur->save();

		return $compteur;
	}

	public function applyMigration(int $compteurId, string $referenceSrm, ?string $referenceAncienne = null): Compteurs {
		if (!$this->hasLegacyColumn()) {
			throw new \RuntimeException(
				'Colonne reference_ancienne absente. Exécutez database/sql/add_compteur_reference_ancienne.sql'
			);
		}

		$referenceSrm = trim($referenceSrm);
		if ($referenceSrm === '') {
			throw new \InvalidArgumentException('Le n° de contrat SRM est obligatoire.');
		}

		/** @var Compteurs $compteur */
		$compteur = Compteurs::query()->findOrFail($compteurId);
		$currentRef = trim((string) ($compteur->reference ?? ''));
		$currentLegacy = trim((string) ($compteur->reference_ancienne ?? ''));
		$previousFournisseurId = (int) ($compteur->fournisseurid ?? 0);

		if ($referenceAncienne !== null) {
			$legacyRef = trim($referenceAncienne) ?: null;
		} elseif ($currentLegacy === '' && $currentRef !== '' && strcasecmp($currentRef, $referenceSrm) !== 0) {
			$legacyRef = $currentRef;
		} else {
			$legacyRef = $currentLegacy !== '' ? $currentLegacy : null;
		}

		$compteur->reference = $referenceSrm;

		$fournisseurId = $this->resolveFournisseurId('SRM');
		if ($fournisseurId > 0) {
			$compteur->fournisseurid = $fournisseurId;
		}

		$compteur->save();

		return $this->applyLegacyFields($compteur->fresh(), $legacyRef, $previousFournisseurId);
	}

	public function userCanManageCompteur(int $compteurId, int $batimentId): bool {
		return Compteurbatiments::query()
			->where('compteurid', $compteurId)
			->where('batimentid', $batimentId)
			->exists();
	}
}
