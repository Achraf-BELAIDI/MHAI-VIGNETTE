(function ($) {
	'use strict';

	var cfg = window.TBGE_SRM || {};
	var tbgeBase = (cfg.tbgeBase || '/tbge').replace(/\/$/, '');
	var mosqueeEditPattern = new RegExp(
		'^' + tbgeBase.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + '/patrimoine/batiment/\\d+/edit/?$'
	);

	if (!mosqueeEditPattern.test(window.location.pathname)) {
		return;
	}

	function readRawData() {
		var raw = $('#rawData').text();
		if (!raw) {
			return null;
		}
		try {
			return JSON.parse(raw);
		} catch (e) {
			return null;
		}
	}

	function readBatimentId() {
		var data = readRawData();
		if (!data) {
			return null;
		}
		return data.batimentid || data.BatimentID || null;
	}

	function readToken() {
		return $('#_token').text()
			|| $('input[name="_token"]').val()
			|| $('meta[name="csrf-token"]').attr('content')
			|| '';
	}

	function extractCompteurId(href) {
		var match = (href || '').match(/\/compteur\/(\d+)\/edit/i);
		return match ? parseInt(match[1], 10) : null;
	}

	function isSrmFournisseurName(nom) {
		nom = (nom || '').trim();
		if (!nom) {
			return false;
		}
		return /\bsrm\b/i.test(nom);
	}

	function isSrmItem(item) {
		return !!(item && isSrmFournisseurName(item.fournisseur_nom));
	}

	function getRefCellText($row) {
		// Après décoration : [edit, fourn, ref, ancien_fourn, ancien_contrat, numero, …]
		var $fournAncien = $row.find('.tbge-srm-col-ancien-fourn');
		if ($fournAncien.length) {
			return ($fournAncien.prev('td').text() || '').trim();
		}
		return ($row.find('td').eq(2).text() || '').trim();
	}

	function getFournisseurCellText($row) {
		return ($row.find('td').eq(1).text() || '').trim();
	}

	/**
	 * Ancien n° contrat :
	 * 1) reference_ancienne si présent
	 * 2) sinon n° actuel si fournisseur ≠ SRM
	 * 3) sinon cellule contrat de la ligne
	 */
	function getAncienContratDisplay(item, $row) {
		if (item) {
			var ancienne = String(item.reference_ancienne || '').trim();
			if (ancienne) {
				return ancienne;
			}
			if (!isSrmItem(item)) {
				var ref = String(item.reference || '').trim();
				if (ref) {
					return ref;
				}
			}
		}
		if ($row && $row.length) {
			var fournNom = (item && item.fournisseur_nom) || getFournisseurCellText($row);
			if (!isSrmFournisseurName(fournNom)) {
				return getRefCellText($row);
			}
		}
		return '';
	}

	/**
	 * Ancien fournisseur :
	 * 1) fournisseur_ancien_nom si migration déjà faite
	 * 2) sinon fournisseur actuel si ≠ SRM (sera conservé)
	 * 3) sinon texte cellule Fournisseur
	 */
	function getAncienFournisseurDisplay(item, $row) {
		if (item) {
			var ancienNom = String(item.fournisseur_ancien_nom || '').trim();
			if (ancienNom) {
				return ancienNom;
			}
			if (!isSrmItem(item)) {
				var courant = String(item.fournisseur_nom || '').trim();
				if (courant) {
					return courant;
				}
			}
		}
		if ($row && $row.length) {
			var fournNom = getFournisseurCellText($row);
			if (!isSrmFournisseurName(fournNom)) {
				return fournNom;
			}
		}
		return '';
	}

	function ensureModal() {
		if ($('#tbge-srm-overlay').length) {
			return;
		}

		$('body').append(
			'<div id="tbge-srm-overlay" class="tbge-srm-overlay" aria-hidden="true">' +
				'<div class="tbge-srm-modal" role="dialog">' +
					'<h4>Migration contrat SRM</h4>' +
					'<p class="help">SRM est le nouveau fournisseur. L\'ancien fournisseur et l\'ancien n° de contrat sont conservés automatiquement.</p>' +
					'<label for="tbge-srm-reference">Nouveau contrat SRM</label>' +
					'<input id="tbge-srm-reference" class="form-control" maxlength="50" placeholder="Ex. 1411112">' +
					'<label for="tbge-srm-ancien-fourn">Ancien fournisseur (lecture seule)</label>' +
					'<input id="tbge-srm-ancien-fourn" class="form-control" maxlength="100" readonly>' +
					'<label for="tbge-srm-ancien">Ancien n° contrat (lecture seule)</label>' +
					'<input id="tbge-srm-ancien" class="form-control" maxlength="50" readonly>' +
					'<div class="tbge-srm-modal-actions">' +
						'<button type="button" class="btn btn-default tbge-srm-cancel">Annuler</button>' +
						'<button type="button" class="btn btn-success tbge-srm-save">Enregistrer</button>' +
					'</div>' +
				'</div>' +
			'</div>'
		);

		$(document).on('click', '.tbge-srm-cancel, #tbge-srm-overlay', function (e) {
			if (e.target.id === 'tbge-srm-overlay' || $(e.target).hasClass('tbge-srm-cancel')) {
				closeModal();
			}
		});

		$(document).on('click', '.tbge-srm-save', saveModal);
	}

	var state = {
		compteurs: {},
		batimentId: null,
		activeCompteurId: null,
	};

	function normalizeCompteur(c) {
		var id = parseInt(c.compteurid || c.CompteurID || c.id || 0, 10);
		if (!id) {
			return null;
		}
		var fourn = c.fournisseur || c.Fournisseur || {};
		return {
			compteurid: id,
			reference: String(c.reference || c.Reference || ''),
			reference_ancienne: String(c.reference_ancienne || c.ReferenceAncienne || ''),
			numero: String(c.numero || c.Numero || ''),
			energieid: parseInt(c.energieid || c.EnergieID || 0, 10) || 0,
			fournisseurid: parseInt(c.fournisseurid || c.FournisseurID || 0, 10) || 0,
			fournisseur_nom: String(c.fournisseur_nom || fourn.nom || fourn.Nom || ''),
			fournisseur_ancien_id: parseInt(c.fournisseur_ancien_id || 0, 10) || 0,
			fournisseur_ancien_nom: String(c.fournisseur_ancien_nom || ''),
		};
	}

	function seedFromRawData() {
		var data = readRawData();
		if (!data) {
			return;
		}
		[].concat(data.compteurs_electricite || [], data.compteurs_eau || []).forEach(function (c) {
			var item = normalizeCompteur(c);
			if (!item) {
				return;
			}
			state.compteurs[item.compteurid] = $.extend({}, state.compteurs[item.compteurid] || {}, item);
		});
	}

	function openModal(compteurId) {
		var item = state.compteurs[compteurId];
		if (!item) {
			return;
		}

		state.activeCompteurId = compteurId;
		$('#tbge-srm-reference').val(isSrmItem(item) ? (item.reference || '') : '');
		$('#tbge-srm-ancien-fourn').val(getAncienFournisseurDisplay(item, null));
		$('#tbge-srm-ancien').val(getAncienContratDisplay(item, null));
		$('#tbge-srm-overlay').addClass('is-open').attr('aria-hidden', 'false');
	}

	function closeModal() {
		state.activeCompteurId = null;
		$('#tbge-srm-overlay').removeClass('is-open').attr('aria-hidden', 'true');
	}

	function saveModal() {
		var compteurId = state.activeCompteurId;
		if (!compteurId) {
			return;
		}

		var referenceSrm = ($('#tbge-srm-reference').val() || '').trim();
		if (!referenceSrm) {
			alert('Le n° de contrat SRM est obligatoire.');
			return;
		}

		$.ajax({
			url: tbgeBase + '/api/compteur/' + compteurId + '/srm',
			method: 'POST',
			headers: { 'X-CSRF-TOKEN': readToken() },
			data: {
				_token: readToken(),
				batiment_id: state.batimentId,
				reference_srm: referenceSrm,
				reference_ancienne: ($('#tbge-srm-ancien').val() || '').trim(),
			},
		}).done(function (resp) {
			if (resp && resp.compteur) {
				var merged = normalizeCompteur($.extend({}, state.compteurs[compteurId] || {}, resp.compteur));
				if (merged) {
					if (!merged.fournisseur_nom) {
						merged.fournisseur_nom = 'SRM';
					}
					// Si l'API n'a pas encore la colonne, garder le nom affiché dans le modal
					if (!merged.fournisseur_ancien_nom) {
						merged.fournisseur_ancien_nom = ($('#tbge-srm-ancien-fourn').val() || '').trim();
					}
					state.compteurs[compteurId] = merged;
				}
				refreshRows();
			}
			closeModal();
		}).fail(function (xhr) {
			var msg = (xhr.responseJSON && xhr.responseJSON.message) ? xhr.responseJSON.message : 'Erreur lors de l\'enregistrement.';
			alert(msg);
		});
	}

	function tableHasAnySrm($table) {
		var found = false;
		$table.find('tbody tr').each(function () {
			var $row = $(this);
			var compteurId = extractCompteurId($row.find('a.glyphicon-pencil').attr('href'));
			var item = compteurId ? state.compteurs[compteurId] : null;
			var nom = (item && item.fournisseur_nom) || getFournisseurCellText($row);
			if (isSrmFournisseurName(nom)) {
				found = true;
				return false;
			}
		});
		return found;
	}

	function decorateTables() {
		$('#page-wrapper table.table-bordered').each(function () {
			var $table = $(this);
			var $headerRow = $table.find('tr').first();

			if (!$headerRow.find('.tbge-srm-col-header').length) {
				if (tableHasAnySrm($table)) {
					$headerRow.find('th').eq(2).html('Contrat SRM <span class="tbge-srm-badge-srm">SRM</span>');
				}
				$('<th class="compteur-cols tbge-srm-col-header">Ancien fournisseur</th>')
					.insertAfter($headerRow.find('th').eq(2));
				$('<th class="compteur-cols tbge-srm-col-header-contrat">Ancien n° contrat</th>')
					.insertAfter($headerRow.find('th').eq(3));
			}

			$table.find('tbody tr').each(function () {
				var $row = $(this);
				var compteurId = extractCompteurId($row.find('a.glyphicon-pencil').attr('href'));
				var item = compteurId ? state.compteurs[compteurId] : null;

				if (!item && compteurId) {
					item = {
						compteurid: compteurId,
						reference: getRefCellText($row),
						reference_ancienne: '',
						fournisseur_nom: getFournisseurCellText($row),
						fournisseur_ancien_nom: '',
					};
					state.compteurs[compteurId] = item;
				}

				var ancienFourn = getAncienFournisseurDisplay(item, $row);
				var ancienContrat = getAncienContratDisplay(item, $row);
				var $fournTd = $row.find('.tbge-srm-col-ancien-fourn');
				var $contratTd = $row.find('.tbge-srm-col-ancien');

				if (!$fournTd.length) {
					$fournTd = $('<td class="tbge-srm-col-ancien-fourn"></td>')
						.text(ancienFourn)
						.insertAfter($row.find('td').eq(2));
				} else if ($fournTd.text() !== ancienFourn) {
					$fournTd.text(ancienFourn);
				}

				if (!$contratTd.length) {
					$('<td class="tbge-srm-col-ancien"></td>')
						.text(ancienContrat)
						.insertAfter($row.find('.tbge-srm-col-ancien-fourn'));
				} else if ($contratTd.text() !== ancienContrat) {
					$contratTd.text(ancienContrat);
				}

				if (compteurId && !$row.find('.tbge-srm-btn').length) {
					$row.find('td').first().append(
						'<button type="button" class="btn btn-xs btn-info tbge-srm-btn" data-compteur-id="' +
						compteurId +
						'" title="Migrer / mettre à jour le contrat SRM">SRM</button>'
					);
				}
			});
		});

		$(document).off('click.tbgeSrm').on('click.tbgeSrm', '.tbge-srm-btn', function () {
			openModal(parseInt($(this).data('compteur-id'), 10));
		});
	}

	function refreshRows() {
		$('#page-wrapper table.table-bordered tbody tr').each(function () {
			var $row = $(this);
			var compteurId = extractCompteurId($row.find('a.glyphicon-pencil').attr('href'));
			if (!compteurId || !state.compteurs[compteurId]) {
				return;
			}
			var item = state.compteurs[compteurId];
			var $refTd = $row.find('.tbge-srm-col-ancien-fourn').prev('td');
			if ($refTd.length && $refTd.text() !== (item.reference || '')) {
				$refTd.text(item.reference || '');
			}
			var ancienFourn = getAncienFournisseurDisplay(item, $row);
			var ancienContrat = getAncienContratDisplay(item, $row);
			var $fournTd = $row.find('.tbge-srm-col-ancien-fourn');
			var $contratTd = $row.find('.tbge-srm-col-ancien');
			if ($fournTd.length && $fournTd.text() !== ancienFourn) {
				$fournTd.text(ancienFourn);
			}
			if ($contratTd.length && $contratTd.text() !== ancienContrat) {
				$contratTd.text(ancienContrat);
			}
			if (item.fournisseur_nom) {
				var $fournCourant = $row.find('td').eq(1);
				if ($fournCourant.text() !== item.fournisseur_nom) {
					$fournCourant.text(item.fournisseur_nom);
				}
			}
		});

		$('#page-wrapper table.table-bordered').each(function () {
			var $table = $(this);
			if (tableHasAnySrm($table)) {
				var $th = $table.find('tr').first().find('th').eq(2);
				if ($th.find('.tbge-srm-badge-srm').length === 0) {
					$th.html('Contrat SRM <span class="tbge-srm-badge-srm">SRM</span>');
				}
			}
		});
	}

	function loadData() {
		state.batimentId = readBatimentId();
		seedFromRawData();
		decorateTables();

		if (!state.batimentId) {
			return;
		}

		$.getJSON(tbgeBase + '/api/batiment/' + state.batimentId + '/compteurs-srm')
			.done(function (resp) {
				if (!resp || !resp.enabled) {
					return;
				}
				(resp.compteurs || []).forEach(function (c) {
					var item = normalizeCompteur(c);
					if (!item) {
						return;
					}
					state.compteurs[item.compteurid] = $.extend({}, state.compteurs[item.compteurid] || {}, item);
				});
				decorateTables();
				refreshRows();
			});
	}

	function initWhenReady() {
		if (!$('#page-wrapper').length) {
			return;
		}
		ensureModal();
		loadData();

		var decorating = false;
		var debounceTimer = null;
		var observer = new MutationObserver(function () {
			if (decorating) {
				return;
			}
			clearTimeout(debounceTimer);
			debounceTimer = setTimeout(function () {
				decorating = true;
				try {
					decorateTables();
				} finally {
					decorating = false;
				}
			}, 80);
		});
		var target = document.querySelector('#page-wrapper');
		if (target) {
			observer.observe(target, { childList: true, subtree: true });
		}
	}

	$(initWhenReady);
})(jQuery);
