<?php
/**
 * login.blade.php 
 * {File description}
 * 
 * @author tapsaid
 * @created 2019
 * 
 */

    $proxy_url = Config::get('enertrack.proxy_url');
    if(!empty($proxy_url)){
        URL::forceSchema('https');
        URL::forceRootUrl(Config::get('enertrack.proxy_url'));
    }
?>
{{-- Page template --}}
@extends('tbge.templates.normal')

{{-- Page title --}}
@section('title') Compteur @stop

@section('css')
    <link rel="stylesheet" href='/assets/jquery-ui-1.11.2/themes/base/all.css'>
    <link rel="stylesheet" href="/assets/select2/select2.min.css"></script>

    <style type="text/css">
        
        @media screen and (max-width: 420px) {
          .padding-xs {
            padding-right: 15px;
          }
        }
        
        @media screen and (min-width: 421px) {
          .padding-xs {
            padding-right: 5px;
          }
        }

    </style>
@stop

@section('scripts')
    <script lang="javascript" src='/assets/select2/select2.full.min.js'></script>
    <script lang="javascriptja" src='/assets/select2/i18n/fr.js'></script>

    <script lang="javascript" src='/assets/knockout/validation/dist/knockout.validation.min.js'></script>
    <script lang="javascript" src='/assets/knockout/validation/localization/fr-FR.js'></script>


    <script type="text/javascript" src="/tbge-legacy/commons/ParametreRechercheMultiCriteres.js"></script>
    <script type="text/javascript" src="/tbge-legacy/commons/RechercheMultiCriteres.js"></script>
    <script type="text/javascript" src="/tbge-legacy/commons/pFilterMosqueeSelect.js"></script>

    <script lang="" src='/tbge-legacy/createOrEdit/coeViewModel.js?v=20240206'></script>
    <script lang="" src='/tbge-legacy/createOrEdit/coeCVM.js?v=20260921anc3'></script>


    <script>
        
        $(document).ready(function() {
            
            var vm = new coeCVM();
            ko.applyBindings(vm,document.getElementById('page-wrapper'));
            window.mvVm = vm;

            vm.start();

        });

    </script>
@stop


<?php 
       
    $currentLang = "fr";

    $typeFacturations = [(object)[
        "TypeFacturationID" => 1,
        "Nom" => "Facture sur Consommation",
    ], (object)[
            "TypeFacturationID" => 2,
            "Nom" => "Facture Prépayée",
        ]];

        $typeTarifications = [(object)[
            "TypeTarificationID" => 1,
            "Nom" => "Tarif administration",
        ], (object)[
            "TypeTarificationID" => 2,
            "Nom" => "Tarif Privé",
        ]];
?>

{{-- Page content --}}
@section('content')

<div id="page-wrapper" style="margin-top: 60px;">
    
    <div class="row">
        
        <div class="col-md-10 col-md-offset-1">
            <h1 class="tbge-page-title"> 
                <span data-bind="text:$root.fields().CompteurID()<=0 ? 'Ajouter':'Modifier'"></span> un compteur
            </h1>
            <hr class="tbge-page-title-separator">
        </div>
    
        {{ Form::open(array('url' => URL::to('tbge/compteur') ,'class'=>'form-horizontal', 'role' => 'form', 'style'=>'padding-left: 5px; padding-right: 5px;')) }}
            
            <div class="col-md-10 col-md-offset-1 col-sm-12 tbge-form-card-container-box">
                    
                <div class="card">
                    <div class="col-md-12">    
                    
                        @if ( $errors->count() > 0 )
                            @foreach( $errors->all() as $message )
                                <div class="alert alert-warning">
                                    {{$message}}
                                </div>
                            @endforeach
                        @endif
                            
                                
                        <input type="hidden" name="CompteurID", data-bind="value:$root.fields().CompteurID">
                        <input type="hidden" name="OldBatimentID", data-bind="value:$root.fields().OldBatimentID">
                        
                         <div class="form-group tbge-form-group" style="margin-bottom: 5px !important;">
                            <label style="margin-top: 0px !important; padding-top:10px !important;" class="col-md-3 control-label tbge-form-label">Mosquée *</label>
                                    
                            <div class="col-md-6" data-bind="with:$root.filterParams().parametres()['pFilterMosquee']">
                                @include('tbge.includes.pFilterMosquee')
                            </div>
                        </div>
                                
                        <hr class="tbge-common-filter-separator">

                        <div class="form-group tbge-form-group">
                            <label style="margin-top: 0px !important; padding-top:10px !important;" class="col-md-3 control-label tbge-form-label">N° de compteur *</label>
                            <div class="col-md-4">
                                {{ Form::text('Numero', null, array('data-bind'=>'value:$root.fields().Numero','placeholder'=>'Numéro de compteur', 'class' => 'form-control', 'maxlength'=>'25') ) }}
                            </div>

                            <div class="col-md-4" style="padding-top: 10px;">
                                <label>
                                    <input type="radio" data-bind="attr:{value:1}, checkedValue:1, checked:$root.fields().Etat" id="P25-F06-1" name="etat_compteur"> Activé 
                                </label>
                                &nbsp; &nbsp; &nbsp;
                                <label>
                                    <input type="radio" data-bind="attr:{value:'0'}, checkedValue:'0', checked:$root.fields().Etat" id="P25-F06-2" name="etat_compteur"> Désactivé
                                </label>
                            </div>
                            
                        </div>

                        <div class="form-group tbge-form-group">
                                    
                            <label style="margin-top: 0px !important; padding-top:10px !important;" class="col-md-3 control-label tbge-form-label">&Eacute;nergie *</label>
                                    
                            <div class="col-md-8">
                                        
                                <div style="width: 50%; float: left; padding-right: 5px;">
                                    {{ Form::select('EnergieID', $energies, null, array('data-bind'=>'value:$root.fields().EnergieID', 'class' => 'form-control', 'id' => 'EnergieID')) }}
                                </div>
                                
                                @if(isset($selectedEnergie))
                                    <input type="hidden" name="EnergieID" data-bind="value:$root.fields().EnergieID">
                                @endif
                                
                                <div style="width: 50%; float: left; padding-left: 5px;">
                                    <select id="FournisseurID" data-bind="value:$root.fields().FournisseurID" placeholder="Fournisseur" name="FournisseurID" class="form-control">
                                        @if(count($fournisseurs) > 0)
                                            <option value=""></option>
                                            @foreach($fournisseurs as $key => $value)
                                                <option value="{{$key}}">{{ $value }}</option>
                                            @endforeach
                                        @endif
                                    </select>
                                </div>
                                
                            </div>
                            
                        </div>

                        <div class="form-group tbge-form-group" id="bloc-id-lieu">
                            <label id="label-id-lieu" style="margin-top: 0px !important; padding-top:10px !important;" class="col-md-3 control-label tbge-form-label">ID Lieu <span class="text-danger">*</span></label>
                            <div class="col-md-4">
                                {{ Form::text('Reference', null, array('id'=>'Reference', 'data-bind'=>'value:$root.fields().Reference','placeholder'=>'N° contrat / police / CIL…', 'class' => 'form-control', 'autofocus' => '', 'maxlength'=>'50') ) }}
                                <p class="help-block" id="hint-id-lieu" style="margin-bottom:0;">Identifiant du point de livraison chez le fournisseur.</p>
                            </div>
                        </div>

                        <div class="form-group tbge-form-group" id="bloc-contrat-srm" style="display:none;">
                            <label id="label-contrat-srm" style="margin-top: 0px !important; padding-top:10px !important;" class="col-md-3 control-label tbge-form-label">N° contrat SRM <span class="text-danger">*</span></label>
                            <div class="col-md-4">
                                <input type="text" name="ContratSrm" id="ContratSrm" class="form-control" maxlength="50" placeholder="N° de contrat SRM…" data-bind="value:$root.fields().ContratSrm">
                                <p class="help-block" id="hint-contrat-srm" style="margin-bottom:0;">Uniquement si le fournisseur est SRM.</p>
                            </div>
                        </div>

                        @include('tbge.extensions.compteur-srm-fields')
                                
                        <div class="form-group tbge-form-group">
                            <label style="margin-top: 0px !important; padding-top:10px !important;" class="col-md-3 control-label tbge-form-label">Type de facturation *</label>
                                
                            <div class="col-md-4 col-xs-12 col-sm-12" style="padding-right: 0px;">
                                <div style="width: 100%; float: left;" class="padding-xs">
                                    <select id="TypeFacturationID" data-bind="value:$root.fields().TypeFacturationID" placeholder="Type de facture" name="TypeFacturationID" class="form-control">
                                        @if(count($typeFacturations) > 0)
                                            <option></option>
                                            @foreach($typeFacturations as $key => $value)
                                                <option value="{{$value->TypeFacturationID}}">{{ $value->Nom }}</option>
                                            @endforeach
                                        @endif
                                    </select>
                                </div>
                            </div>

                        </div>

                        <div class="form-group tbge-form-group">
                            <label style="margin-top: 0px !important; padding-top:10px !important;" class="col-md-3 control-label tbge-form-label">Type de tarification *</label>
                                    
                            <div class="col-md-4 col-xs-12 col-sm-12" style="padding-right: 0px;">
                                <div style="width: 100%; float: left;" class="padding-xs">
                                    <select id="TypeTarificationID" data-bind="value:$root.fields().TypeTarificationID" placeholder="Type de tarification" name="TypeTarificationID" class="form-control">
                                        @if(count($typeTarifications) > 0)
                                            <option></option>
                                            @foreach($typeTarifications as $key => $value)
                                                <option value="{{$value->TypeTarificationID}}">{{ $value->Nom }}</option>
                                            @endforeach
                                        @endif
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="form-group row tbge-form-group">
                            <div class="col-md-4 col-sm-12 col-xs-12 col-md-offset-3">
                                <div style="width: 50%; float: left; padding-right: 5px;">
                                    {{ Form::submit('Enregistrer', array('data-bind'=>'enable:$root.fields.isValid()', 'class'=>'btn btn-block btn-raised', 'style'=>' background-color: #008b10 !important; color:white;')) }}
                                </div>
                                <div style="width: 50%; float: left; padding-left: 5px;">
                                    {{ link_to(URL::previous(), 'Annuler', ['class' => 'btn btn-danger btn-block btn-raised']) }}
                                </div>                                    
                            </div>
                            
                        </div>

                    </div>
                </div>
            </div>
    
        {{ Form::close() }}

    </div>
    
    <pre data-bind="text:ko.toJSON($root, null, 2)" class="hidden"></pre>
    <!-- /.row -->
    @if(isset($compteur))
        <span id="rawData" style="display: none">{{$compteur->toJson()}}</span>
    @endif
    @if(isset($selectedBatiment))
        <span style="display:none" id="BatimentID">{{$selectedBatiment}}</span>
    @endif
    
    @if(isset($selectedBatimentName))
        <span style="display:none" id="BatimentName">{{$selectedBatimentName}}</span>
    @endif
    
    @if(isset($selectedEnergie))
        <span style="display:none" id="EnergieID">{{$selectedEnergie}}</span>
    @endif
</div>
<!-- /#page-wrapper -->

@stop