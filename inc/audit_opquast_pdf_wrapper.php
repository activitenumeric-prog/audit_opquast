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

function audit_opquast_set_pdf_error($message) {
	$GLOBALS['audit_opquast_pdf_last_error'] = trim((string) $message);
}

function audit_opquast_get_pdf_error() {
	return trim((string) ($GLOBALS['audit_opquast_pdf_last_error'] ?? ''));
}

function audit_opquast_set_python_probe_details($details) {
	$GLOBALS['audit_opquast_python_probe_details'] = trim((string) $details);
}

function audit_opquast_get_python_probe_details() {
	return trim((string) ($GLOBALS['audit_opquast_python_probe_details'] ?? ''));
}

function audit_opquast_python_commands() {
	static $commands = null;

	if ($commands !== null) {
		return $commands;
	}

	$commands = [];

	$python_bin = audit_opquast_python_bin_configured();

	if ($python_bin !== '') {
		$commands[] = $python_bin;
	}

	$commands = array_merge($commands, ['python3', 'python', 'py -3']);

	if (stripos(PHP_OS_FAMILY, 'Windows') !== false) {
		$commands = array_values(array_unique(array_merge(
			$python_bin !== '' ? [$python_bin] : [],
			['python', 'py -3', 'python3']
		)));
	}

	return array_values(array_unique(array_filter($commands, 'strlen')));
}

function audit_opquast_python_bin_configured() {
	$python_bin = trim((string) OPQUAST_PYTHON_BIN);

	if ($python_bin !== '') {
		return $python_bin;
	}

	include_spip('inc/config');

	$python_env = function_exists('lire_config')
		? trim((string) lire_config('audit_opquast/python_environment', 'local'))
		: 'local';
	$python_bin = function_exists('lire_config')
		? trim((string) lire_config('audit_opquast/python_bin', ''))
		: '';

	if ($python_bin !== '') {
		return $python_bin;
	}

	if ($python_env === 'externe') {
		return '/bin/python3';
	}

	return '';
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
		$process_exit_code = null;

		while (true) {
			$stdout .= stream_get_contents($pipes[1]);
			$stderr .= stream_get_contents($pipes[2]);

			$status = proc_get_status($process);

			if (!$status['running']) {
				if (isset($status['exitcode']) && $status['exitcode'] >= 0) {
					$process_exit_code = intval($status['exitcode']);
				}
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
		if ($exit_code < 0 && $process_exit_code !== null) {
			$exit_code = $process_exit_code;
		}
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
	$details = [];

	foreach (audit_opquast_python_commands() as $candidate) {
		$output = [];
		$exit_code = audit_opquast_exec_capture($candidate . ' --version', $output, 15);
		$version = trim(implode(' ', $output));
		$details[] = $candidate . ' => code ' . $exit_code . ($version !== '' ? ' | ' . $version : '');

		if ($exit_code === 0 && preg_match('/Python\s+3\./i', $version)) {
			$python = $candidate;
			break;
		}
	}

	audit_opquast_set_python_probe_details(implode(' || ', $details));

	return $python;
}

function audit_opquast_python_import_ok($python, $module) {
	$output = [];
	if (!audit_opquast_prepare_tmp_dir()) {
		return false;
	}
	$tmp_script = OPQUAST_TMP_DIR . 'import_' . preg_replace('/[^\w\-]+/', '_', $module) . '_' . uniqid('', true) . '.py';
	$code = "import importlib, sys\nimportlib.import_module(" . var_export($module, true) . ")\nsys.stdout.write('ok')\n";

	if (file_put_contents($tmp_script, $code) === false) {
		return false;
	}

	$command = audit_opquast_python_command(
		$python . ' ' . escapeshellarg($tmp_script)
	);
	$exit_code = audit_opquast_exec_capture($command, $output, 20);
	@unlink($tmp_script);

	return $exit_code === 0 && preg_match('/\bok\b/i', implode("\n", $output));
}

function audit_opquast_python_module_embedded($module) {
	$module = trim((string) $module);

	if ($module === '' || !is_dir(OPQUAST_PYTHON_LIB_DIR)) {
		return false;
	}

	$module_dir = OPQUAST_PYTHON_LIB_DIR . DIRECTORY_SEPARATOR . $module;
	$module_file = OPQUAST_PYTHON_LIB_DIR . DIRECTORY_SEPARATOR . $module . '.py';

	return is_dir($module_dir) || is_file($module_file);
}

function audit_opquast_python_command($command) {
	$lib_dir = OPQUAST_PYTHON_LIB_DIR;

	if (!is_dir($lib_dir)) {
		return $command;
	}

	if (stripos(PHP_OS_FAMILY, 'Windows') !== false) {
		$env_command = 'set "PYTHONPATH=' . str_replace('"', '', $lib_dir) . '" && ' . $command;
		return 'cmd /V:OFF /C ' . escapeshellarg($env_command);
	}

	return 'PYTHONPATH=' . escapeshellarg($lib_dir) . ' ' . $command;
}

function audit_opquast_prepare_tmp_dir() {
	if (!is_dir(OPQUAST_TMP_DIR)) {
		if (!@mkdir(OPQUAST_TMP_DIR, 0750, true)) {
			return false;
		}
	}

	return is_writable(OPQUAST_TMP_DIR);
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
	audit_opquast_set_pdf_error('');

	if (trim((string) $csv_content) === '') {
		audit_opquast_set_pdf_error('CSV vide, generation PDF impossible.');
		spip_log('[audit_opquast] CSV vide, generation PDF impossible.', 'audit_opquast');
		return false;
	}

	if (!audit_opquast_prepare_tmp_dir()) {
		audit_opquast_set_pdf_error('Dossier temporaire inaccessible : ' . OPQUAST_TMP_DIR);
		spip_log('[audit_opquast] Dossier temporaire inaccessible : ' . OPQUAST_TMP_DIR, 'audit_opquast');
		return false;
	}

	$python = audit_opquast_find_python_command();

	if (!$python) {
		audit_opquast_set_pdf_error('Aucun interpreteur Python 3 compatible n a ete trouve.');
		spip_log('[audit_opquast] Aucun interpreteur Python 3 compatible n a ete trouve.', 'audit_opquast');
		return false;
	}

	if (!is_file(OPQUAST_PDF_SCRIPT)) {
		audit_opquast_set_pdf_error('Script PDF introuvable : ' . OPQUAST_PDF_SCRIPT);
		spip_log('[audit_opquast] Script PDF introuvable : ' . OPQUAST_PDF_SCRIPT, 'audit_opquast');
		return false;
	}

	audit_opquast_cleanup_tmp();

	$uid = uniqid('opquast_', true);
	$csv_tmp = OPQUAST_TMP_DIR . $uid . '.csv';
	$pdf_tmp = OPQUAST_TMP_DIR . $uid . '.pdf';

	if (file_put_contents($csv_tmp, $csv_content) === false) {
		audit_opquast_set_pdf_error('Echec ecriture CSV temporaire : ' . $csv_tmp);
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
		audit_opquast_set_pdf_error(
			'Erreur generation PDF'
			. ' | Code=' . $exit_code
			. ($log_output !== '' ? ' | Sortie=' . $log_output : '')
		);
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

	if (!$sent) {
		audit_opquast_set_pdf_error('Le PDF a ete genere mais n a pas pu etre envoye au navigateur.');
	}

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
		audit_opquast_set_pdf_error('CSV vide pour l audit #' . $id_audit . '.');
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

	if (!is_file(OPQUAST_PDF_SCRIPT)) {
		$messages[] = 'Script Python introuvable : ' . OPQUAST_PDF_SCRIPT;
		$ok = false;
	}

	if (!is_file(OPQUAST_PDF_LANG_FILE)) {
		$messages[] = 'Fichier de langue introuvable : ' . OPQUAST_PDF_LANG_FILE;
		$ok = false;
	}

	$tmp_ok = audit_opquast_prepare_tmp_dir();

	if (!$tmp_ok) {
		$messages[] = 'Dossier temporaire inaccessible : ' . OPQUAST_TMP_DIR;
		$ok = false;
	}

	$embedded_libs = is_dir(OPQUAST_PYTHON_LIB_DIR);

	if ($embedded_libs) {
		$messages[] = 'Bibliotheques Python embarquees detectees : ' . OPQUAST_PYTHON_LIB_DIR;
	} else {
		$messages[] = 'Aucune bibliotheque Python embarquee detectee, utilisation des bibliotheques systeme si disponibles.';
	}

	if ($python) {
		$reportlab_available = audit_opquast_python_module_embedded('reportlab')
			|| audit_opquast_python_import_ok($python, 'reportlab');

		if (!$reportlab_available) {
			$messages[] = 'Module Python manquant : reportlab';
			$ok = false;
		}
	}

	return [
		'ok' => $ok,
		'messages' => $messages,
	];
}

function audit_opquast_html_diagnostic_requirements($check, $support = 'PDF') {
	$messages = is_array($check['messages'] ?? null) ? $check['messages'] : [];

	if (!$messages) {
		return '';
	}

	$html = '<p>' . _T('audit_opquast:info_restitution_generation_impossible') . '</p>';
	$html .= '<p><strong>'
		. htmlspecialchars(_T('audit_opquast:info_restitution_prerequis_titre') . ' ' . trim((string) $support), ENT_QUOTES, 'UTF-8')
		. '</strong></p>';
	$html .= '<ul>';

	foreach ($messages as $message) {
		$html .= '<li>' . htmlspecialchars((string) $message, ENT_QUOTES, 'UTF-8') . '</li>';
	}

	$html .= '</ul>';

	return $html;
}

function audit_opquast_html_generation_error($support = 'PDF', $message = null) {
	if ($message === null) {
		$message = audit_opquast_get_pdf_error();
	}

	if ($message === '') {
		return '';
	}

	$html = '<p>' . _T('audit_opquast:info_restitution_generation_impossible') . '</p>';
	$html .= '<p><strong>Detail technique ' . htmlspecialchars((string) $support, ENT_QUOTES, 'UTF-8') . '</strong></p>';
	$html .= '<ul><li>' . htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . '</li></ul>';

	return $html;
}
