# Changelog

## 1.4.3 - 2026-03-20

- deplacement du badge de priorite sous le nom de famille dans les cartes KPI
- alignement visuel ameliore entre les cartes famille

## 1.4.2 - 2026-03-20

- correction du rendu des cartes KPI famille pour limiter la densite visuelle
- amelioration de la lisibilite des mini-indicateurs dans chaque carte
- ajustement du badge de priorite et du lien d'action des cartes famille

## 1.4.1 - 2026-03-20

- transformation de la vue par famille en grille de cartes KPI
- ajout d'indicateurs compacts par famille pour la progression, les non-conformites et les regles traitees
- ajout d'un lien direct pour filtrer les regles d'une famille depuis sa carte
- ajustement CSS pour renforcer la lisibilite et la criticite des familles

## 1.4.0 - 2026-03-20

- ajout d'une synthese decisionnelle sur la fiche audit avec indicateurs de non-conformites et top priorites
- ajout d'un classement des familles prioritaires selon les non-conformites et les regles a verifier
- ajout d'un tri des regles par priorite d'action, numero, famille ou statut
- ajout de raccourcis de filtre cliquables par statut sur la fiche audit
- ajustement du rendu CSS pour mettre en avant les priorites et les regles critiques

## 1.3.0 - 2026-03-20

- ajout de l'edition d'un audit existant directement depuis la page detail
- ajout d'indicateurs de progression sur la liste des audits et sur la fiche d'audit
- ajout d'une vue de synthese par famille sur la page detail
- ajout d'une navigation precedente et suivante entre les regles affichees
- ajout d'une feuille CSS dediee et de son chargement dans le front office

## 1.2.2 - 2026-03-20

- correction du formulaire de resultat pour afficher correctement le numero et le titre de la regle selectionnee
- suppression de l'avertissement `Array to string conversion` sur la page detail d'audit
- ajout d'un libelle de bouton de filtre traduit dans le plugin

## 1.2.1 - 2026-03-20

- ajout des squelettes publics racine `audit_opquast.html` et `audit_opquast_audit.html`
- correction de l'integration avec le theme public pour que les pages `spip.php?page=...` soient bien resolues

## 1.2.0 - 2026-03-20

- ajout d'une premiere interface front office pour lister et creer des audits
- ajout d'une page detail d'audit avec resume, filtres et affichage des regles
- ajout des formulaires de creation d'audit et de saisie d'un resultat par regle
- ajout des helpers et autorisations necessaires au MVP

## 1.1.2 - 2026-03-20

- correction de la redirection de reprise pour revenir sur l'administration des plugins
- suppression de la boucle vers l'ecran de mise a niveau SQL du coeur SPIP pendant l'activation

## 1.1.1 - 2026-03-20

- correction de l'import du referentiel Opquast pour une activation compatible avec le mecanisme de reprise SPIP
- ajout d'un import par lots avec memorisation de progression en meta technique
- nettoyage automatique de la progression d'import en fin d'installation et a la desinstallation

## 1.1.0 - 2026-03-20

- ajout de la declaration SQL des tables `regles`, `audits` et `resultats`
- mise en place de la creation et mise a jour des tables a l'installation
- mise en place de la suppression des tables a la desinstallation
- ajout d'un referentiel local des 245 regles Opquast pour peupler la table `spip_audit_opquast_regles`
- correction du squelette prive de configuration
- harmonisation minimale des metadonnees et fichiers de langue

## 1.0.0 - 2026-03-20

- squelette initial du plugin
