{{-- Page template --}}
@extends('tbge.templates.normal')

{{-- Page title --}}
@section('title') Liste des factures @stop


@section('css')
    <!-- Included -->
    <link rel="stylesheet" href='/assets/select2/select2.min.css'>

    <link rel="stylesheet" href='/assets/datatables/dataTables.min.css'>
    <link rel="stylesheet" href='/assets/datatables/dataTables.bootstrap.css'>
    
    <link rel="stylesheet" href='/assets/jquery-ui-1.11.2/themes/base/all.css'>

    <link rel="stylesheet" href='/tbge-styles/page12_gerer_factures.css'>

    <style type="text/css">
        .vignette-datatable-table-col {
            width: 100%;
            overflow-x: scroll;
        }

        /* Filter */
        .vignette-datatable-table-filter > div > label {
            display: flex;
            justify-content: space-evenly;
            padding-left: 5px; padding-right: 5px;
            padding-top: 5px;
            margin-bottom: 0px;
            align-items: baseline;

        }
        .vignette-datatable-table-filter > div > label > input {
            margin-bottom: 0px;
            padding-bottom: 0px;
            padding-top: 0px;
        }

        /* Length */
        .vignette-datatable-table-length > {
            display: flex;
            align-items: center;
        }
        .vignette-datatable-table-length > div > label {
            display: flex;
            justify-content: space-evenly;
            align-items: center;
        }

        /* Processing  */
        .vignette-datatable-table-processing > div {
            height: 100%;
        }

        @media (max-width: 420px){
            
            /* Filter */
            .vignette-datatable-table-filter > div > label {
                width: 100%;
                margin-top: 2px;
                padding-left: 30px; padding-right: 30px;
            }

            /* Length */
            .vignette-datatable-table-length > div > label {
                width: 100%;
                margin-top: 2px;
            }
            .vignette-datatable-table-length > div > label > select {
                width: 50%;
            }

            #delete-fatures-btn {
                padding-left: 10px; 
                padding-right: 10px;
            }

            #delete-fatures-btn > span {
                display: none;
            }
        }

        @media (min-width: 421px){
            
            .vignette-datatable-table-length > div > label {
                width: 100%;
                margin-top: 2px;
            }

            .vignette-datatable-table-length > div > label > select {
                min-width: 30%;
            }    
        }

        #dataTables-example > thead > tr > th.select-checkbox.no-sort.sorting_asc {
            background: none;
        }
        .sorting_asc.no-sort::after {
            display: none!important;
        }

        .sorting_asc.no-sort { 
            /*pointer-events: none!important;*/ 
            cursor: default!important;
        }

        .dt-body-nowrap {
           white-space: nowrap;
        }

        .dt-head-nowrap {
           white-space: nowrap;
        }

    </style>

@stop 

@section('scripts')
    
    <script lang="javascript" src="/assets/select2/select2.full.min.js"></script>
    <script lang="javascript" src='/assets/select2/i18n/fr.js'></script>
    
    <script lang="javascript" src='/assets/datatables/dataTables.min.js'></script>
    
    <script lang="javascript" src="/assets/jquery/jquery.mask.min.js"></script>
    <script type="text/javascript" src="/assets/jquery-ui-1.11.2/ui/core.js"></script>
    <script type="text/javascript" src="/assets/jquery-ui-1.11.2/ui/widget.js"></script>
    <script type="text/javascript" src="/assets/jquery-ui-1.11.2/ui/datepicker.js"></script>
    <script type="text/javascript" src="/assets/jquery-ui-1.11.2/demos/datepicker/datepicker-fr.js"></script>

    <script lang="javascript" src="/assets/xlsx.js"></script>


    <style type="text/css">
        .mhaiTable {
            background-color: black;
            color: white;
        }
        .mhaiTable th {
            text-align: left;
        }
    </style>


<script type="text/javascript" src="/tbge-legacy/commons/ParametreRechercheMultiCriteres.js"></script>
<script type="text/javascript" src="/tbge-legacy/commons/RechercheMultiCriteres.js"></script>
<script type="text/javascript" src="/tbge-legacy/commons/pFilterMosqueeSelect.js"></script>

<script type="text/javascript" src="/tbge-legacy/indexes/IndexFactures.js?v=20240206"></script>

<script type="text/javascript">
    $.fn.DataTable.ext.pager.numbers_length = 5
    $(document).ready(function() {
        var baseUrl = ''; // "{{URL::to('/')}}";
        //var baseUrl = "{{URL::to('/tbge/factures')}}";
        
        var token = "{{csrf_token()}}";
        
        var canCreate   = "{{$canCreate}}" == 1 ? true : false;
        var canEdit     = "{{$canEdit}}" == 1 ? true : false;
        var canDelete   = "{{$canDelete}}" == 1 ? true : false;

        var vm = new IndexFactures(baseUrl, token, canCreate, canEdit, canDelete);
        ko.applyBindings(vm, document.getElementById('page-wrapper'));
        
        vm.init(baseUrl, token);
        
        window.viewModel = vm;
    });

</script>
@stop


@section('content')
    <div id="page-wrapper" style="margin-top: 60px;">
        
        <div class="row">
            <div class="col-lg-12">
                <h1 class="tbge-page-title">Administration des Factures 
                    <a target="_blank" data-bind="attr:{href:'/tbge/export/factures'+$root.buildExportFacturesParams()}" href="/tbge/export/factures" style="float: right; margin-bottom: -35px; padding-bottom: 0px">
                        <img src="/tbge-styles/icons/imgExport.png" class="tbge-icon" style="float: right;  width:25px; height:25px; margin-top: 10.5px; cursor: pointer;">
                    </a>
                </h1>
                <hr class="tbge-page-title-separator">
                <a class="pull-right" style="margin-bottom: 10px; margin-top: 5px; text-decoration:underline; font-weight:bolde; font-size:1.2em;" href="/tbge/factures/create">Saisir facture</a>

            </div>
        </div>
            
        <!-- /.row -->
        <div class="row">
            <div class="col-lg-12 col-md-12">
                <div class="panel panel-info">
                    <!-- /.panel-heading -->
                    <div class="panel-body">
                        <!-- Success-Messages -->
                        @if ($message = Session::get('success'))
                        <div class="alert alert-success alert-block" id="ephemeral-alert-block">
                            <button type="button" class="close" data-dismiss="alert">&times;</button>
                            {!!$message!!}
                        </div>
                        @endif
                        @if ($message = Session::get('error'))
                        <div class="alert alert-danger alert-block">
                            <button type="button" class="close" data-dismiss="alert">&times;</button>
                            {!!$message!!}
                        </div>
                        @endif
                        
                           <form method="POST" action="/paiements/0" name="BillsBePaidForm" id="BillsBePaidForm" role="form"></form>
                            <form method="GET" action="/tbge/factures" name="facturesSearchForm">
                            
                                <div class="thumbnail tbge-filter-box" id="mosquees-filter-box" data-bind="with:$root.rechercheMultiCriteres()">
                                    @include('tbge.tbge.facture.facturesFilter')
                                </div>

                                <div class="row tbge-pure-row">
                                
                                    <div style="display:flex; width: 30%;" class="pull-left">
                                        
                                        <button type="submit" form="BillsBePaidForm" data-bind="visible:$root.selectedFactures().length > 0, enable:$root.canPay().status" class="col-md-6 btn btn-success tbge-btn btn-raised" style="display: none; margin-top: 0px; margin-bottom: 2px; margin-left: 2.5px; flex-grow: 0.5;@if($canDelete) margin-right: 5px;@endif">PAYER </button>
                                        @if($canDelete)
                                            <a id="delete-fatures-btn" data-bind="visible:$root.selectedFactures().length > 0, click:function(){$root.deleteSelectFactures();}" class="col-md-6 btn btn-danger btn-raised" style="display: none; margin-top: 0px; margin-bottom: 2px; flex-grow: 0.5;margin-left: 5px;"><span>Supprimer</span> <i class="glyphicon glyphicon-trash"></i> </a>
                                        @endif
                                            <input type="hidden" name="_token" value="{{ csrf_token() }}" form="BillsBePaidForm">
                                            <input type="hidden" name="ids[]" data-bind="value:$root.selectedFacturesValues" form="BillsBePaidForm">
                                    </div>

                                    <button type="submit" class="btn tbge-btn-do-filter btn-raised pull-right" type="submit" style="margin-top: 0px; margin-bottom: 2px" form="facturesSearchForm" data-bind="click:function(){$root.proceedWithSearch();}">
                                        RECHERCHER
                                    </button>
                                </div>

                            </form>
                            
                            <div>
                                <div class="row tbge-pure-row" data-bind="visible:$root.selectedFactures().length > 0">
                                    <div class="col-md-4 col-md-offset-4">
                                        <span>Montant Factures: </span> <strong style="font-size:200%;" data-bind="html:montantFacturesSelectionnees()"></strong> 
                                    </div>
                                </div>
                                <table class="table table-striped table-hover table-bordered" id="dataTables-example">
                                    <thead>
                                        <tr class="tbge-data-table-header">
                                            <th class="select-checkbox no-sort" style="width: 25px !important; vertical-align: middle; padding-right: 0px;">
                                                <input type="checkbox" id="select-all" data-bind="click:function(){$root.selectAllEntries();}" />
                                            </th>
                                            <th>N° facture</th>
                                            <th style="max-width: 200px">Compteur</th>
                                            <th>Fournisseur</th>
                                            <th>Délégation</th>
                                            <th>Mosquée</th>
                                            <th>Du</th>
                                            <th>Au</th>
                                            <th>Qté</th>
                                            <th>Coût TTC</th>
                                            <th>Reste (DH)</th>
                                            <th style="min-width: 50px;">&Eacute;tat</th>
                                            <th></th>
                                        </tr>
                                    </thead>
                                </table>
                            </div>

                    </div>
                    <!-- /.panel-body -->
                </div>
                <!-- panel -->
            </div>
            <!-- /.col-lg-4 -->
        </div>
        <!-- /.row -->
        @include('tbge.includes.confirmDelete')

        <span id="__token" style="display: none;">
            {{csrf_token()}}
        </span>
    </div>
<!-- /#page-wrapper -->

@stop
