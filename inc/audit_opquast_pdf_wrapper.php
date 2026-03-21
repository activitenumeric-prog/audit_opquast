<?php

if (!defined('_ECRIRE_INC_VERSION')) {
	return;
}

if (!defined('OPQUAST_PDF_SCRIPT')) {
	define('OPQUAST_PDF_SCRIPT', _DIR_PLUGIN_AUDIT_OPQUAST . 'scripts/create_audit_pdf.py');
}

if (!defined('OPQUAST_PDF_LANG_FILE')) {
	define('OPQUAST_PDF_LANG_FILE', _DIR_PLUGIN_AUDIT_OPQUAST . 'scripts/lang_fr.json');
}

if (!defined('OPQUAST_PYTHON_LIB_DIR')) {
	define('OPQUAST_PYTHON_LIB_DIR', _DIR_PLUGIN_AUDIT_OPQUAST . 'scripts/py_libs');
}

if (!defined('OPQUAST_PYTHON_BIN')) {
	define('OPQUAST_PYTHON_BIN', '');
}

if (!defined('OPQUAST_TMP_DIR')) {
	define('OPQUAST_TMP_DIR', _DIR_TMP . 'audit_opquast/');
}

if (!defined('OPQUAST_TMP_LIFETIME')) {
	define('OPQUAST_TMP_LIFETIME', 3600);
}

if (!defined('OPQUAST_EXEC_TIMEOUT')) {
	define('OPQUAST_EXEC_TIMEOUT', 120);
}

if (!defined('OPQUAST_MPLCONFIG_DIR')) {
	define('OPQUAST_MPLCONFIG_DIR', OPQUAST_TMP_DIR . 'matplotlib/');
}

function audit_opquast_python_commands() {
	static $commands = null;

	if ($commands !== null) {
		return $commands;
	}

	$commands = [];

	$python_bin = trim((string) OPQUAST_PYTHON_BIN);

	if ($python_bin !== '') {
		$commands[] = $python_bin;
	}

	$commands = array_merge($commands, ['python3', 'python', 'py -3']);

	if (stripos(PHP_OS_FAMILY, 'Windows') !== false) {
		$commands = array_values(array_unique(array_merge(
			$python_bin !== '' ? [$python_bin] : [],
			['py -3', 'python', 'python3']
		)));
	}

	return array_values(array_unique(array_filter($commands, 'strlen')));
}

function audit_opquast_system_command_available() {
	return function_exists('proc_open') || function_exists('exec');
}

function audit_opquast_exec_capture($command, &$output = [], $timeout = OPQUAST_EXEC_TIMEOUT) {
	$output = [];

	if (function_exists('proc_open')) {
		$descriptors = [
			0 => ['pipe', 'r'],
			1 => ['pipe', 'w'],
			2 => ['pipe', 'w'],
		];

		$process = proc_open($command, $descriptors, $pipes);

		if (!is_resource($process)) {
			$output[] = 'proc_open failed';
			return 1;
		}

		fclose($pipes[0]);
		stream_set_blocking($pipes[1], false);
		stream_set_blocking($pipes[2], false);

		$stdout = '';
		$stderr = '';
		$start = time();

		while (true) {
			$stdout .= stream_get_contents($pipes[1]);
			$stderr .= stream_get_contents($pipes[2]);

			$status = proc_get_status($process);

			if (!$status['running']) {
				$stdout .= stream_get_contents($pipes[1]);
				$stderr .= stream_get_contents($pipes[2]);
				break;
			}

			if ((time() - $start) >= intval($timeout)) {
				proc_terminate($process, 9);
				$output[] = '[TIMEOUT] Process killed after ' . intval($timeout) . 's';
				break;
			}

			usleep(100000);
		}

		fclose($pipes[1]);
		fclose($pipes[2]);

		$exit_code = proc_close($process);
		$stdout_lines = trim($stdout) !== '' ? preg_split("/\r\n|\n|\r/", trim($stdout)) : [];
		$stderr_lines = trim($stderr) !== '' ? preg_split("/\r\n|\n|\r/", trim($stderr)) : [];
		$output = array_values(array_filter(array_merge($output, $stdout_lines, $stderr_lines), 'strlen'));

		return intval($exit_code);
	}

	if (function_exists('exec')) {
		exec($command . ' 2>&1', $output, $exit_code);

		return intval($exit_code);
	}

	$output[] = 'Aucune fonction d execution systeme disponible (proc_open / exec).';

	return 1;
}

function audit_opquast_find_python_command() {
	static $python = false;
	static $checked = false;

	if ($checked) {
		return $python;
	}

	$checked = true;

	foreach (audit_opquast_python_commands() as $candidate) {
		$output = [];
		$exit_code = audit_opquast_exec_capture($candidate . ' --version', $output, 15);
		$version = trim(implode(' ', $output));

		if ($exit_code === 0 && preg_match('/Python\s+3\./i', $version)) {
			$python = $candidate;
			break;
		}
	}

	return $python;
}

function audit_opquast_python_import_ok($python, $module) {
	$output = [];
	$command = audit_opquast_python_command(
		$python . ' -c ' . escapeshellarg("import $module; print('ok')")
	);
	$exit_code = audit_opquast_exec_capture($command, $output, 20);

	return $exit_code === 0 && trim(implode('', $output)) === 'ok';
}

function audit_opquast_python_command($command) {
	$lib_dir = OPQUAST_PYTHON_LIB_DIR;

	if (!is_dir($lib_dir)) {
		return $command;
	}

	if (stripos(PHP_OS_FAMILY, 'Windows') !== false) {
		return 'set "PYTHONPATH=' . str_replace('"', '', $lib_dir) . '" && set "MPLCONFIGDIR=' . str_replace('"', '', OPQUAST_MPLCONFIG_DIR) . '" && ' . $command;
	}

	return 'PYTHONPATH=' . escapeshellarg($lib_dir) . ' MPLCONFIGDIR=' . escapeshellarg(OPQUAST_MPLCONFIG_DIR) . ' ' . $command;
}

function audit_opquast_prepare_tmp_dir() {
	if (!is_dir(OPQUAST_TMP_DIR)) {
		if (!@mkdir(OPQUAST_TMP_DIR, 0750, true)) {
			return false;
		}
	}

	if (!is_dir(OPQUAST_MPLCONFIG_DIR) && !@mkdir(OPQUAST_MPLCONFIG_DIR, 0750, true)) {
		return false;
	}

	return is_writable(OPQUAST_TMP_DIR) && is_writable(OPQUAST_MPLCONFIG_DIR);
}

function audit_opquast_cleanup_tmp() {
	if (!is_dir(OPQUAST_TMP_DIR)) {
		return;
	}

	$now = time();
	$files = glob(OPQUAST_TMP_DIR . 'opquast_*');

	if (!$files) {
		return;
	}

	foreach ($files as $file) {
		if (is_file($file) && ($now - filemtime($file)) > OPQUAST_TMP_LIFETIME) {
			@unlink($file);
		}
	}
}

function audit_opquast_sanitize_filename($name) {
	$name = (string) $name;

	if (function_exists('translitteration')) {
		$name = translitteration($name);
	}

	$name = preg_replace('/[^\w\-.]+/', '_', $name);
	$name = preg_replace('/_+/', '_', $name);
	$name = trim((string) $name, '_');

	return $name !== '' ? substr($name, 0, 80) : 'audit_opquast';
}

function audit_opquast_send_pdf($pdf_path, $filename) {
	if (!is_file($pdf_path) || headers_sent()) {
		return false;
	}

	header('Content-Type: application/pdf');
	header('Content-Disposition: attachment; filename="' . str_replace('"', '', $filename) . '"');
	header('Content-Length: ' . filesize($pdf_path));
	header('Cache-Control: private, max-age=0, must-revalidate');
	header('Pragma: public');

	readfile($pdf_path);

	return true;
}

function audit_opquast_rows_to_csv($rows) {
	if (!is_array($rows) || !$rows) {
		return '';
	}

	$columns = [
		'Audit',
		'URL cible',
		'Type de cible',
		'Statut audit',
		'Numero regle',
		'Intitule',
		'Famille',
		'Statut',
		'Commentaire',
		'Preuve ou note',
		'Source',
	];

	$stream = fopen('php://temp', 'r+');

	if (!$stream) {
		return '';
	}

	fwrite($stream, "\xEF\xBB\xBF");
	fputcsv($stream, $columns, ';');

	foreach ($rows as $row) {
		fputcsv($stream, [
			$row['audit_titre'] ?? '',
			$row['url_cible'] ?? '',
			$row['type_cible'] ?? '',
			$row['statut_audit'] ?? '',
			$row['numero'] ?? '',
			$row['titre'] ?? '',
			$row['famille'] ?? '',
			$row['statut_verification'] ?? '',
			$row['commentaire'] ?? '',
			$row['preuve'] ?? '',
			$row['url_source'] ?? '',
		], ';');
	}

	rewind($stream);
	$csv = stream_get_contents($stream);
	fclose($stream);

	return (string) $csv;
}

function audit_opquast_generate_pdf($csv_content, $audit_name = 'audit_opquast', $return_path = false) {
	if (trim((string) $csv_content) === '') {
		spip_log('[audit_opquast] CSV vide, generation PDF impossible.', 'audit_opquast');
		return false;
	}

	if (!audit_opquast_prepare_tmp_dir()) {
		spip_log('[audit_opquast] Dossier temporaire inaccessible : ' . OPQUAST_TMP_DIR, 'audit_opquast');
		return false;
	}

	$python = audit_opquast_find_python_command();

	if (!$python) {
		spip_log('[audit_opquast] Aucun interpreteur Python 3 compatible n a ete trouve.', 'audit_opquast');
		return false;
	}

	if (!is_file(OPQUAST_PDF_SCRIPT)) {
		spip_log('[audit_opquast] Script PDF introuvable : ' . OPQUAST_PDF_SCRIPT, 'audit_opquast');
		return false;
	}

	audit_opquast_cleanup_tmp();

	$uid = uniqid('opquast_', true);
	$csv_tmp = OPQUAST_TMP_DIR . $uid . '.csv';
	$pdf_tmp = OPQUAST_TMP_DIR . $uid . '.pdf';

	if (file_put_contents($csv_tmp, $csv_content) === false) {
		spip_log('[audit_opquast] Echec ecriture CSV temporaire.', 'audit_opquast');
		return false;
	}

	$command = audit_opquast_python_command(
		$python
		. ' ' . escapeshellarg(OPQUAST_PDF_SCRIPT)
		. ' ' . escapeshellarg($csv_tmp)
		. ' ' . escapeshellarg($pdf_tmp)
	);

	$output = [];
	$exit_code = audit_opquast_exec_capture($command, $output, OPQUAST_EXEC_TIMEOUT);
	$log_output = implode(' | ', $output);

	if ($exit_code !== 0 || !is_file($pdf_tmp) || filesize($pdf_tmp) === 0) {
		spip_log(
			'[audit_opquast] Erreur generation PDF. Commande=' . $command . ' | Code=' . $exit_code . ' | Sortie=' . $log_output,
			'audit_opquast'
		);
		@unlink($csv_tmp);
		@unlink($pdf_tmp);
		return false;
	}

	@unlink($csv_tmp);

	if ($return_path) {
		return $pdf_tmp;
	}

	$filename = audit_opquast_sanitize_filename($audit_name) . '_' . date('Ymd') . '.pdf';
	$sent = audit_opquast_send_pdf($pdf_tmp, $filename);
	@unlink($pdf_tmp);

	return $sent;
}

function audit_opquast_export_pdf_action($id_audit) {
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
		spip_log('[audit_opquast] CSV vide pour l audit #' . $id_audit, 'audit_opquast');
		return false;
	}

	return audit_opquast_generate_pdf($csv_content, $audit['titre'] ?? ('audit_' . $id_audit), false);
}

function audit_opquast_check_requirements() {
	$messages = [];
	$ok = true;

	$python = audit_opquast_find_python_command();

	if (!audit_opquast_system_command_available()) {
		$messages[] = 'Aucune fonction PHP d execution systeme disponible (proc_open / exec).';
		$ok = false;
	} elseif (!$python) {
		$messages[] = OPQUAST_PYTHON_BIN !== ''
			? 'Python 3 non trouve au chemin configure : ' . OPQUAST_PYTHON_BIN
			: 'Python 3 non trouve.';
		$ok = false;
	} else {
		$messages[] = 'Python detecte : ' . $python;
	}

	if (!is_file(OPQUAST_PDF_SCRIPT)) {
		$messages[] = 'Script Python introuvable : ' . OPQUAST_PDF_SCRIPT;
		$ok = false;
	}

	if (!is_file(OPQUAST_PDF_LANG_FILE)) {
		$messages[] = 'Fichier de langue introuvable : ' . OPQUAST_PDF_LANG_FILE;
		$ok = false;
	}

	if (!is_dir(OPQUAST_PYTHON_LIB_DIR)) {
		$messages[] = 'Bibliotheques Python locales introuvables : ' . OPQUAST_PYTHON_LIB_DIR;
		$ok = false;
	}

	if ($python) {
		foreach (['reportlab', 'matplotlib', 'numpy'] as $module) {
			if (!audit_opquast_python_import_ok($python, $module)) {
				$messages[] = 'Module Python manquant : ' . $module;
				$ok = false;
			}
		}
	}

	if (!audit_opquast_prepare_tmp_dir()) {
		$messages[] = 'Dossier temporaire inaccessible : ' . OPQUAST_TMP_DIR;
		$ok = false;
	}

	return [
		'ok' => $ok,
		'messages' => $messages,
	];
}

function audit_opquast_html_diagnostic_requirements($check) {
	$messages = is_array($check['messages'] ?? null) ? $check['messages'] : [];

	if (!$messages) {
		return '';
	}

	$html = '<p>' . _T('audit_opquast:info_restitution_generation_impossible') . '</p>';
	$html .= '<p><strong>' . _T('audit_opquast:info_restitution_prerequis_titre') . '</strong></p>';
	$html .= '<ul>';

	foreach ($messages as $message) {
		$html .= '<li>' . htmlspecialchars((string) $message, ENT_QUOTES, 'UTF-8') . '</li>';
	}

	$html .= '</ul>';

	return $html;
}
