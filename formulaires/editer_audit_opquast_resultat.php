<?php

if (!defined('_ECRIRE_INC_VERSION')) {
	return;
}

function formulaires_editer_audit_opquast_resultat_charger_dist($id_audit, $id_regle) {
	include_spip('inc/autoriser');
	include_spip('audit_opquast_fonctions');

	if (!autoriser('modifier', 'audit_opquast')) {
		return false;
	}

	$id_audit = intval($id_audit);
	$id_regle = intval($id_regle);
	$audit = audit_opquast_lire_audit($id_audit);
	$regle = audit_opquast_lire_regle($id_regle);

	if (!$audit || !$regle) {
		return false;
	}

	$resultat = audit_opquast_lire_resultat($id_audit, $id_regle);

	return [
		'id_audit' => $id_audit,
		'id_regle' => $id_regle,
		'statut_verification' => $resultat['statut_verification'] ?? 'a_verifier',
		'commentaire' => $resultat['commentaire'] ?? '',
		'preuve' => $resultat['preuve'] ?? '',
		'q' => trim((string) _request('q')),
		'famille' => trim((string) _request('famille')),
		'tri' => trim((string) _request('tri')) ?: 'priorite',
		'statut_verification_filtre' => trim((string) _request('statut_verification')),
		'_regle_numero' => $regle['numero'] ?? '',
		'_regle_titre' => $regle['titre'] ?? '',
		'_regle' => $regle,
		'_statuts_verification' => audit_opquast_statuts_verification(),
	];
}

function formulaires_editer_audit_opquast_resultat_verifier_dist($id_audit, $id_regle) {
	$erreurs = [];

	if (!trim((string) _request('statut_verification'))) {
		$erreurs['statut_verification'] = _T('info_obligatoire');
	}

	return $erreurs;
}

function formulaires_editer_audit_opquast_resultat_traiter_dist($id_audit, $id_regle) {
	include_spip('inc/autoriser');
	include_spip('inc/utils');
	include_spip('base/abstract_sql');
	include_spip('audit_opquast_fonctions');

	if (!autoriser('modifier', 'audit_opquast')) {
		return ['message_erreur' => _T('info_acces_interdit')];
	}

	$id_audit = intval($id_audit);
	$id_regle = intval($id_regle);
	$maintenant = date('Y-m-d H:i:s');
	$set = [
		'id_audit' => $id_audit,
		'id_regle' => $id_regle,
		'statut_verification' => trim((string) _request('statut_verification')) ?: 'a_verifier',
		'commentaire' => trim((string) _request('commentaire')),
		'preuve' => trim((string) _request('preuve')),
		'date_modif' => $maintenant,
		'id_auteur' => intval($GLOBALS['visiteur_session']['id_auteur'] ?? 0),
	];

	$existant = sql_fetsel(
		'id_resultat',
		'spip_audit_opquast_resultats',
		'id_audit=' . $id_audit . ' AND id_regle=' . $id_regle
	);

	if ($existant) {
		sql_updateq('spip_audit_opquast_resultats', $set, 'id_resultat=' . intval($existant['id_resultat']));
	} else {
		sql_insertq('spip_audit_opquast_resultats', $set);
	}

	$filtres = audit_opquast_parametres_filtres();
	$id_regle_redirect = audit_opquast_regle_redirect_apres_resultat($id_audit, $id_regle, $filtres);

	return [
		'message_ok' => _T('audit_opquast:message_resultat_enregistre'),
		'redirect' => audit_opquast_url_audit_filtre($id_audit, $id_regle_redirect),
	];
}
