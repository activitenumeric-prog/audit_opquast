<?php

if (!defined('_ECRIRE_INC_VERSION')) {
	return;
}

function action_audit_opquast_appliquer_statut_famille_dist($arg = null) {
	include_spip('inc/actions');
	include_spip('inc/autoriser');
	include_spip('audit_opquast_fonctions');

	if ($arg === null) {
		$securiser_action = charger_fonction('securiser_action', 'inc');
		$arg = $securiser_action();
	}

	$id_audit = intval($arg);
	$id_audit_site = intval(_request('id_audit_site'));
	$famille = trim((string) _request('famille_cible'));
	$statut_cible = trim((string) _request('statut_cible'));

	if (
		!$id_audit
		|| $famille === ''
		|| !in_array($statut_cible, ['a_verifier', 'non_applicable'], true)
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

	$id_auteur = intval($GLOBALS['visiteur_session']['id_auteur'] ?? 0);
	$nb = audit_opquast_appliquer_statut_famille($id_audit, $famille, $statut_cible, $id_auteur);
	$id_regle = intval(_request('id_regle'));
	$redirect = $id_audit_site
		? audit_opquast_url_site($id_audit_site, $id_audit, $id_regle, ['famille' => $famille])
		: audit_opquast_url_audit_filtre($id_audit, $id_regle, ['famille' => $famille]);

	header('Content-Type: application/json; charset=UTF-8');
	echo json_encode([
		'ok' => true,
		'message' => _T(
			'audit_opquast:message_famille_statut_applique',
			[
				'nb' => $nb,
				'famille' => $famille,
				'statut' => audit_opquast_statuts_verification($statut_cible),
			]
		),
		'redirect' => $redirect . '#resume-familles',
	]);
	exit;
}
