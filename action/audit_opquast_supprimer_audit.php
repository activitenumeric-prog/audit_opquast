<?php

if (!defined('_ECRIRE_INC_VERSION')) {
	return;
}

function action_audit_opquast_supprimer_audit_dist($arg = null) {
	include_spip('inc/actions');
	include_spip('inc/autoriser');
	include_spip('base/abstract_sql');

	if ($arg === null) {
		$securiser_action = charger_fonction('securiser_action', 'inc');
		$arg = $securiser_action();
	}

	$id_audit = intval($arg);

	if (!$id_audit || !autoriser('supprimer', 'audit_opquast', $id_audit)) {
		return;
	}

	sql_delete('spip_audit_opquast_resultats', 'id_audit=' . $id_audit);
	sql_delete('spip_audit_opquast_audits', 'id_audit=' . $id_audit);
}
