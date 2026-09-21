-- Conserver l'ancien fournisseur (ONEE, Redal, Lydec, Amendis, …) après passage à SRM
-- À exécuter une fois sur PostgreSQL (schéma sk_tbge).

ALTER TABLE sk_tbge.compteur
    ADD COLUMN IF NOT EXISTS fournisseur_ancien_id INTEGER NULL;

COMMENT ON COLUMN sk_tbge.compteur.fournisseur_ancien_id IS
    'Ancien fournisseurid (avant migration vers SRM)';

CREATE INDEX IF NOT EXISTS idx_compteur_fournisseur_ancien_id
    ON sk_tbge.compteur (fournisseur_ancien_id)
    WHERE fournisseur_ancien_id IS NOT NULL;
