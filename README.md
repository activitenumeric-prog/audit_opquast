# audit_opquast

Plugin SPIP d'audit manuel et semi-assiste du referentiel Opquast.

## Version

- Version courante : `1.1.0`
- Compatibilite SPIP : `4.0` a `4.4`
- Referentiel embarque : `Opquast Qualite Numerique v5 (2025-2030)`

## Objectif

Le plugin pose les bases d'un outil d'audit dans SPIP avec :

- une table des 245 regles Opquast
- une table des audits
- une table des resultats par regle et par audit
- une installation qui cree les tables et peuple le referentiel
- une desinstallation qui supprime les tables creees par le plugin

## Tables SQL

- `spip_audit_opquast_regles`
- `spip_audit_opquast_audits`
- `spip_audit_opquast_resultats`

## Installation

A l'installation ou a la mise a jour du plugin :

- les trois tables SQL sont creees ou mises a jour
- le referentiel local des 245 regles est importe dans `spip_audit_opquast_regles`

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

Construire l'interface MVP :

- liste des audits
- creation d'un audit
- fiche audit
- selection d'une regle
- enregistrement d'un resultat
