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
		include_spip('inc/audit_opquast_docx_wrapper');

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

		if ($format !== '' && !in_array($format, ['pdf', 'docx'], true)) {
			echo minipres(_T('audit_opquast:info_restitution_generation_impossible'));
			exit;
		}

		$is_docx = ($format === 'docx');

		if ($is_docx && !in_array(($audit['type_cible'] ?? ''), ['url', 'site'], true)) {
			echo minipres(_T('audit_opquast:info_restitution_docx_url_site_seulement'));
			exit;
		}

		if (!$is_docx && !in_array(($audit['type_cible'] ?? ''), ['url', 'site'], true)) {
			echo minipres(_T('audit_opquast:info_restitution_pdf_url_site_seulement'));
			exit;
		}

		$check = $is_docx ? audit_opquast_check_docx_requirements() : audit_opquast_check_requirements();

		if (!$check['ok']) {
			spip_log(
				'[audit_opquast] Prerequis restitution manquants pour l audit #' . $id_audit . ' : ' . implode(' | ', $check['messages']),
				'audit_opquast'
			);
			echo minipres('', audit_opquast_html_diagnostic_requirements($check, $is_docx ? 'DOCX' : 'PDF'));
			exit;
		}

		$ok = $is_docx
			? audit_opquast_export_docx_action($id_audit)
			: audit_opquast_export_pdf_action($id_audit);

		if (!$ok) {
			$detail = audit_opquast_html_generation_error(
				$is_docx ? 'DOCX' : 'PDF',
				$is_docx ? audit_opquast_get_docx_error() : null
			);
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
