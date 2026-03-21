# Changelog

## 1.16.0 - 2026-03-21

- refonte visuelle du bloc `Navigation entre les regles` pour mieux separer la navigation, le statut et l'edition
- densification du formulaire de resultat avec un header plus clair, des champs mieux structures et une action finale plus lisible

## 1.15.9 - 2026-03-21

- conservation explicite des champs `famille`, `q` et `tri` dans le formulaire de navigation
- suppression des bascules parasites du sous-ensemble filtre vers le referentiel complet apres sauvegarde AJAX

## 1.15.8 - 2026-03-21

- gel temporaire de la position et des liens precedente/suivante de la regle courante apres sauvegarde
- retour au classement reel des regles au clic de navigation suivant

## 1.15.7 - 2026-03-21

- conservation de la regle courante apres sauvegarde si elle reste visible dans le sous-ensemble filtre
- passage a la regle suivante seulement quand la regle modifiee sort reellement du filtre actif

## 1.15.6 - 2026-03-21

- fallback automatique sur la premiere regle visible quand la regle demandee n'appartient plus au sous-ensemble filtre
- alignement du formulaire et du bloc `Navigation entre les regles` sur la meme regle courante resolue

## 1.15.5 - 2026-03-21

- conservation explicite des filtres soumis apres sauvegarde AJAX d'une regle
- maintien du contexte `famille`, `tri`, `recherche` et `statut` lors du passage a la regle suivante visible

## 1.15.4 - 2026-03-21

- resynchronisation en AJAX du tableau de bord, de la navigation et de la liste des regles apres un changement de statut
- mise a jour immediate des KPI, priorites, familles et badges sans rechargement complet de la page

## 1.15.3 - 2026-03-21

- conservation de la position de lecture sur le bloc `Navigation entre les regles` apres enregistrement AJAX
- suppression du retour parasite en haut de page apres passage automatique a la regle suivante

## 1.15.2 - 2026-03-21

- correction de l'URL de redirection apres enregistrement AJAX pour eliminer les `&amp;` parasites
- suppression du cas `Audit introuvable` provoque par une URL de detail mal recomposee

## 1.15.1 - 2026-03-21

- remplacement de la confirmation fugace apres sauvegarde AJAX par un toast global lisible
- conservation de la navigation automatique vers la regle suivante sans perdre le retour visuel de sauvegarde

## 1.15.0 - 2026-03-21

- enregistrement AJAX des resultats de regle sans rechargement complet de la page
- enchainement automatique vers la regle suivante visible apres sauvegarde, avec fallback standard sans JavaScript

## 1.14.0 - 2026-03-21

- fusion du bloc de synthese et du bloc des parametres dans un bloc de tete unique
- ouverture du formulaire de parametres dans ce meme bloc, sans duplication des informations de contexte

## 1.13.0 - 2026-03-21

- enrichissement des cards audit avec `Non conforme`, `A verifier`, `Conforme`, `Non applicable`
- affichage de `Regles traitees` sur toute la largeur de la grille KPI
- ajout d'un libelle `Progression : x%` au-dessus de la barre de progression

## 1.12.1 - 2026-03-21

- harmonisation visuelle des actions `Ouvrir` et `Supprimer` avec le style des boutons de creation
- ajout d'une variante danger pour l'action `Supprimer` sur les cards audit

## 1.12.0 - 2026-03-21

- ajout d'une action `Supprimer` sur chaque card de la liste des audits
- suppression complete de l'audit et de tous ses resultats lies apres confirmation

## 1.11.6 - 2026-03-21

- suppression de l'action `Modifier l'audit` dans la liste des audits
- conservation d'un parcours plus simple avec une seule action `Ouvrir` par card

## 1.11.5 - 2026-03-21

- remplacement du `h1` de la page liste par `Page des audits`
- remplacement de l'introduction de la page liste par un texte d'action plus explicite

## 1.11.4 - 2026-03-21

- affichage du titre et du slogan du plugin dans le header des pages publiques `audit_opquast`
- alignement de la page liste sur les inclusions `inc/` du plugin pour un rendu cohérent avec la page detail

## 1.11.3 - 2026-03-21

- ajout du titre de l'audit dans le `h1` de la page detail
- conservation du meme titre dans le bloc hero pour la synthese visuelle

## 1.11.2 - 2026-03-21

- URL cible du bloc hero rendue cliquable quand elle commence par `http://` ou `https://`
- conservation d'un affichage texte simple pour les cibles libres non URL

## 1.11.1 - 2026-03-21

- correction de l'interception AJAX sur le bouton de soumission du formulaire de creation
- suppression de la redirection parasite vers `/undefined` lors de la creation d'un audit

## 1.11.0 - 2026-03-21

- amelioration visuelle du formulaire de creation et d'edition d'audit avec une largeur mieux maitrisee
- meilleure hierarchie des champs, grille plus lisible et actions `Annuler / Creer un audit` harmonisees

## 1.10.1 - 2026-03-21

- chargement du script AJAX sur la page liste des audits
- activation du bouton `Creer un nouvel audit` sans rechargement complet

## 1.10.0 - 2026-03-21

- remplacement du formulaire de creation visible en permanence par un bloc compact ouvrable au clic
- transformation de la liste des audits existants en cards KPI avec progression, statuts et actions

## 1.9.9 - 2026-03-21

- passage du libelle `Retour en haut` en texte reserve aux lecteurs d'ecran
- affichage visuel reduit a la fleche du bouton fixe

## 1.9.8 - 2026-03-21

- ajout d'un bouton `Retour en haut` fixe sur la page detail d'audit
- affichage conditionnel au scroll avec retour doux vers le haut

## 1.9.7 - 2026-03-20

- ajout de la famille active dans le titre du bloc `Regles du referentiel`
- conservation du titre simple quand aucun filtre famille n'est applique

## 1.9.6 - 2026-03-20

- ajout d'un `href` ancre vers la zone resultats pour les liens des cartes famille
- ajout d'un `data-ajax-href` explicite pour fiabiliser l'ouverture AJAX des regles par famille

## 1.9.5 - 2026-03-20

- ouverture AJAX du bloc resultats depuis les liens `Voir les regles de cette famille`
- mise a jour de l'URL et defilement vers la zone filtres/liste sans rechargement complet

## 1.9.4 - 2026-03-20

- ouverture des regles prioritaires sans reutiliser des filtres incompatibles
- activation de l'ouverture AJAX du bloc navigation depuis `Regles a traiter en premier`

## 1.9.3 - 2026-03-20

- ajustement CSS cible de l'alignement du bouton `Filtrer` dans la grille
- conservation du champ `Recherche` tel quel

## 1.9.2 - 2026-03-20

- correction de l'alignement du bouton `Filtrer` dans sa colonne de grille
- retour a une largeur naturelle du bouton au lieu d'un etirement sur toute la colonne

## 1.9.1 - 2026-03-20

- alignement du bouton `Filtrer` avec les autres champs du formulaire de filtres
- meilleure tenue responsive du bouton dans la grille des filtres

## 1.9.0 - 2026-03-20

- recharge AJAX du bloc `Filtres + navigation + liste des regles` sans rechargement complet de la page
- prise en charge des raccourcis de statut et du formulaire de filtres avec mise a jour de l'URL

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
