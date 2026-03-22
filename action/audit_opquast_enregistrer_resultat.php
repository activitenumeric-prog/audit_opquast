<?php

if (!defined('_ECRIRE_INC_VERSION')) {
	return;
}

function action_audit_opquast_enregistrer_resultat_dist($arg = null) {
	include_spip('inc/actions');
	include_spip('inc/autoriser');
	include_spip('base/abstract_sql');
	include_spip('audit_opquast_fonctions');

	if ($arg === null) {
		$securiser_action = charger_fonction('securiser_action', 'inc');
		$arg = $securiser_action();
	}

	$arg = trim((string) $arg);
	$parts = explode('-', $arg, 2);
	$id_audit = intval($parts[0] ?? 0);
	$id_regle = intval($parts[1] ?? 0);
	$id_audit_site = intval(_request('id_audit_site'));

	if (
		!$id_audit
		|| !$id_regle
		|| !autoriser('modifier', 'audit_opquast')
	) {
		http_response_code(403);
		header('Content-Type: application/json; charset=UTF-8');
		echo json_encode([
			'ok' => false,
			'message' => _T('info_acces_interdit'),
		]);
		exit;
	}

	$maintenant = date('Y-m-d H:i:s');
	$filtres = audit_opquast_parametres_filtres([
		'q' => trim((string) _request('q')),
		'famille' => trim((string) _request('famille')),
		'statut_verification' => trim((string) _request('statut_verification_filtre')),
		'tri' => trim((string) _request('tri')),
	]);
	$navigation_avant = audit_opquast_navigation_regle($id_audit, $id_regle);
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

	$id_regle_redirect = audit_opquast_regle_redirect_apres_resultat($id_audit, $id_regle, $filtres);
	$redirect = $id_audit_site
		? audit_opquast_url_site($id_audit_site, $id_audit, $id_regle_redirect, $filtres)
		: audit_opquast_url_audit_filtre($id_audit, $id_regle_redirect, $filtres);

	if (
		$id_regle_redirect === $id_regle
		&& audit_opquast_regle_visible_dans_filtres($id_audit, $id_regle, $filtres)
		&& !empty($navigation_avant['position'])
	) {
		include_spip('inc/utils');
		$redirect = parametre_url($redirect, 'navigation_freeze', 'oui', '&');
		$redirect = parametre_url($redirect, 'navigation_freeze_position', intval($navigation_avant['position']), '&');
		$redirect = parametre_url($redirect, 'navigation_freeze_total', intval($navigation_avant['total'] ?? 0), '&');
		$redirect = parametre_url($redirect, 'navigation_freeze_precedente_id', intval($navigation_avant['precedente_id_regle'] ?? 0), '&');
		$redirect = parametre_url($redirect, 'navigation_freeze_suivante_id', intval($navigation_avant['suivante_id_regle'] ?? 0), '&');
	}

	header('Content-Type: application/json; charset=UTF-8');
	echo json_encode([
		'ok' => true,
		'message' => _T('audit_opquast:message_resultat_enregistre'),
		'redirect' => $redirect,
		'id_regle' => $id_regle_redirect,
	]);
	exit;
}
