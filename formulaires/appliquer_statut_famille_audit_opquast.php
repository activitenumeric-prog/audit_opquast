<?php

if (!defined('_ECRIRE_INC_VERSION')) {
	return;
}

function formulaires_appliquer_statut_famille_audit_opquast_charger_dist($id_audit, $famille, $id_audit_site = 0) {
	include_spip('inc/autoriser');

	if (!autoriser('modifier', 'audit_opquast')) {
		return false;
	}

	return [
		'id_audit' => intval($id_audit),
		'id_audit_site' => intval($id_audit_site),
		'famille_cible' => trim((string) $famille),
		'id_regle' => intval(_request('id_regle')),
		'q' => trim((string) _request('q')),
		'famille' => trim((string) _request('famille')),
		'statut_verification_filtre' => trim((string) _request('statut_verification')),
		'tri' => trim((string) _request('tri')),
	];
}

function formulaires_appliquer_statut_famille_audit_opquast_verifier_dist($id_audit, $famille, $id_audit_site = 0) {
	$erreurs = [];
	$statut_cible = trim((string) _request('statut_cible'));

	if (!in_array($statut_cible, ['a_verifier', 'non_applicable'], true)) {
		$erreurs['message_erreur'] = _T('info_acces_interdit');
	}

	return $erreurs;
}

function formulaires_appliquer_statut_famille_audit_opquast_traiter_dist($id_audit, $famille, $id_audit_site = 0) {
	include_spip('inc/autoriser');
	include_spip('audit_opquast_fonctions');

	if (!autoriser('modifier', 'audit_opquast')) {
		return ['message_erreur' => _T('info_acces_interdit')];
	}

	$id_audit = intval($id_audit);
	$famille = trim((string) $famille);
	$statut_cible = trim((string) _request('statut_cible'));
	$id_auteur = intval($GLOBALS['visiteur_session']['id_auteur'] ?? 0);
	$nb = audit_opquast_appliquer_statut_famille($id_audit, $famille, $statut_cible, $id_auteur);
	$id_audit_site = intval($id_audit_site ?: _request('id_audit_site'));
	$redirect = $id_audit_site
		? audit_opquast_url_site_famille_action($id_audit_site, $id_audit, $famille)
		: audit_opquast_url_audit_famille_action($id_audit, $famille);

	return [
		'message_ok' => _T(
			'audit_opquast:message_famille_statut_applique',
			[
				'nb' => $nb,
				'famille' => $famille,
				'statut' => audit_opquast_statuts_verification($statut_cible),
			]
		),
		'redirect' => $redirect,
	];
}
