<?php

if (!defined('_ECRIRE_INC_VERSION')) {
	return;
}

function action_audit_opquast_export_pdf_dist($arg = null) {
	$id_audit = 0;

	try {
		include_spip('inc/actions');
		include_spip('inc/autoriser');
		include_spip('inc/minipres');
		include_spip('audit_opquast_fonctions');
		include_spip('inc/audit_opquast_pdf_wrapper');

		if ($arg === null) {
			$securiser_action = charger_fonction('securiser_action', 'inc');
			$arg = $securiser_action();
		}

		$id_audit = intval($arg);
		$format = trim((string) _request('format_restitution'));

		if (
			!$id_audit
			|| !autoriser('voir', 'audit_opquast')
		) {
			echo minipres(_T('info_acces_interdit'));
			exit;
		}

		$audit = audit_opquast_lire_audit($id_audit);

		if (!$audit) {
			echo minipres(_T('audit_opquast:info_audit_introuvable'));
			exit;
		}

		if (($audit['type_cible'] ?? '') !== 'url') {
			echo minipres(_T('audit_opquast:info_restitution_pdf_url_seulement'));
			exit;
		}

		if ($format !== '' && $format !== 'pdf') {
			echo minipres(_T('audit_opquast:info_restitution_generation_impossible'));
			exit;
		}

		$check = audit_opquast_check_requirements();

		if (!$check['ok']) {
			spip_log(
				'[audit_opquast] Prerequis PDF manquants pour l audit #' . $id_audit . ' : ' . implode(' | ', $check['messages']),
				'audit_opquast'
			);
			echo minipres('', audit_opquast_html_diagnostic_requirements($check));
			exit;
		}

		if (!audit_opquast_export_pdf_action($id_audit)) {
			$detail = audit_opquast_html_generation_error();
			echo minipres('', $detail !== '' ? $detail : _T('audit_opquast:info_restitution_generation_impossible'));
			exit;
		}

		exit;
	} catch (\Throwable $e) {
		include_spip('inc/minipres');
		spip_log(
			'[audit_opquast] Exception PDF audit #' . intval($id_audit) . ' : '
			. $e->getMessage()
			. ' @ ' . $e->getFile() . ':' . $e->getLine()
			. ' | ' . $e->getTraceAsString(),
			'audit_opquast'
		);
		echo minipres(_T('audit_opquast:info_restitution_generation_impossible'));
		exit;
	}
}
