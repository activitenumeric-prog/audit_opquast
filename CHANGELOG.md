# Changelog

## 1.8.0 - 2026-03-20

- ouverture en AJAX du bloc `Navigation entre les regles` depuis les liens `Enregistrer le resultat` de la liste
- conservation du contexte de filtre avec mise a jour de l'URL et defilement vers le bloc charge

## 1.7.2 - 2026-03-20

- ajout de couleurs dediees et accessibles aux badges de statut des regles
- application de ces variantes sur la navigation et la liste des regles

## 1.7.1 - 2026-03-20

- ajout du KPI `Non applicable` dans les cartes famille
- reorganisation des indicateurs famille avec `Regles traitees` sur toute la largeur

## 1.7.0 - 2026-03-20

- ajout d'actions groupees par famille pour passer toutes les regles en `A verifier` ou `Non applicable`
- integration de ces actions directement dans les cartes KPI des familles

## 1.6.0 - 2026-03-20

- remplacement du formulaire des parametres d'audit par une card compacte en affichage par defaut
- ouverture et fermeture du formulaire de parametres en AJAX avec fallback standard sans JavaScript

## 1.5.6 - 2026-03-20

- remise du numero et du texte de la regle active sur une meme ligne logique dans la navigation

## 1.5.5 - 2026-03-20

- suppression du focus visuel parasite apres navigation AJAX entre les regles
- alignement a gauche du numero, du titre et du badge de statut pour mieux gerer les regles longues

## 1.5.4 - 2026-03-20

- deplacement du badge de statut sous le titre de la regle active dans la navigation pour mieux gerer les intitules longs

## 1.5.3 - 2026-03-20

- conservation de `var_mode=recalcul` dans la navigation AJAX pour fiabiliser le rechargement du bloc partiel en phase de mise au point

## 1.5.2 - 2026-03-20

- correction de l'affichage du badge `A verifier` dans la navigation AJAX entre les regles

## 1.5.1 - 2026-03-20

- ajout du badge de statut dans la navigation entre les regles pour afficher l'etat de la regle active

## 1.5.0 - 2026-03-20

- navigation AJAX sur le bloc regle active et formulaire pour eviter le rechargement complet de la page
- ajout d'un endpoint partiel dedie et d'un script progressif avec fallback standard

## 1.4.10 - 2026-03-20

- amelioration visuelle du bloc navigation entre les regles et meilleure separation avec le formulaire de saisie
- sous-titre du formulaire plus discret et titre de regle mieux mis en valeur

## 1.4.9 - 2026-03-20

- mise en forme plus compacte et plus claire du bloc de resume de l'audit
- transformation des indicateurs en mini cartes KPI plus lisibles

## 1.4.8 - 2026-03-20

- mise en forme plus compacte et plus lisible du bloc de navigation entre les regles
- meilleure separation entre la position de la regle, son intitule et les actions precedente/suivante

## 1.4.7 - 2026-03-20

- correction finale de l'affichage du numero et du titre de la regle dans le formulaire de resultat
- redirection plus propre apres enregistrement quand la regle sort du filtre actif

## 1.4.6 - 2026-03-20

- correction de l'affichage du titre de la regle dans le formulaire de resultat pour supprimer le fragment technique visible a l'ecran

## 1.4.5 - 2026-03-20

- augmentation de la marge externe des blocs principaux `audit-opquast-bloc` a `2rem`

## 1.4.4 - 2026-03-20

- refonte du bloc de synthese d'audit en carte structuree alignee a gauche
- mise en avant de l'URL cible, du statut et de deux KPI visibles
- ajustement CSS isole sur la carte de synthese pour faciliter un retour arriere

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
