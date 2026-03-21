<?php

if (!defined('_ECRIRE_INC_VERSION')) {
	return;
}

function action_audit_opquast_generer_restitution_dist($arg = null) {
	include_spip('inc/actions');
	include_spip('inc/autoriser');
	include_spip('audit_opquast_fonctions');

	if ($arg === null) {
		$securiser_action = charger_fonction('securiser_action', 'inc');
		$arg = $securiser_action();
	}

	$id_audit = intval($arg);
	if (
		!$id_audit
		|| !autoriser('voir', 'audit_opquast')
	) {
		include_spip('inc/minipres');
		echo minipres(_T('info_acces_interdit'));
		exit;
	}

	$audit = audit_opquast_lire_audit($id_audit);

	if (!$audit) {
		include_spip('inc/minipres');
		echo minipres(_T('audit_opquast:info_audit_introuvable'));
		exit;
	}

	include_spip('inc/minipres');
	echo minipres(_T('audit_opquast:info_restitution_generation_impossible'));
	exit;
}
