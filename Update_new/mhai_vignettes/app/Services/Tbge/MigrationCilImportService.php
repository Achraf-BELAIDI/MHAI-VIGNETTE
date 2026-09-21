<?php

namespace App\Services\Tbge;

use App\Models\Tbge\Compteurs;
use Config;
use DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;

class MigrationCilImportService {

	private ?bool $hasLegacyColumn = null;

	public function hasLegacyColumn(): bool {
		if ($this->hasLegacyColumn === null) {
			$this->hasLegacyColumn = Schema::hasColumn('compteur', 'reference_ancienne');
		}
		return $this->hasLegacyColumn;
	}

	/**
	 * Fichier Excel SRM (ex. Tanger rural) :
	 * - N° de contrat SRM = nouveau contrat (obligatoire)
	 * - Ancienne référence = ancien contrat Redal ; si vide = nouveau contrat sans prédécesseur
	 * - Col. service + nom mosquée pour le rapprochement
	 */
	public function parseExcel(string $path): array {
		$sheet = IOFactory::load($path)->getActiveSheet();
		$raw = $sheet->toArray(null, true, true, false);
		if (count($raw) < 2) {
			return [];
		}

		$header = array_map([$this, 'normalizeHeader'], $raw[0]);
		$colNouveau = $this->findColumn($header, ['contrat srm', 'n de contrat srm', 'n de contrat', 'nouveau contrat'], 0);
		$colAncien = $this->findColumn($header, ['ancienne reference', 'ancien contrat', 'ancien cil', 'ancienne ref'], 1);
		$colService = $this->findColumn($header, ['service principal', 'service'], 2);
		$colMosquee = $this->findColumn($header, ['complement d adresse', 'complement adresse', 'mosquee'], 4);

		$rows = [];
		for ($i = 1, $c = count($raw); $i < $c; $i++) {
			$line = $raw[$i];
			$nouveau = $this->cell($line, $colNouveau);
			$ancien = $this->cell($line, $colAncien);
			if ($nouveau === '' && $ancien === '') {
				continue;
			}
			$rows[] = [
				'line' => $i + 1,
				'reference_nouvelle' => $nouveau,
				'reference_ancienne' => $ancien,
				'service' => $this->cell($line, $colService),
				'mosquee' => $this->cell($line, $colMosquee),
			];
		}
		return $rows;
	}

	public function process(array $rows, array $options = []): array {
		if (!$this->hasLegacyColumn()) {
			throw new \RuntimeException(
				'Colonne reference_ancienne absente. Exécutez database/sql/add_compteur_reference_ancienne.sql'
			);
		}

		$dryRun = !empty($options['dryRun']);
		$mouvrageId = isset($options['mouvrageId']) && $options['mouvrageId'] > 0
			? (int) $options['mouvrageId'] : null;
		$nouveauFournisseurId = $this->resolveFournisseurId($options['nouveauFournisseurNom'] ?? 'SRM');

		$report = [
			'dry_run' => $dryRun,
			'total' => count($rows),
			'ok' => [],
			'skip' => [],
			'nok' => [],
			'nouveaux_contrats' => [],
		];

		foreach ($rows as $row) {
			$nouveau = trim((string) $row['reference_nouvelle']);
			$ancien = trim((string) $row['reference_ancienne']);
			$mosquee = trim((string) ($row['mosquee'] ?? ''));
			$energieId = $this->resolveEnergieId($row['service'] ?? '', $options);
			$isNouveauContrat = ($ancien === '');

			if ($nouveau === '') {
				$report['nok'][] = $this->lineReport($row, 'N° de contrat SRM manquant');
				continue;
			}

			if ($isNouveauContrat) {
				$compteur = $this->findCompteurForNewContract($nouveau, $mosquee, $energieId, $mouvrageId);
				if ($compteur === null) {
					$report['nok'][] = $this->lineReport(
						$row,
						'Nouveau contrat : compteur introuvable (recherche par mosquée « ' . $mosquee . ' » ou n° ' . $nouveau . ')'
					);
					continue;
				}
			} else {
				$compteur = $this->findCompteurByAncienContrat($ancien, $mosquee, $energieId, $mouvrageId);
				if ($compteur === null) {
					$report['nok'][] = $this->lineReport($row, 'Migration : compteur introuvable pour l\'ancien contrat « ' . $ancien . ' »');
					continue;
				}
			}

			$currentRef = trim((string) $compteur->reference);
			$currentLegacy = trim((string) ($compteur->reference_ancienne ?? ''));

			if ($isNouveauContrat) {
				if ($currentRef === $nouveau && $currentLegacy === '') {
					$report['skip'][] = $this->lineReport($row, 'Nouveau contrat déjà en place', $compteur);
					continue;
				}
			} elseif ($currentRef === $nouveau && $currentLegacy === $ancien) {
				$report['skip'][] = $this->lineReport($row, 'Migration déjà effectuée', $compteur);
				continue;
			}

			if (!$dryRun) {
				$previousFournisseurId = (int) ($compteur->fournisseurid ?? 0);
				if (!$isNouveauContrat && $currentLegacy === '') {
					$compteur->reference_ancienne = $ancien;
				}
				$compteur->reference = $nouveau;
				if ($nouveauFournisseurId > 0) {
					$compteur->fournisseurid = $nouveauFournisseurId;
				}
				$compteur->save();

				$srmService = app(CompteurSrmService::class);
				if ($srmService->hasFournisseurAncienColumn() || $srmService->hasLegacyColumn()) {
					$srmService->applyLegacyFields(
						$compteur->fresh(),
						!$isNouveauContrat ? $ancien : null,
						$previousFournisseurId
					);
				}
			}

			$msg = $isNouveauContrat
				? ($dryRun ? 'Simulation OK — nouveau contrat SRM' : 'Nouveau contrat SRM appliqué')
				: ($dryRun ? 'Simulation OK — migration Redal → SRM' : 'Migration Redal → SRM');

			$entry = $this->lineReport(
				$row,
				$msg,
				$compteur,
				[
					'type' => $isNouveauContrat ? 'nouveau_contrat' : 'migration',
					'reference_avant' => $currentRef,
					'reference_apres' => $nouveau,
					'energieid' => $energieId,
				]
			);

			$report['ok'][] = $entry;
			if ($isNouveauContrat) {
				$report['nouveaux_contrats'][] = $entry;
			}
		}

		return $report;
	}

	public function resolveFournisseurId(?string $nom): int {
		if ($nom === null || trim($nom) === '') {
			return 0;
		}
		$like = '%' . Str::lower(trim($nom)) . '%';
		$row = DB::table('fournisseur')
			->where(DB::raw('LOWER(nom)'), 'ILIKE', $like)
			->orderBy('fournisseurid')
			->first();
		return $row ? (int) $row->fournisseurid : 0;
	}

	private function resolveEnergieId(string $service, array $options): int {
		if (!empty($options['energieId'])) {
			return (int) $options['energieId'];
		}
		$s = Str::lower(Str::ascii(trim($service)));
		if (str_contains($s, 'eau')) {
			return (int) Config::get('enertrack.eauID', 5);
		}
		return (int) Config::get('enertrack.electriciteID', 1);
	}

	/** Ancienne référence renseignée → retrouver le compteur Redal existant. */
	private function findCompteurByAncienContrat(string $ancienRef, string $mosqueeName, int $energieId, ?int $mouvrageId): ?Compteurs {
		$variants = $this->referenceVariants($ancienRef);

		$query = Compteurs::query()
			->where('energieid', $energieId)
			->where(function ($q) {
				$q->where('clos', 0)->orWhereNull('clos');
			})
			->where(function ($q) use ($variants, $ancienRef) {
				foreach ($variants as $variant) {
					$q->orWhere(DB::raw('LOWER(reference)'), '=', $variant);
					if ($this->hasLegacyColumn()) {
						$q->orWhere(DB::raw('LOWER(COALESCE(reference_ancienne, \'\'))'), '=', $variant);
					}
				}
				$core = $this->referenceCoreDigits($ancienRef);
				if ($core !== '') {
					$q->orWhere(DB::raw('LOWER(reference)'), 'LIKE', '%' . Str::lower($core) . '%');
				}
			});

		if ($mouvrageId !== null) {
			$query->where('mouvrageid', $mouvrageId);
		}

		$matches = $query->get();
		if ($matches->count() >= 1) {
			return $matches->first();
		}

		return $this->findCompteurByMosquee($mosqueeName, $energieId, $mouvrageId);
	}

	/** Ancienne référence vide → nouveau contrat : chercher par n° SRM ou nom mosquée. */
	private function findCompteurForNewContract(string $nouveauContrat, string $mosqueeName, int $energieId, ?int $mouvrageId): ?Compteurs {
		$variants = $this->referenceVariants($nouveauContrat);

		$query = Compteurs::query()
			->where('energieid', $energieId)
			->where(function ($q) {
				$q->where('clos', 0)->orWhereNull('clos');
			})
			->where(function ($q) use ($variants) {
				foreach ($variants as $variant) {
					$q->orWhere(DB::raw('LOWER(reference)'), '=', $variant);
				}
			});

		if ($mouvrageId !== null) {
			$query->where('mouvrageid', $mouvrageId);
		}

		$matches = $query->get();
		if ($matches->count() >= 1) {
			return $matches->first();
		}

		return $this->findCompteurByMosquee($mosqueeName, $energieId, $mouvrageId);
	}

	private function findCompteurByMosquee(string $mosqueeName, int $energieId, ?int $mouvrageId): ?Compteurs {
		if ($mosqueeName === '') {
			return null;
		}

		$like = '%' . Str::lower(trim($mosqueeName)) . '%';
		$fallback = Compteurs::query()
			->select('compteur.*')
			->join('compteurbatiments', 'compteurbatiments.compteurid', '=', 'compteur.compteurid')
			->join('batiment', 'batiment.batimentid', '=', 'compteurbatiments.batimentid')
			->where('compteur.energieid', $energieId)
			->where(function ($q) {
				$q->where('compteur.clos', 0)->orWhereNull('compteur.clos');
			})
			->where(function ($q) use ($like) {
				$q->where(DB::raw('LOWER(batiment.nom)'), 'ILIKE', $like)
					->orWhere(DB::raw('LOWER(COALESCE(batiment.nom_alternatif, \'\'))'), 'ILIKE', $like);
			});

		if ($mouvrageId !== null) {
			$fallback->where('compteur.mouvrageid', $mouvrageId);
		}

		$fb = $fallback->get();
		return $fb->count() >= 1 ? $fb->first() : null;
	}

	private function lineReport(array $row, string $message, ?Compteurs $compteur = null, array $extra = []): array {
		$out = [
			'line' => $row['line'],
			'reference_ancienne' => $row['reference_ancienne'],
			'reference_nouvelle' => $row['reference_nouvelle'],
			'mosquee' => $row['mosquee'] ?? '',
			'service' => $row['service'] ?? '',
			'message' => $message,
		];
		if ($compteur !== null) {
			$out['compteurid'] = $compteur->compteurid;
			$out['reference_actuelle'] = $compteur->reference;
		}
		return array_merge($out, $extra);
	}

	private function normalizeHeader($value): string {
		$s = Str::lower(trim((string) $value));
		$s = Str::ascii($s);
		$s = preg_replace('/[^a-z0-9 ]+/', ' ', $s);
		return trim(preg_replace('/\s+/', ' ', $s));
	}

	private function findColumn(array $header, array $needles, int $fallbackIndex): int {
		foreach ($header as $idx => $label) {
			foreach ($needles as $needle) {
				if ($label !== '' && str_contains($label, $needle)) {
					return (int) $idx;
				}
			}
		}
		return $fallbackIndex;
	}

	private function cell(array $line, int $index): string {
		return isset($line[$index]) ? trim((string) $line[$index]) : '';
	}

	/** BE_0006968903, BO_xxx et 6968903 → variantes pour rapprochement base TBGE. */
	private function referenceVariants(string $ref): array {
		$ref = trim($ref);
		$variants = [Str::lower($ref)];
		$core = $this->referenceCoreDigits($ref);
		if ($core !== '') {
			$variants[] = Str::lower($core);
			$variants[] = Str::lower(ltrim($core, '0'));
			$variants[] = Str::lower('BE_' . str_pad(ltrim($core, '0'), 10, '0', STR_PAD_LEFT));
			$variants[] = Str::lower('BO_' . str_pad(ltrim($core, '0'), 10, '0', STR_PAD_LEFT));
		}
		return array_values(array_unique(array_filter($variants)));
	}

	private function referenceCoreDigits(string $ref): string {
		$ref = trim($ref);
		if (preg_match('/^(?:BE_|BO_)?0*(.+)$/i', $ref, $m)) {
			return preg_replace('/\D/', '', $m[1]) ?: $m[1];
		}
		return preg_replace('/\D/', '', $ref);
	}

}
