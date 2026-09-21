# Reprise Compteur — ID Lieu / SRM (Anc_Vignette)

**Chemin projet :** `C:\Users\user\Desktop\Projet_DSI_Courant\Anc_Vignette\TBGE\mhai_vignettes`  
**App :** `mhai_vignettes\mhai_vignettes`  
**Contexte :** port 3300 = Anc_Vignette (pas Nv_Vignette).

## Statut

Modifications **déjà appliquées** sur Anc_Vignette (session 2026-09-21). À l’ouverture du projet : **vérifier** puis compléter si besoin.

## Checklist de vérification

- [ ] `create.blade.php` : label `#label-id-lieu` = « ID Lieu * » (pas « contrat SRM »)
- [ ] `create.blade.php` : bloc `#bloc-contrat-srm` + `name="ContratSrm"` + `data-bind`
- [ ] `create.blade.php` : script `coeCVM.js?v=20260921anc2` (ou version plus récente)
- [ ] `coeCVM.js` : `updateLabelsForFournisseur` change le libellé selon fournisseur
- [ ] `coeCVM.js` : bloc SRM visible seulement si fournisseur SRM
- [ ] `coeCVM.js` : `Reference` required onlyIf non-SRM ; `ContratSrm` required onlyIf SRM
- [ ] `CompteurTbgeController::store` : SRM → `reference` = ContratSrm ; `reference_ancienne` = ID Lieu optionnel
- [ ] Ctrl+F5 + `php artisan view:clear` après test

## Comportement attendu

1. Choisir **ONEE** → libellé « ID Lieu (contrat ONEE) * », pas de champ contrat SRM.
2. Choisir **Redal** → « ID Lieu (CIL Redal) * ».
3. Choisir **SRM** → ID Lieu optionnel (ancien CIL) + **N° contrat SRM *** obligatoire.
4. Enregistrement OK pour chaque fournisseur sans erreur « contrat SRM » fantôme.
