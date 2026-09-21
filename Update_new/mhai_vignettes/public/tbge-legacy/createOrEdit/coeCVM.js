var validationErrorsMessages = {
	fr_FR : {
		Numero:"Veuillez fournir un numéro de compteur",
		Reference:"Veuillez renseigner l'ID Lieu (n° contrat / police)",
		ContratSrm:"Veuillez renseigner le n° de contrat SRM",
		
		CompteurID : "",
		
		BatimentID : "Veuillez préciser la mosquée associée à ce compteur",
		EnergieID : "Veuillez préciser le type de consommation du compteur",

		FournisseurID : "Veuillez préciser le fournisseur du compteur"
	},
	ar_MA : {
		Numero:"يرجى تقديم رقم متر",
		Reference:"يرجى إدخال معرف الموقع",
		ContratSrm:"يرجى إدخال رقم عقد SRM",
		
		CompteurID : "",
		
		BatimentID : "يرجى تحديد المسجد المرتبط بهذا المقياس",
		EnergieID : "يرجى تحديد نوع استهلاك العداد",

		FournisseurID : "يرجى تحديد المورد متر"
	}
};

ko.validation.rules['ReferenceValid'] = {
    validator: function (val, params) {
        return true;
    },
    message: validationErrorsMessages[(mainApp && mainApp.locale && mainApp.locale == 'fr-FR') ? 'fr_FR':'ar_MA'].Reference,
};

ko.validation.registerExtenders();

coeCVM = function () {
	var self = this;
	
	self.isMultiDelegations = ko.observable(mainApp.isMultiDelegations());

	var datesFields = [];

	var select2ConfigsKeys = [
		"TypeFacturationID",
		"FournisseurID",
		"TypeTarificationID",
		"EnergieID"
	];

	var select2ConfigsFields = {
		
		TypeFacturationID : {
        	allowClear: true
    	},
    	FournisseurID : {
        	allowClear: true,
        	placeholder: "Fournisseur"
    	},
    	TypeTarificationID : {
        	allowClear: true
    	},
    	EnergieID : {
        	allowClear: true
    	}
	}
	
	self.disableMosqueeSelect = ko.observable(false);
	
	var rawData = $("#rawData") == null || $("#rawData").length <= 0 ? null : $.parseJSON($("#rawData").text());
	
	self.selectedBatiment = ko.observable($('#BatimentID') ? 1*$('#BatimentID').text() : null);
	self.selectedBatimentName = ko.observable($('#BatimentName') ? $('#BatimentName').text() : null);

	var filteredFields = [ "pFilterMosquee", "EnergieID", "FournisseurID", "TypeFacturationID", "TypeTarificationID"];

	self.filterParams = ko.observable(new RechercheMultiCriteres(filteredFields));

	var oldBatiment = null;
	if(rawData != null && rawData.compteur_batiments != null && 1*rawData.compteur_batiments.BatimentID > 0)
		oldBatiment = 1*rawData.compteur_batiments.BatimentID;

	if(oldBatiment == null || oldBatiment == "")
		oldBatiment = self.selectedBatiment();

	self.selectedEnergie = ko.observable($('#EnergieID') ? 1*$('#EnergieID').text() : null);
	self.selectedEnergieName = ko.observable(null);
	
	var fields = {
		
		CompteurID : ko.observable(rawData == null ? -1 : rawData.compteurid),

		Numero : ko.observable(rawData == null ? null : rawData.numero).extend({
			required: {
				params : true,
				message : validationErrorsMessages[(mainApp && mainApp.locale && mainApp.locale == 'fr-FR') ? 'fr_FR':'ar_MA'].Numero
			}
		}),
		
		Reference : ko.observable(rawData == null ? null : rawData.reference).extend({
			required: {
				onlyIf: function () {
					return !(self.isSrmFournisseur && self.isSrmFournisseur());
				},
				message : validationErrorsMessages[(mainApp && mainApp.locale && mainApp.locale == 'fr-FR') ? 'fr_FR':'ar_MA'].Reference
			}
		}),

		ContratSrm : ko.observable('').extend({
			required: {
				onlyIf: function () {
					return !!(self.isSrmFournisseur && self.isSrmFournisseur());
				},
				message : validationErrorsMessages[(mainApp && mainApp.locale && mainApp.locale == 'fr-FR') ? 'fr_FR':'ar_MA'].ContratSrm
			}
		}),
		
		OldBatimentID : ko.observable(oldBatiment),

		BatimentID : ko.observable(oldBatiment).extend({
			min : 1,
			number : true,
			required : true
		}),
		
		EnergieID : ko.observable(rawData == null ? self.selectedEnergie() : rawData.energieid).extend({
			min : 1,
			number : true,
			required : true
		}),
		
		FournisseurID : ko.observable(rawData == null ? null : rawData.fournisseurid).extend({
			min : 1,
			number : true,
			required : true
		}),

		TypeFacturationID : ko.observable(rawData == null ? null : rawData.typefactureid).extend({
			required : true
		}),

		TypeTarificationID : ko.observable(rawData == null ? null : rawData.typetarificationid).extend({
			required : true
		}),

		Etat : ko.observable(rawData == null ? null : rawData.etat_compteur == 1 ? 1 : '0'),

	};

	coeViewModel.call(self, fields, datesFields, select2ConfigsKeys, select2ConfigsFields, "Ajouter un compteur");
	
	if(rawData != null && rawData.FournisseurID != null && rawData.FournisseurID != "")
		$("#FournisseurID").val(rawData.fournisseurid).trigger('change');
	
	if(rawData != null && rawData.typeFacturationid != null && rawData.typeFacturationid != "")
		$("#TypeFacturationID").val(rawData.TypeFactureID).trigger('change');

	if(rawData != null && rawData.typetarificationid != null && rawData.typetarificationid != "")
		$("#TypeTarificationID").val(rawData.typetarificationid).trigger('change');

	self.rawData = ko.observable(rawData);

	self.getSelectedFournisseurName = function () {
		var $sel = $('#FournisseurID');
		if (!$sel.length) {
			return '';
		}
		var fromOption = (($sel.find('option:selected').text() || '') + '').trim();
		if (fromOption !== '') {
			return fromOption;
		}
		try {
			if ($sel.data('select2')) {
				var data = $sel.select2('data');
				if (data && data.length && data[0] && data[0].text) {
					return ('' + data[0].text).trim();
				}
			}
		} catch (e) {}
		return '';
	};

	self.isSrmFournisseur = function () {
		var nom = self.getSelectedFournisseurName();
		if (!nom) {
			return false;
		}
		return /(^|[\s\-_/])srm($|[\s\-_/])/i.test(nom) || /^srm$/i.test(nom.trim());
	};

	self.updateLabelsForFournisseur = function () {
		var nom = self.getSelectedFournisseurName();
		var nomLower = (nom || '').toLowerCase();
		var isSrm = self.isSrmFournisseur();
		var $label = $('#label-id-lieu');
		var $hint = $('#hint-id-lieu');
		var $input = $('#Reference');
		var $blocSrm = $('#bloc-contrat-srm');

		if (isSrm) {
			$blocSrm.show();
		} else {
			$blocSrm.hide();
			$('#ContratSrm').val('');
		}

		if (!$label.length) {
			return;
		}

		if (!nom) {
			$label.html('ID Lieu <span class="text-danger">*</span>');
			if ($hint.length) {
				$hint.text('Choisissez d’abord un fournisseur, puis saisissez l’identifiant du contrat.');
			}
			$input.attr('placeholder', 'N° contrat / police / CIL…');
			return;
		}

		if (isSrm) {
			$label.html('ID Lieu (ancien n° contrat)');
			if ($hint.length) {
				$hint.text('Ancien n° contrat / police / CIL (ONEE, Redal, Lydec…). Optionnel si nouveau contrat SRM.');
			}
			$input.attr('placeholder', 'Ancien n° contrat (optionnel)…');
			return;
		}

		var suffix = 'contrat ' + nom;
		var placeholder = 'N° contrat / police / CIL…';
		var hint = 'Saisissez le n° de contrat / police / CIL chez ' + nom + '.';

		if (/redal/i.test(nomLower)) {
			suffix = 'CIL Redal';
			placeholder = 'Ex. BE_0001234567';
			hint = 'Saisissez le CIL Redal (ex. BE_… / BO_…).';
		} else if (/lydec/i.test(nomLower)) {
			suffix = 'contrat Lydec';
			placeholder = 'N° contrat Lydec…';
			hint = 'Saisissez le n° de contrat / police Lydec.';
		} else if (/onee/i.test(nomLower)) {
			suffix = 'contrat ONEE';
			placeholder = 'N° contrat ONEE…';
			hint = 'Saisissez le n° de contrat / police ONEE.';
		} else if (/amendis/i.test(nomLower)) {
			suffix = 'contrat Amendis';
			placeholder = 'N° contrat Amendis…';
			hint = 'Saisissez le n° de contrat / police Amendis.';
		}

		$label.html('ID Lieu (' + suffix + ') <span class="text-danger">*</span>');
		if ($hint.length) {
			$hint.text(hint);
		}
		$input.attr('placeholder', placeholder);
	};

	self.prefillSrmFieldsFromRawData = function () {
		var data = self.rawData && self.rawData();
		if (!data || !self.isSrmFournisseur()) {
			return;
		}
		var ancienne = ((data.reference_ancienne || '') + '').trim();
		var ref = ((data.reference || '') + '').trim();
		if (ref) {
			self.fields().ContratSrm(ref);
			$('#ContratSrm').val(ref);
		}
		if (ancienne) {
			self.fields().Reference(ancienne);
			$('#Reference').val(ancienne);
		} else if (ref) {
			// référence = contrat SRM : ne pas le dupliquer dans ID Lieu
			self.fields().Reference('');
			$('#Reference').val('');
		}
	};

	self.filterParams().parametres()['pFilterMosquee']().valeur.subscribe(function (newValue) {
		self.fields().BatimentID(newValue);
	});

	self.disableMosqueeSelect.subscribe(function (newValue) {
		$("#pFilterMosquee").attr("disabled", "disabled");
	});

	const urlParams = new URLSearchParams(window.location.search);
	if(urlParams.get('EnergieID')){
		self.fields().EnergieID(urlParams.get('EnergieID'));
		$('#EnergieID').trigger('change');
		$("#EnergieID").attr("disabled", "disabled");
	}

}

coeCVM.prototype = mainApp.createObject(coeViewModel.prototype);
coeCVM.prototype.constructor = coeCVM;

coeCVM.prototype.start = function() {
	var self = this;

	var simpleFilters = {
		EnergieID : "Choisir énergie",
		FournisseurID : "Fournisseur",
		TypeFacturationID : "Type de facturation",
		TypeTarificationID : "Type de tarification"
	}

	for(filter in simpleFilters){
		$("#"+filter).select2({
			placeholder : simpleFilters[filter],
			allowClear : true,
			width: '100%',
			minimumResultsForSearch: 0
		});
	}

	$("#FournisseurID")
		.off('change.coeSrm select2:select.coeSrm select2:clear.coeSrm select2:close.coeSrm')
		.on('change.coeSrm select2:select.coeSrm select2:clear.coeSrm select2:close.coeSrm', function () {
			setTimeout(function () {
				var raw = $('#FournisseurID').val();
				var n = raw ? (1 * raw) : null;
				self.fields().FournisseurID((n && !isNaN(n) && n > 0) ? n : null);
				self.updateLabelsForFournisseur();
				if (self.fields().Reference && self.fields().Reference.isModified) {
					self.fields().Reference.valueHasMutated();
				}
				if (self.fields().ContratSrm && self.fields().ContratSrm.valueHasMutated) {
					self.fields().ContratSrm.valueHasMutated();
				}
			}, 0);
		});
	self.updateLabelsForFournisseur();
	self.prefillSrmFieldsFromRawData();

	self.initMosqueeSelect('pFilterMosquee');

	if(self.selectedBatiment() > 0){
		self.fields().BatimentID(self.selectedBatiment());
		self.filterParams().parametres()['pFilterMosquee']().valeur(self.selectedBatiment());
		$("#pFilterMosquee").trigger('change');
	}
		
}


coeCVM.prototype.initMosqueeSelect = function(inputId) {
	var self = this;
	
	var mosqueeSelect = new pFilterMosqueeSelect(inputId, null);
	mosqueeSelect.init();
	
	const urlParams = new URLSearchParams(window.location.search);

	if(urlParams.get('BatimentID')){
		self.disableMosqueeSelect(true);
		setTimeout(function () {
			var $option = $("<option value='" + (1*urlParams.get('BatimentID')) + "'>" + self.selectedBatimentName() + "</option>").val(1*urlParams.get('BatimentID'));
			$('#pFilterMosquee').append($option).trigger('change');
		}, 150);	
		return;
	}

	var compteurId = self.fields().CompteurID();

	/*
	if(compteurId > 0){
		
		self.fields().BatimentID(self.rawData().compteur_batiments.batiment.BatimentID);
		var $option = $("<option value='" + self.fields().BatimentID() + "'>" + self.rawData().compteur_batiments.batiment.Nom + "</option>").val(1*self.selectedBatiment());
		$('#pFilterMosquee').append($option).trigger('change');
	}
	*/
	
	if(compteurId > 0){
		setTimeout(function () {
			
			var $option = $("<option value='" 
						+ self.fields().BatimentID() + "'>" 
						+ (self.rawData().compteur_batiments.batiment.Nom) 
						+ "</option>").val(self.fields().BatimentID());
			if(self.fields().BatimentID() > 0)	
				$('#pFilterMosquee').append($option).trigger('change');
		
		}, 150);

		if(self.rawData().compteur_batiments && self.rawData().compteur_batiments.batiment.BatimentID)	
			self.fields().BatimentID(self.rawData().compteur_batiments.batiment.BatimentID);

		/*
		var state =  {
			id: self.fields().BatimentID(), 
			batimentid: self.fields().BatimentID(), 
			text:self.rawData().compteur_batiments.batiment.Nom,
			numeronational: self.rawData().compteur_batiments.batiment.NumeroNational, 
			nommosquee: self.rawData().compteur_batiments.batiment.Nom, 
			nomdelegation: ""
		};

		mosqueeSelect.setState(state);
		*/

	}
	

};