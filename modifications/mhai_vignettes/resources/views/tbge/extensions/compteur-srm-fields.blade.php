{{-- Extension SRM : affichage en plus du formulaire compteur existant --}}
@if(isset($compteur) && (!empty($compteur->reference_ancienne) || !empty($compteur->fournisseur_ancien_id)))
@php
    $ancienFournNom = '';
    if (!empty($compteur->fournisseur_ancien_id)) {
        $ancienFournNom = \DB::table('fournisseur')->where('fournisseurid', $compteur->fournisseur_ancien_id)->value('nom');
    }
@endphp
@if(!empty($ancienFournNom))
<div class="form-group tbge-form-group">
    <label style="margin-top: 0px !important; padding-top:10px !important;" class="col-md-3 control-label tbge-form-label">Ancien fournisseur</label>
    <div class="col-md-4">
        <p class="form-control-static text-muted" style="padding-top: 8px;">{{ $ancienFournNom }}</p>
    </div>
</div>
@endif
@if(!empty($compteur->reference_ancienne))
<div class="form-group tbge-form-group">
    <label style="margin-top: 0px !important; padding-top:10px !important;" class="col-md-3 control-label tbge-form-label">Ancien n° contrat</label>
    <div class="col-md-4">
        <p class="form-control-static text-muted" style="padding-top: 8px;">{{ $compteur->reference_ancienne }}</p>
    </div>
</div>
@endif
@endif
