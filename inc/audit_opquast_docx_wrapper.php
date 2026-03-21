<?php

if (!defined('_ECRIRE_INC_VERSION')) {
	return;
}

include_spip('inc/audit_opquast_pdf_wrapper');

if (!defined('OPQUAST_DOCX_SCRIPT')) {
	define('OPQUAST_DOCX_SCRIPT', _DIR_PLUGIN_AUDIT_OPQUAST . 'scripts/create_audit_docx.py');
}

function audit_opquast_set_docx_error($message) {
	$GLOBALS['audit_opquast_docx_last_error'] = trim((string) $message);
}

function audit_opquast_get_docx_error() {
	return trim((string) ($GLOBALS['audit_opquast_docx_last_error'] ?? ''));
}

function audit_opquast_send_docx($docx_path, $filename) {
	if (!is_file($docx_path) || headers_sent()) {
		return false;
	}

	header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
	header('Content-Disposition: attachment; filename="' . str_replace('"', '', $filename) . '"');
	header('Content-Length: ' . filesize($docx_path));
	header('Cache-Control: private, max-age=0, must-revalidate');
	header('Pragma: public');

	readfile($docx_path);

	return true;
}

function audit_opquast_generate_docx($csv_content, $audit_name = 'audit_opquast', $return_path = false) {
	audit_opquast_set_docx_error('');

	if (trim((string) $csv_content) === '') {
		audit_opquast_set_docx_error('CSV vide, generation DOCX impossible.');
		spip_log('[audit_opquast] CSV vide, generation DOCX impossible.', 'audit_opquast');
		return false;
	}

	if (!audit_opquast_prepare_tmp_dir()) {
		audit_opquast_set_docx_error('Dossier temporaire inaccessible : ' . OPQUAST_TMP_DIR);
		spip_log('[audit_opquast] Dossier temporaire inaccessible pour DOCX : ' . OPQUAST_TMP_DIR, 'audit_opquast');
		return false;
	}

	$python = audit_opquast_find_python_command();

	if (!$python) {
		audit_opquast_set_docx_error('Aucun interpreteur Python 3 compatible n a ete trouve.');
		spip_log('[audit_opquast] Aucun interpreteur Python 3 compatible n a ete trouve pour DOCX.', 'audit_opquast');
		return false;
	}

	if (!is_file(OPQUAST_DOCX_SCRIPT)) {
		audit_opquast_set_docx_error('Script DOCX introuvable : ' . OPQUAST_DOCX_SCRIPT);
		spip_log('[audit_opquast] Script DOCX introuvable : ' . OPQUAST_DOCX_SCRIPT, 'audit_opquast');
		return false;
	}

	audit_opquast_cleanup_tmp();

	$uid = uniqid('opquast_', true);
	$csv_tmp = OPQUAST_TMP_DIR . $uid . '.csv';
	$docx_tmp = OPQUAST_TMP_DIR . $uid . '.docx';

	if (file_put_contents($csv_tmp, $csv_content) === false) {
		audit_opquast_set_docx_error('Echec ecriture CSV temporaire : ' . $csv_tmp);
		spip_log('[audit_opquast] Echec ecriture CSV temporaire pour DOCX.', 'audit_opquast');
		return false;
	}

	$command = audit_opquast_python_command(
		$python
		. ' ' . escapeshellarg(OPQUAST_DOCX_SCRIPT)
		. ' ' . escapeshellarg($csv_tmp)
		. ' ' . escapeshellarg($docx_tmp)
	);

	$output = [];
	$exit_code = audit_opquast_exec_capture($command, $output, OPQUAST_EXEC_TIMEOUT);
	$log_output = implode(' | ', $output);

	if ($exit_code !== 0 || !is_file($docx_tmp) || filesize($docx_tmp) === 0) {
		audit_opquast_set_docx_error(
			'Erreur generation DOCX'
			. ' | Code=' . $exit_code
			. ($log_output !== '' ? ' | Sortie=' . $log_output : '')
		);
		spip_log(
			'[audit_opquast] Erreur generation DOCX. Commande=' . $command . ' | Code=' . $exit_code . ' | Sortie=' . $log_output,
			'audit_opquast'
		);
		@unlink($csv_tmp);
		@unlink($docx_tmp);
		return false;
	}

	@unlink($csv_tmp);

	if ($return_path) {
		return $docx_tmp;
	}

	$filename = audit_opquast_sanitize_filename($audit_name) . '_' . date('Ymd') . '.docx';
	$sent = audit_opquast_send_docx($docx_tmp, $filename);
	@unlink($docx_tmp);

	if (!$sent) {
		audit_opquast_set_docx_error('Le DOCX a ete genere mais n a pas pu etre envoye au navigateur.');
	}

	return $sent;
}

function audit_opquast_export_docx_action($id_audit) {
	include_spip('audit_opquast_fonctions');

	$id_audit = intval($id_audit);

	if (!$id_audit) {
		return false;
	}

	$audit = audit_opquast_lire_audit($id_audit);

	if (!$audit || ($audit['type_cible'] ?? '') !== 'url') {
		return false;
	}

	$rows = audit_opquast_donnees_export_audit($id_audit);
	$csv_content = audit_opquast_rows_to_csv($rows);

	if ($csv_content === '') {
		audit_opquast_set_docx_error('CSV vide pour l audit #' . $id_audit . '.');
		spip_log('[audit_opquast] CSV vide pour l audit #' . $id_audit . ' (DOCX)', 'audit_opquast');
		return false;
	}

	return audit_opquast_generate_docx($csv_content, $audit['titre'] ?? ('audit_' . $id_audit), false);
}

function audit_opquast_check_docx_requirements() {
	$messages = [];
	$ok = true;

	$python = audit_opquast_find_python_command();

	if (!audit_opquast_system_command_available()) {
		$messages[] = 'Aucune fonction PHP d execution systeme disponible (proc_open / exec).';
		$ok = false;
	} elseif (!$python) {
		$python_bin_configured = audit_opquast_python_bin_configured();
		$messages[] = $python_bin_configured !== ''
			? 'Python 3 non trouve au chemin configure : ' . $python_bin_configured
			: 'Python 3 non trouve.';
		$probe_details = audit_opquast_get_python_probe_details();
		if ($probe_details !== '') {
			$messages[] = 'Diagnostic detection Python : ' . $probe_details;
		}
		$ok = false;
	} else {
		$messages[] = 'Python detecte : ' . $python;
	}

	if (!is_file(OPQUAST_DOCX_SCRIPT)) {
		$messages[] = 'Script Python DOCX introuvable : ' . OPQUAST_DOCX_SCRIPT;
		$ok = false;
	}

	if (!audit_opquast_prepare_tmp_dir()) {
		$messages[] = 'Dossier temporaire inaccessible : ' . OPQUAST_TMP_DIR;
		$ok = false;
	}

	$messages[] = is_dir(OPQUAST_PYTHON_LIB_DIR)
		? 'Bibliotheques Python embarquees detectees : ' . OPQUAST_PYTHON_LIB_DIR
		: 'Aucune bibliotheque Python embarquee detectee, utilisation des bibliotheques systeme si disponibles.';

	return [
		'ok' => $ok,
		'messages' => $messages,
	];
}
