-- Afficher / filtrer le fournisseur ACTUEL du compteur (ex. SRM après migration),
-- et non le fournisseur figé sur facture.fournisseurid à la création.
-- À exécuter sur PostgreSQL (db_energie_mhai).

CREATE OR REPLACE FUNCTION sk_tbge.facture_search(p_num_facture character varying, p_compteurid integer, p_energieid integer, p_fournisseurid integer, p_batimentid integer, p_listeid integer, p_is_inclus_prog_mv smallint, p_trimestrefacturation smallint, p_anneefacturationtrimestre smallint, p_mouvrageid integer, p_utilisateurid integer, p_etat_facture integer, p_lib_search character varying, p_sorting_criteria character varying, p_page_index integer, p_page_size integer)
 RETURNS TABLE(nb_total integer, factureid integer, num_facture character varying, batimentid integer, num_national character varying, lib_batiment character varying, compteurid integer, ref_compteur character varying, num_compteur character varying, energieid integer, lib_energie character varying, fournisseurid integer, lib_fournisseur character varying, debutperiode date, finperiode date, abonnement numeric, consommation numeric, totalttc numeric, prixunitaire numeric, estimation smallint, baseid character, mouvrageid integer, lib_mouvrage character varying, totalht numeric, tauxtva numeric, taxes numeric, total_regle numeric, total_restant_du numeric, remarques character varying, trimestrefacturation smallint, anneefacturationtrimestre smallint, created_at timestamp without time zone, updated_at timestamp without time zone, deleted_at timestamp without time zone, etat_facture smallint, etat_facture_lib text)
 LANGUAGE plpgsql
 STABLE
AS $function$
DECLARE p_criteria text;
		p_requete text;
		
		p_requete_count text;
		p_nb_total int;
		
		p_start_row_index int;
		p_end_row_index int;

BEGIN
	IF(p_sorting_criteria IS NULL) THEN p_sorting_criteria:='anneefacturationtrimestre DESC, trimestrefacturation DESC, finperiode DESC, mouvrageid'; END IF;
	--->>>>>----CRITERIA : DEBUT
	p_criteria:='SELECT facture.factureid,
						facture.nom AS num_facture,
						---
						facture_batiment.batimentid,
						batiment.num_national,
						batiment.nom AS lib_batiment,
						
						facture.compteurid,
						facture_batiment.compteurreference AS ref_compteur,
						facture_batiment.compteurnumero AS num_compteur,
						
						facture_batiment.compteurenergieid AS energieid,
						energie.nom AS lib_energie,
						
						COALESCE(compteur.fournisseurid, facture.fournisseurid) AS fournisseurid,
						fournisseur.nom AS lib_fournisseur,
						---
						facture.debutperiode,
						facture.finperiode,
						
						facture.abonnement,
						facture.consommation,
						facture.totalttc,
						---
						facture.prixunitaire,
						facture.estimation,
						---
						facture.baseid,
						
						facture.mouvrageid,
						mouvrage.societe AS lib_mouvrage,
						---
						facture.totalht,
						facture.tauxtva,
						facture.taxes,
						---
						facture.total_regle,
						facture.total_restant_du,
						---
						facture.remarques,
						
						facture.trimestrefacturation,
						facture.anneefacturationtrimestre,
						---
						facture.created_at,
						facture.updated_at,
						facture.deleted_at,
						---
						facture.etat_facture,
						
					    CASE facture.etat_facture
						    WHEN 0 THEN ( SELECT ''Non Payée'' AS text)
						    WHEN 2 THEN ( SELECT ''Payée Partiellemnt'' AS text)
						    WHEN 1 THEN ( SELECT ''Payée'' AS text)
						    ELSE NULL::text
					    END AS etat_facture_lib
						
   				   FROM sk_tbge.facture
     		 INNER JOIN sk_tbge.facture_batiment ON facture.factureid=facture_batiment.factureid
     		 INNER JOIN sk_tbge.batiment ON facture_batiment.batimentid=batiment.batimentid
			  		 --
     		 INNER JOIN sk_tbge.energie ON facture_batiment.compteurenergieid=energie.energieid
			  LEFT JOIN sk_tbge.compteur ON facture.compteurid=compteur.compteurid
			  LEFT JOIN sk_tbge.fournisseur ON COALESCE(compteur.fournisseurid, facture.fournisseurid)=fournisseur.fournisseurid
     		 INNER JOIN sk_tbge.mouvrage ON facture.mouvrageid=mouvrage.mouvrageid
					 -- END_VIEW
				  WHERE 1=1';	 
	
	IF (p_num_facture IS NOT NULL) THEN p_criteria:=p_criteria||' AND ((LOWER(facture.nom) LIKE ''%'||LOWER(p_num_facture)||'%''))'; END IF;
	
	IF (p_compteurid IS NOT NULL) THEN p_criteria:=p_criteria||' AND facture.compteurid='||p_compteurid; END IF;
	IF (p_energieid IS NOT NULL) THEN p_criteria:=p_criteria||' AND facture_batiment.compteurenergieid='||p_energieid; END IF;
	IF (p_fournisseurid IS NOT NULL) THEN p_criteria:=p_criteria||' AND COALESCE(compteur.fournisseurid, facture.fournisseurid)='||p_fournisseurid; END IF;
	
	IF (p_batimentid IS NOT NULL) THEN p_criteria:=p_criteria||' AND facture_batiment.batimentid='||p_batimentid; END IF;
	IF (p_listeid IS NOT NULL) THEN p_criteria:=p_criteria||' AND facture_batiment.batimentid IN (SELECT liste_batiment.batimentid FROM sk_tbge.liste_batiment WHERE liste_batiment.listeid='||p_listeid||')'; END IF;
	IF (p_is_inclus_prog_mv IS NOT NULL) THEN p_criteria:=p_criteria||' AND batiment.is_inclus_prog_mv='||p_is_inclus_prog_mv; END IF;
	
	IF (p_trimestrefacturation IS NOT NULL) THEN p_criteria:=p_criteria||' AND facture.trimestrefacturation='||p_trimestrefacturation; END IF;
	IF (p_anneefacturationtrimestre IS NOT NULL) THEN p_criteria:=p_criteria||' AND facture.anneefacturationtrimestre='||p_anneefacturationtrimestre; END IF;
	---
	---
	IF (p_mouvrageid IS NOT NULL) THEN p_criteria:=p_criteria||' AND (facture.mouvrageid='||p_mouvrageid||' OR mouvrage.mouvrage_parent_id='||p_mouvrageid||')' ; END IF;
	IF (p_utilisateurid IS NOT NULL) THEN p_criteria:=p_criteria||' AND facture.mouvrageid IN (SELECT utilisateur_mouvrage.mouvrageid 
																							     FROM sk_tbge.utilisateur_mouvrage 
																							    WHERE utilisateur_mouvrage.utilisateurid='||p_utilisateurid||')'; END IF;

	IF (p_etat_facture IS NOT NULL) THEN p_criteria:=p_criteria||' AND facture.etat_facture='||p_etat_facture; END IF;
	
	IF (p_lib_search IS NOT NULL) THEN p_criteria:=p_criteria||' AND ((LOWER(facture.nom) LIKE ''%'||LOWER(p_lib_search)||'%'') OR
																	   ---
																	  (LOWER(batiment.num_national) LIKE ''%'||LOWER(p_lib_search)||'%'') OR
																	  (LOWER(batiment.nom) LIKE ''%'||LOWER(p_lib_search)||'%'') OR
																	  (LOWER(batiment.nom_alternatif) LIKE ''%'||LOWER(p_lib_search)||'%'') OR
																	   ---
																	  (LOWER(facture_batiment.compteurreference) LIKE ''%'||LOWER(p_lib_search)||'%'') OR
																	  (LOWER(facture_batiment.compteurnumero) LIKE ''%'||LOWER(p_lib_search)||'%'') OR
																	   
																	  (LOWER(energie.nom) LIKE ''%'||LOWER(p_lib_search)||'%'') OR
																	  (LOWER(fournisseur.nom) LIKE ''%'||LOWER(p_lib_search)||'%'') OR
																	   ---
																	  (LOWER(mouvrage.societe) LIKE ''%'||LOWER(p_lib_search)||'%''))'; END IF;
	
	---<<<<<----CRITERIA : FIN
	
	----------------------------------------->>>>>----SEARCH-COUNT : DEBUT
	p_requete_count:='SELECT COUNT(factureid) AS nb_total FROM (';
	p_requete_count:=p_requete_count||p_criteria;
	p_requete_count:=p_requete_count||') AS reQ_count';
	EXECUTE p_requete_count INTO p_nb_total;
	-----------------------------------------<<<<<----SEARCH-COUNT : FIN

	p_start_row_index=(p_page_index * p_page_size) + 1;
	p_end_row_index=(p_page_index + 1) * p_page_size; 
	
    p_requete:='SELECT '||p_nb_total||' AS nb_total,';
	p_requete:=p_requete||'factureid,
						   num_facture,
						   ---
						   batimentid,
						   num_national,
						   lib_batiment,
						   
						   compteurid,
						   ref_compteur,
						   num_compteur,
						   
						   energieid,
						   lib_energie,
						   
						   fournisseurid,
						   lib_fournisseur,
						   ---
						   debutperiode,
						   finperiode,
						   
						   abonnement,
						   consommation,
						   totalttc,
						   ---
						   prixunitaire,
						   estimation,
						   ---
						   baseid,
						   
						   mouvrageid,
						   lib_mouvrage,
						   ---
						   totalht,
						   tauxtva,
						   taxes,
						   ---
						   total_regle,
						   total_restant_du,
						   ---
						   remarques,
						   
						   trimestrefacturation,
						   anneefacturationtrimestre,
						   ---
						   created_at,
						   updated_at,
						   deleted_at,
						   ---
						   etat_facture,
						   etat_facture_lib
					 FROM (';

	IF (p_sorting_criteria IS NOT NULL) THEN 
		p_requete:=p_requete||'SELECT ROW_NUMBER() OVER (ORDER BY '||p_sorting_criteria||') AS row_number, * FROM (';
		p_requete:=p_requete|| p_criteria||') AS reQ_order';
	ELSE 
		p_requete:=p_requete||'SELECT ROW_NUMBER() OVER() AS row_number, * FROM (';
		p_requete:=p_requete|| p_criteria||') AS reQ_order';
	END IF;
	p_requete:=p_requete||') AS reQ WHERE row_number>='||p_start_row_index||' AND row_number<='||p_end_row_index;

RETURN QUERY EXECUTE p_requete;
END;
$function$;
