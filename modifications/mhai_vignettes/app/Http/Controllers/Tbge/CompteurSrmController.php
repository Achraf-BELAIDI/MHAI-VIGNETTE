<?php

namespace App\Http\Controllers\Tbge;

use App\Services\Tbge\CompteurSrmService;
use Auth;
use Illuminate\Http\Request;
use Response;

class CompteurSrmController extends BaseController {

	public function __construct(private CompteurSrmService $service) {
		// dataName requis par BaseController::canEdit() (sinon 403 systématique)
		parent::__construct([
			'dataName' => 'compteurs',
		]);
	}

	/**
	 * Lecture / migration SRM : droits compteurs OU batiments (fiche mosquée).
	 */
	private function canManageSrm(): bool {
		$user = Auth::user();
		if (!$user || !isset($user->role) || $user->role === null) {
			return false;
		}
		$role = $user->role;

		return $role->hasAccess('compteurs', 'U')
			|| $role->hasAccess('compteurs', 'R')
			|| $role->hasAccess('batiments', 'U')
			|| $role->hasAccess('batiments', 'R');
	}

	private function canUpdateSrm(): bool {
		$user = Auth::user();
		if (!$user || !isset($user->role) || $user->role === null) {
			return false;
		}
		$role = $user->role;

		return $role->hasAccess('compteurs', 'U')
			|| $role->hasAccess('batiments', 'U');
	}

	public function forBatiment(int $batimentId) {
		if (!$this->canManageSrm()) {
			return Response::json(['message' => 'Accès refusé'], 403);
		}

		if (!$this->service->hasLegacyColumn()) {
			return Response::json([
				'enabled' => false,
				'message' => 'Migration SRM non disponible (SQL non appliqué).',
				'compteurs' => [],
			]);
		}

		return Response::json([
			'enabled' => true,
			'compteurs' => $this->service->getForBatiment($batimentId),
			'fournisseur_srm_id' => $this->service->resolveFournisseurId('SRM'),
		]);
	}

	public function update(Request $request, int $compteurId) {
		if (!$this->canUpdateSrm()) {
			return Response::json(['message' => 'Accès refusé'], 403);
		}

		$batimentId = (int) $request->input('batiment_id', 0);
		if ($batimentId <= 0 || !$this->service->userCanManageCompteur($compteurId, $batimentId)) {
			return Response::json(['message' => 'Compteur introuvable pour cette mosquée.'], 404);
		}

		try {
			$compteur = $this->service->applyMigration(
				$compteurId,
				(string) $request->input('reference_srm', ''),
				$request->has('reference_ancienne')
					? (string) $request->input('reference_ancienne')
					: null
			);
			$compteur->load('Fournisseur');
		} catch (\InvalidArgumentException $e) {
			return Response::json(['message' => $e->getMessage()], 422);
		} catch (\RuntimeException $e) {
			return Response::json(['message' => $e->getMessage()], 503);
		}

		return Response::json([
			'message' => 'Contrat SRM enregistré.',
			'compteur' => [
				'compteurid' => (int) $compteur->compteurid,
				'reference' => (string) ($compteur->reference ?? ''),
				'reference_ancienne' => (string) ($compteur->reference_ancienne ?? ''),
				'fournisseurid' => (int) ($compteur->fournisseurid ?? 0),
				'fournisseur_nom' => (string) (optional($compteur->Fournisseur)->nom ?? 'SRM'),
				'fournisseur_ancien_id' => (int) ($compteur->fournisseur_ancien_id ?? 0),
				'fournisseur_ancien_nom' => $this->service->hasFournisseurAncienColumn()
					? $this->service->resolveFournisseurNom((int) ($compteur->fournisseur_ancien_id ?? 0))
					: '',
			],
		]);
	}
}
