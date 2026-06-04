-- Ajoute la colonne permettant de stocker la raison du refus d'un outil par l'administrateur.
-- Exécuter dans la base de données MySQL de l'application.

ALTER TABLE outils_ia
    ADD COLUMN refusal_cause TEXT NULL AFTER status;
