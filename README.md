# Audit OpQuast

![SPIP](https://img.shields.io/badge/SPIP-4.4.7%20%7C%204.*-red)
![Version](https://img.shields.io/badge/version-1.19.1-blue)
![Statut](https://img.shields.io/badge/statut-stable-brightgreen)
![Licence](https://img.shields.io/badge/licence-GNU%2FGPL-green)

Plugin SPIP d'audit manuel et semi-assiste du referentiel Opquast.

## Version

- Version courante : `1.19.1`
- Compatibilite SPIP : `4.0` a `4.4`
- Referentiel embarque : `Opquast Qualite Numerique v5 (2025-2030)`

## Objectif

Le plugin pose les bases d'un outil d'audit dans SPIP avec :

- une table des 245 regles Opquast
- une table des audits
- une table des resultats par regle et par audit
- une installation qui cree les tables et peuple le referentiel
- une desinstallation qui supprime les tables creees par le plugin
- un premier parcours front office pour creer un audit et renseigner des regles

## MVP disponible

Le MVP permet maintenant :

- de creer un audit avec un titre, une cible et un statut
- d'afficher la liste des audits existants
- d'afficher pour chaque audit une progression et le nombre de regles deja traitees
- d'afficher une carte de synthese d'audit plus lisible, alignee a gauche, avec 2 KPI visibles
- d'ouvrir un audit en detail
- de modifier un audit existant depuis sa page detail
- de filtrer les regles par famille, recherche libre et statut
- de filtrer rapidement les regles via des raccourcis cliquables par statut
- de naviguer entre les regles precedentes et suivantes dans un audit filtre
- de saisir un resultat par regle avec statut, commentaire et preuve
- de suivre un resume plus lisible de l'avancement global et par famille
- de piloter l'audit avec une synthese decisionnelle des non-conformites et familles prioritaires
- de trier les regles par priorite d'action, numero, famille ou statut
- d'afficher la vue par famille sous forme de cartes KPI plus lisibles
- de beneficier d'un polissage visuel des cartes famille pour une lecture plus confortable
- d'aligner plus proprement les badges de priorite dans les cartes famille
- de conserver un redesign isole du bloc de synthese d'audit pour faciliter un retour arriere
- d'espacer davantage chaque bloc principal pour aérer la lecture
- d'acceder aux pages publiques via `spip.php?page=audit_opquast` et `spip.php?page=audit_opquast_audit`
- de naviguer entre les regles sans recharger toute la page grace a l'AJAX
- d'enregistrer un resultat en AJAX et d'enchainer automatiquement sur la regle suivante visible
- de conserver une confirmation lisible grace a un toast global apres enregistrement AJAX
- de corriger la redirection AJAX apres sauvegarde pour conserver une URL valide
- de conserver la position sur le bloc de navigation apres une sauvegarde AJAX
- de resynchroniser le tableau de bord et les regles du referentiel apres chaque changement de statut en AJAX
- de conserver les filtres actifs apres sauvegarde AJAX, notamment en vue famille
- de fiabiliser la regle courante de navigation quand une regle sort du filtre actif
- de conserver la regle courante apres sauvegarde tant qu'elle reste visible dans le filtre actif
- de figer temporairement la position et les liens de navigation de la regle courante jusqu'au prochain clic
- de conserver explicitement le contexte `famille`, `recherche` et `tri` dans le formulaire de navigation
- de regrouper les actions `Parametres de l'audit` et `Generer l'export` dans le bloc de tete de l'audit
- de remplacer les deux cartes d'action du bloc de tete par un panneau unique quand les parametres ou l'export sont ouverts
- d'harmoniser le panneau `Modifier l'audit` avec le panneau d'export, titre et bouton `Fermer` inclus
- de supprimer la hauteur excessive du panneau d'export quand il est ouvert
- d'integrer les KPI du resume directement dans le bloc de tete de l'audit
- d'ajouter une separation visuelle entre la progression et les KPI du resume
- d'harmoniser le hover des actions de la vue par famille
- d'afficher un score de conformite dans le bloc de tete, avec `--` tant qu'aucune regle n'est evaluee
- d'afficher le score de conformite avec deux decimales
- de beneficier d'un bloc `Navigation entre les regles` plus lisible avec un formulaire plus dense et mieux hierarchise
- de supprimer la repetition du titre de regle entre l'entete de navigation et le formulaire
- de disposer d'un panneau d'export sur la page detail avec `CSV` actif et `Excel`, `DOC`, `PDF` annonces comme bientot disponibles
- de consulter les parametres d'audit dans une card compacte puis d'ouvrir le formulaire d'edition en AJAX
- d'appliquer en une action le statut `A verifier` ou `Non applicable` a toutes les regles d'une famille
- d'afficher des badges de statut colores avec un contraste accessible pour les regles
- d'ouvrir en AJAX le bloc `Navigation entre les regles` depuis la liste sans recharger toute la page
- d'utiliser l'AJAX sur le bloc filtres pour recharger plus vite la liste, les raccourcis et la navigation
- d'aligner le bouton `Filtrer` sur la meme ligne de lecture que les autres champs du bloc filtres
- de conserver la taille naturelle du bouton `Filtrer` tout en le gardant aligne avec les champs
- d'ajuster finement l'alignement du bouton `Filtrer` sans toucher au champ `Recherche`
- d'ouvrir correctement les regles prioritaires meme si des filtres incompatibles etaient actifs
- d'ouvrir en AJAX les listes de regles depuis les cartes KPI des familles
- de viser directement la zone `Regles du referentiel` depuis les cartes famille, avec ou sans JavaScript
- d'afficher la famille active dans le titre `Regles du referentiel` quand un filtre famille est applique
- de proposer un bouton `Retour en haut` discret, affiche au scroll
- d'afficher uniquement la fleche du bouton `Retour en haut`, avec un texte conserve pour l'accessibilite
- de n'afficher la creation d'audit qu'au clic sur `Creer un nouvel audit`
- de presenter les audits existants sous forme de cards KPI plus lisibles
- de proposer un acces direct `Ouvrir` plus simple depuis la liste des audits
- de supprimer completement un audit et tous ses resultats depuis la liste apres confirmation
- d'afficher les actions `Ouvrir` et `Supprimer` avec le meme format visuel que le bouton de creation
- d'enrichir les cards audit avec tous les KPI de suivi et une progression explicite au-dessus de la barre
- de faire fonctionner les bascules AJAX aussi sur la page liste des audits
- d'offrir un formulaire de creation et d'edition d'audit plus lisible, avec une grille plus claire et des actions harmonisees
- de soumettre correctement le formulaire de creation d'audit sans interception AJAX parasite
- de rendre cliquable l'URL cible dans le detail d'audit quand il s'agit d'une vraie URL
- d'afficher le titre de l'audit directement dans le `h1` de la page detail
- d'afficher le titre et le slogan du plugin dans le header des pages publiques du plugin
- d'afficher un `h1` et une introduction de page plus explicites sur la liste des audits
- de beneficier d'une mise en forme CSS dediee pour les ecrans publics du plugin
- de fusionner la synthese et les parametres d'audit dans un bloc de tete unique

## Tables SQL

- `spip_audit_opquast_regles`
- `spip_audit_opquast_audits`
- `spip_audit_opquast_resultats`

## Installation

A l'installation ou a la mise a jour du plugin :

- les trois tables SQL sont creees ou mises a jour
- le referentiel local des 245 regles est importe dans `spip_audit_opquast_regles`
- l'import du referentiel est repris par lots pour eviter les blocages a l'activation
- les reprises d'import reviennent vers l'ecran des plugins et non vers l'upgrade SQL du coeur SPIP
- le formulaire de resultat affiche correctement le numero et le titre de la regle selectionnee
- le bouton de filtre utilise maintenant un libelle traduit par le plugin
- la fiche audit propose une synthese decisionnelle sans modification du schema SQL

A la desinstallation :

- les trois tables sont supprimees
- les metas du plugin sont effacees

## Referentiel

Les regles embarquees dans le plugin proviennent du referentiel officiel Opquast :

- [Checklist Opquast - Qualite Numerique](https://checklists.opquast.com/fr/qualite-numerique/)

Les donnees importees dans le MVP sont :

- numero de regle
- intitule
- famille
- slug de famille
- mots-cles
- phases projet
- URL source
- niveau d'automatisation initial (`non_classe`)
- version du referentiel

## Prochaine etape conseillee

Enrichir le MVP avec :

- un export des resultats
- une aide semi-automatique sur certaines regles
- une ponderation plus fine des priorites selon criticite ou contexte projet
