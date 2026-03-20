# audit_opquast

![SPIP](https://img.shields.io/badge/SPIP-4.4.7%20%7C%204.*-red)
![Version](https://img.shields.io/badge/version-1.7.2-blue)
![Statut](https://img.shields.io/badge/statut-stable-brightgreen)
![Licence](https://img.shields.io/badge/licence-GNU%2FGPL-green)

Plugin SPIP d'audit manuel et semi-assiste du referentiel Opquast.

## Version

- Version courante : `1.7.2`
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
- de consulter les parametres d'audit dans une card compacte puis d'ouvrir le formulaire d'edition en AJAX
- d'appliquer en une action le statut `A verifier` ou `Non applicable` a toutes les regles d'une famille
- d'afficher des badges de statut colores avec un contraste accessible pour les regles
- de beneficier d'une mise en forme CSS dediee pour les ecrans publics du plugin

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
