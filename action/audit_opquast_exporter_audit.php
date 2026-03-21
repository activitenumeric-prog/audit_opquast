<?php

if (!defined('_ECRIRE_INC_VERSION')) {
	return;
}

function action_audit_opquast_exporter_audit_dist($arg = null) {
	include_spip('inc/actions');
	include_spip('inc/autoriser');
	include_spip('audit_opquast_fonctions');

	if ($arg === null) {
		$securiser_action = charger_fonction('securiser_action', 'inc');
		$arg = $securiser_action();
	}

	$id_audit = intval($arg);
	$format = trim((string) _request('format')) ?: 'csv';

	if (
		!$id_audit
		|| $format !== 'csv'
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

	$rows = audit_opquast_donnees_export_audit($id_audit);
	$filename = audit_opquast_nom_fichier_export_audit($audit, 'csv');

	header('Content-Type: text/csv; charset=UTF-8');
	header('Content-Disposition: attachment; filename="' . str_replace('"', '', $filename) . '"');
	header('Pragma: public');
	header('Cache-Control: max-age=0');

	$output = fopen('php://output', 'w');

	if ($output === false) {
		exit;
	}

	fwrite($output, "\xEF\xBB\xBF");
	fputcsv($output, [
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
	], ';');

	foreach ($rows as $row) {
		fputcsv($output, [
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

	fclose($output);
	exit;
}
