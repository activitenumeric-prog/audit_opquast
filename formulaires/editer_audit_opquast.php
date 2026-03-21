<?php

if (!defined('_ECRIRE_INC_VERSION')) {
	return;
}

function formulaires_editer_audit_opquast_charger_dist($id_audit = 0) {
	include_spip('inc/autoriser');
	include_spip('audit_opquast_fonctions');

	$id_audit = intval($id_audit);

	if (($id_audit && !autoriser('modifier', 'audit_opquast')) || (!$id_audit && !autoriser('creer', 'audit_opquast'))) {
		return false;
	}

	$valeurs = [
		'id_audit' => $id_audit,
		'titre' => '',
		'url_cible' => '',
		'urls_site' => '',
		'type_cible' => 'url',
		'statut' => 'brouillon',
	];

	if ($id_audit) {
		$audit = audit_opquast_lire_audit($id_audit);
		if ($audit) {
			$valeurs = array_merge($valeurs, $audit);
			$valeurs['type_cible'] = ($valeurs['type_cible'] ?? '') === 'site' ? 'site' : 'url';
			$valeurs['urls_site'] = audit_opquast_urls_site_texte($audit);
		}
	}

	$valeurs['_statuts_audit'] = audit_opquast_statuts_audit();
	$valeurs['_types_cible'] = audit_opquast_types_cible();
	$valeurs['_submit_label'] = $id_audit
		? _T('audit_opquast:bouton_mettre_a_jour_audit')
		: _T('audit_opquast:bouton_creer_audit');

	return $valeurs;
}

function formulaires_editer_audit_opquast_verifier_dist($id_audit = 0) {
	include_spip('audit_opquast_fonctions');

	$erreurs = [];
	$type_cible = trim((string) _request('type_cible')) === 'site' ? 'site' : 'url';
	$url_cible = trim((string) _request('url_cible'));

	if (!trim((string) _request('titre'))) {
		$erreurs['titre'] = _T('info_obligatoire');
	}

	if ($type_cible === 'url' && !$url_cible) {
		$erreurs['url_cible'] = _T('info_obligatoire');
	}

	if ($type_cible === 'site') {
		$urls = audit_opquast_normaliser_urls_site((string) _request('urls_site'), $url_cible);

		if (!$urls) {
			$erreurs['urls_site'] = _T('info_obligatoire');
		}
	}

	return $erreurs;
}

function formulaires_editer_audit_opquast_traiter_dist($id_audit = 0) {
	include_spip('inc/autoriser');
	include_spip('inc/utils');
	include_spip('base/abstract_sql');
	include_spip('audit_opquast_fonctions');

	$id_audit = intval($id_audit);

	if (($id_audit && !autoriser('modifier', 'audit_opquast')) || (!$id_audit && !autoriser('creer', 'audit_opquast'))) {
		return ['message_erreur' => _T('info_acces_interdit')];
	}

	$maintenant = date('Y-m-d H:i:s');
	$type_cible = trim((string) _request('type_cible')) === 'site' ? 'site' : 'url';
	$url_cible = trim((string) _request('url_cible'));
	$urls_site = audit_opquast_normaliser_urls_site((string) _request('urls_site'), $url_cible);
	$resume = '';

	if ($type_cible === 'site') {
		$resume = json_encode(['urls_site' => $urls_site], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
		$url_cible = trim((string) ($urls_site[0] ?? ''));
	}

	$set = [
		'titre' => trim((string) _request('titre')),
		'url_cible' => $url_cible,
		'resume' => $resume,
		'type_cible' => $type_cible,
		'statut' => trim((string) _request('statut')) ?: 'brouillon',
		'date_modif' => $maintenant,
	];

	if ($id_audit) {
		sql_updateq('spip_audit_opquast_audits', $set, 'id_audit=' . $id_audit);
	} else {
		$set['date_creation'] = $maintenant;
		$set['id_auteur'] = intval($GLOBALS['visiteur_session']['id_auteur'] ?? 0);
		$id_audit = intval(sql_insertq('spip_audit_opquast_audits', $set));
	}

	if ($type_cible === 'site' && $id_audit) {
		$audit = audit_opquast_lire_audit($id_audit);
		audit_opquast_sync_audit_site_urls($audit, $urls_site);
	}

	$id_regle = intval(_request('id_regle'));
	$redirect = $id_regle
		? audit_opquast_url_audit_filtre($id_audit, $id_regle)
		: audit_opquast_url_audit($id_audit);

	return [
		'message_ok' => _T('audit_opquast:message_audit_enregistre'),
		'redirect' => $redirect,
	];
}
