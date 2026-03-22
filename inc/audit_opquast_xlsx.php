<?php

if (!defined('_ECRIRE_INC_VERSION')) {
	return;
}

function audit_opquast_export_xlsx($id_audit) {
	$id_audit = intval($id_audit);

	if (
		!$id_audit
		|| (!class_exists('ZipArchive') && !class_exists('PharData'))
	) {
		return [];
	}

	$data = audit_opquast_xlsx_preparer_donnees($id_audit);

	if (!$data) {
		return [];
	}

	$tmp = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR)
		. DIRECTORY_SEPARATOR
		. uniqid('audit-opquast-xlsx-', true)
		. '.zip';

	$sheets = audit_opquast_xlsx_construire_feuilles($data);

	$files = [
		'[Content_Types].xml' => audit_opquast_xlsx_content_types(count($sheets)),
		'_rels/.rels' => audit_opquast_xlsx_root_rels(),
		'docProps/core.xml' => audit_opquast_xlsx_core_xml(),
		'docProps/app.xml' => audit_opquast_xlsx_app_xml($sheets),
		'xl/workbook.xml' => audit_opquast_xlsx_workbook_xml($sheets),
		'xl/_rels/workbook.xml.rels' => audit_opquast_xlsx_workbook_rels(count($sheets)),
		'xl/styles.xml' => audit_opquast_xlsx_styles_xml(),
	];

	foreach ($sheets as $index => $sheet) {
		$files['xl/worksheets/sheet' . ($index + 1) . '.xml'] = audit_opquast_xlsx_sheet_xml($sheet);
	}

	if (!audit_opquast_xlsx_creer_archive($tmp, $files)) {
		@unlink($tmp);
		return [];
	}

	return [
		'path' => $tmp,
		'filename' => audit_opquast_nom_fichier_export_audit($data['audit'], 'xlsx'),
		'mime' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
	];
}

function audit_opquast_xlsx_construire_feuilles($data) {
	if (($data['type'] ?? 'url') === 'site') {
		$sheets = [
			audit_opquast_xlsx_feuille_site_tableau_bord($data),
			audit_opquast_xlsx_feuille_site_comparatif($data),
			audit_opquast_xlsx_feuille_site_detail($data),
		];

		foreach ((array) ($data['urls'] ?? []) as $url_data) {
			$sheets[] = audit_opquast_xlsx_feuille_site_url($data, $url_data);
		}

		$sheets[] = audit_opquast_xlsx_feuille_non_conformites($data);

		return $sheets;
	}

	return [
		audit_opquast_xlsx_feuille_tableau_bord($data),
		audit_opquast_xlsx_feuille_detail($data),
		audit_opquast_xlsx_feuille_familles($data),
		audit_opquast_xlsx_feuille_non_conformites($data),
	];
}

function audit_opquast_xlsx_creer_archive($path, $files) {
	if (class_exists('ZipArchive')) {
		$zip = new ZipArchive();
		$open = $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);

		if ($open !== true) {
			return false;
		}

		foreach ((array) $files as $entry => $content) {
			$zip->addFromString($entry, $content);
		}

		$zip->close();

		return is_file($path);
	}

	if (!class_exists('PharData')) {
		return false;
	}

	try {
		@unlink($path);
		$archive = new PharData($path, 0, null, Phar::ZIP);

		foreach ((array) $files as $entry => $content) {
			$archive[$entry] = $content;
		}

		unset($archive);

		return is_file($path);
	} catch (Throwable $e) {
		return false;
	}
}

function audit_opquast_xlsx_preparer_donnees($id_audit) {
	$audit = audit_opquast_lire_audit($id_audit);

	if (!$audit) {
		return [];
	}

	$type_cible = (string) ($audit['type_cible'] ?? '');

	if ($type_cible === 'url') {
		return audit_opquast_xlsx_preparer_donnees_url($audit);
	}

	if ($type_cible === 'site') {
		return audit_opquast_xlsx_preparer_donnees_site($audit);
	}

	return [];
}

function audit_opquast_xlsx_preparer_donnees_url($audit) {
	if (!$audit || ($audit['type_cible'] ?? '') !== 'url') {
		return [];
	}

	return array_merge(
		[
			'type' => 'url',
			'audit' => $audit,
		],
		audit_opquast_xlsx_preparer_detail_audit($audit)
	);
}

function audit_opquast_xlsx_preparer_donnees_site($audit) {
	if (!$audit || ($audit['type_cible'] ?? '') !== 'site') {
		return [];
	}

	$id_audit = intval($audit['id_audit'] ?? 0);

	if (!$id_audit) {
		return [];
	}

	$resume = audit_opquast_resume_audit($id_audit);
	$enfants = audit_opquast_lister_audits_site_enfants($id_audit);
	$urls = [];
	$detail = [];
	$non_conformes = [];
	$familles_par_url = [];
	$familles_noms = [];

	foreach ($enfants as $index => $enfant) {
		$id_enfant = intval($enfant['id_audit'] ?? 0);
		$prepared = $id_enfant ? audit_opquast_xlsx_preparer_detail_audit($enfant) : [
			'resume' => [],
			'familles_resume' => [],
			'detail' => [],
			'par_famille' => [],
			'non_conformes' => [],
		];

		$familles_index = [];
		foreach ((array) ($prepared['familles_resume'] ?? []) as $famille_resume) {
			$famille_nom = (string) ($famille_resume['famille'] ?? '');
			$familles_index[$famille_nom] = $famille_resume;
			$familles_noms[$famille_nom] = $famille_nom;
		}

		$url_data = [
			'index' => $index + 1,
			'sheet_name' => 'URL ' . ($index + 1),
			'label' => audit_opquast_xlsx_site_url_label($enfant['url_cible'] ?? ''),
			'audit' => $enfant,
			'resume' => $prepared['resume'],
			'familles_resume' => $prepared['familles_resume'],
			'familles_index' => $familles_index,
			'detail' => $prepared['detail'],
			'par_famille' => $prepared['par_famille'],
			'non_conformes' => $prepared['non_conformes'],
		];

		$urls[] = $url_data;
		$familles_par_url[] = $url_data;
		$detail = array_merge($detail, (array) ($prepared['detail'] ?? []));
		$non_conformes = array_merge($non_conformes, (array) ($prepared['non_conformes'] ?? []));
	}

	natcasesort($familles_noms);
	$comparatif_familles = [];

	foreach ($familles_noms as $famille_nom) {
		$row = [
			'famille' => $famille_nom,
			'par_url' => [],
		];

		foreach ($familles_par_url as $url_data) {
			$row['par_url'][] = $url_data['familles_index'][$famille_nom] ?? [
				'famille' => $famille_nom,
				'conforme' => 0,
				'non_conforme' => 0,
				'a_verifier' => 0,
				'non_applicable' => 0,
				'total_regles' => 0,
				'score_conformite' => null,
			];
		}

		$comparatif_familles[] = $row;
	}

	return [
		'type' => 'site',
		'audit' => $audit,
		'resume' => $resume,
		'urls' => $urls,
		'detail' => $detail,
		'non_conformes' => $non_conformes,
		'comparatif_familles' => $comparatif_familles,
	];
}

function audit_opquast_xlsx_preparer_detail_audit($audit) {
	$id_audit = intval($audit['id_audit'] ?? 0);

	if (!$id_audit || !$audit) {
		return [];
	}

	$resume = audit_opquast_resume_audit($id_audit);
	$regles = audit_opquast_lister_regles_audit($id_audit, ['tri' => 'numero']);
	$familles_resume = audit_opquast_resume_familles($id_audit);
	$familles_resume = array_values($familles_resume);

	usort($familles_resume, function ($a, $b) {
		return strcasecmp((string) ($a['famille'] ?? ''), (string) ($b['famille'] ?? ''));
	});

	$detail = [];
	$par_famille = [];
	$non_conformes = [];

	foreach ($regles as $regle) {
		$row = [
			'numero' => (string) ($regle['numero'] ?? ''),
			'famille' => (string) ($regle['famille'] ?? ''),
			'titre' => (string) ($regle['titre'] ?? ''),
			'statut' => (string) ($regle['statut_verification'] ?? 'a_verifier'),
			'statut_label' => audit_opquast_statuts_verification($regle['statut_verification'] ?? 'a_verifier'),
			'commentaire' => trim((string) ($regle['commentaire'] ?? '')),
			'preuve' => trim((string) ($regle['preuve'] ?? '')),
			'url' => (string) ($audit['url_cible'] ?? ''),
			'lien' => trim((string) ($regle['url_source'] ?? '')),
		];

		$detail[] = $row;
		$par_famille[$row['famille']][] = $row;

		if ($row['statut'] === 'non_conforme') {
			$non_conformes[] = $row;
		}
	}

	ksort($par_famille, SORT_NATURAL | SORT_FLAG_CASE);

	return [
		'resume' => $resume,
		'familles_resume' => $familles_resume,
		'detail' => $detail,
		'par_famille' => $par_famille,
		'non_conformes' => $non_conformes,
	];
}

function audit_opquast_xlsx_feuille_tableau_bord($data) {
	$audit = $data['audit'];
	$resume = $data['resume'];
	$familles = $data['familles_resume'];
	$rows = [];
	$merges = [];
	$total_regles = intval($resume['total_regles'] ?? 0);
	$conforme = intval($resume['conforme'] ?? 0);
	$non_conforme = intval($resume['non_conforme'] ?? 0);
	$a_verifier = intval($resume['a_verifier'] ?? 0);
	$non_applicable = intval($resume['non_applicable'] ?? 0);
	$traitees = intval($resume['traitees'] ?? 0);

	$row = audit_opquast_xlsx_add_row($rows, [
		1 => audit_opquast_xlsx_text('AUDIT OPQUAST', 1),
	], 28);
	$merges[] = 'A' . $row . ':G' . $row;

	$row = audit_opquast_xlsx_add_row($rows, [
		1 => audit_opquast_xlsx_text(
			trim((string) ($audit['titre'] ?? '')) . ' - ' . trim((string) ($audit['url_cible'] ?? '')),
			2
		),
	], 22);
	$merges[] = 'A' . $row . ':G' . $row;

	audit_opquast_xlsx_add_row($rows, [], 8);

	for ($i = 4; $i <= 7; $i++) {
		audit_opquast_xlsx_add_row($rows, []);
	}
	$merges[] = 'A4:B7';
	$rows[3]['cells'][1] = audit_opquast_xlsx_text(audit_opquast_xlsx_score_label($resume['score_conformite'] ?? null), 15);

	$rows[3]['cells'][3] = audit_opquast_xlsx_text('Score de conformite', 16);
	$merges[] = 'C4:G4';
	$rows[4]['cells'][3] = audit_opquast_xlsx_text(
		'Base sur ' . ($conforme + $non_conforme) . ' regles evaluees (Conforme + Non conforme)',
		16
	);
	$merges[] = 'C5:G5';
	$rows[5]['cells'][3] = audit_opquast_xlsx_text(
		'Total regles : ' . $total_regles . ' - Auditees : ' . $traitees . ' - A verifier : ' . $a_verifier . ' - N/A : ' . $non_applicable,
		16
	);
	$merges[] = 'C6:G6';
	$rows[6]['cells'][3] = audit_opquast_xlsx_text(
		'Statut audit : ' . audit_opquast_statuts_audit($audit['statut'] ?? 'brouillon') . ' - Progression : ' . intval($resume['progression'] ?? 0) . '%',
		16
	);
	$merges[] = 'C7:G7';

	audit_opquast_xlsx_add_row($rows, [], 10);

	audit_opquast_xlsx_add_row($rows, [
		1 => audit_opquast_xlsx_text('Conforme', 17),
		4 => audit_opquast_xlsx_text('Non conforme', 20),
	], 18);
	$merges[] = 'A9:C9';
	$merges[] = 'D9:F9';
	audit_opquast_xlsx_add_row($rows, [
		1 => audit_opquast_xlsx_text((string) $conforme, 18),
		4 => audit_opquast_xlsx_text((string) $non_conforme, 21),
	], 30);
	$merges[] = 'A10:C10';
	$merges[] = 'D10:F10';
	audit_opquast_xlsx_add_row($rows, [
		1 => audit_opquast_xlsx_text(audit_opquast_xlsx_regles_percent_label($conforme, $total_regles), 19),
		4 => audit_opquast_xlsx_text(audit_opquast_xlsx_regles_percent_label($non_conforme, $total_regles), 22),
	], 18);
	$merges[] = 'A11:C11';
	$merges[] = 'D11:F11';

	audit_opquast_xlsx_add_row($rows, [], 8);

	audit_opquast_xlsx_add_row($rows, [
		1 => audit_opquast_xlsx_text('A verifier', 23),
		4 => audit_opquast_xlsx_text('Non applicable', 26),
	], 18);
	$merges[] = 'A13:C13';
	$merges[] = 'D13:F13';
	audit_opquast_xlsx_add_row($rows, [
		1 => audit_opquast_xlsx_text((string) $a_verifier, 24),
		4 => audit_opquast_xlsx_text((string) $non_applicable, 27),
	], 30);
	$merges[] = 'A14:C14';
	$merges[] = 'D14:F14';
	audit_opquast_xlsx_add_row($rows, [
		1 => audit_opquast_xlsx_text(audit_opquast_xlsx_regles_percent_label($a_verifier, $total_regles), 25),
		4 => audit_opquast_xlsx_text(audit_opquast_xlsx_regles_percent_label($non_applicable, $total_regles), 28),
	], 18);
	$merges[] = 'A15:C15';
	$merges[] = 'D15:F15';

	audit_opquast_xlsx_add_row($rows, [], 10);

	$row = audit_opquast_xlsx_add_row($rows, [
		1 => audit_opquast_xlsx_text('Resultats par famille', 3),
	], 20);
	$merges[] = 'A' . $row . ':G' . $row;

	audit_opquast_xlsx_add_row($rows, [
		1 => audit_opquast_xlsx_text('Famille', 6),
		2 => audit_opquast_xlsx_text('Conforme', 6),
		3 => audit_opquast_xlsx_text('Non conforme', 6),
		4 => audit_opquast_xlsx_text('A verifier', 6),
		5 => audit_opquast_xlsx_text('Non applicable', 6),
		6 => audit_opquast_xlsx_text('Total', 6),
		7 => audit_opquast_xlsx_text('Score conformite', 6),
	]);

	foreach ($familles as $famille) {
		audit_opquast_xlsx_add_row($rows, [
			1 => audit_opquast_xlsx_text((string) ($famille['famille'] ?? ''), 7),
			2 => audit_opquast_xlsx_text((string) intval($famille['conforme'] ?? 0), 8),
			3 => audit_opquast_xlsx_text((string) intval($famille['non_conforme'] ?? 0), 9),
			4 => audit_opquast_xlsx_text((string) intval($famille['a_verifier'] ?? 0), 10),
			5 => audit_opquast_xlsx_text((string) intval($famille['non_applicable'] ?? 0), 11),
			6 => audit_opquast_xlsx_text((string) intval($famille['total_regles'] ?? 0), 7),
			7 => audit_opquast_xlsx_text(audit_opquast_xlsx_score_label($famille['score_conformite'] ?? null), 7),
		]);
	}

	audit_opquast_xlsx_add_row($rows, [
		1 => audit_opquast_xlsx_text('TOTAL', 29),
		2 => audit_opquast_xlsx_text((string) $conforme, 29),
		3 => audit_opquast_xlsx_text((string) $non_conforme, 29),
		4 => audit_opquast_xlsx_text((string) $a_verifier, 29),
		5 => audit_opquast_xlsx_text((string) $non_applicable, 29),
		6 => audit_opquast_xlsx_text((string) $total_regles, 29),
		7 => audit_opquast_xlsx_text(audit_opquast_xlsx_score_label($resume['score_conformite'] ?? null), 29),
	], 20);

	return [
		'name' => 'Tableau de bord',
		'rows' => $rows,
		'merges' => $merges,
		'cols' => [
			1 => 22,
			2 => 16,
			3 => 14,
			4 => 14,
			5 => 14,
			6 => 14,
			7 => 20,
		],
		'tab_color' => 'FFF39C12',
	];
}

function audit_opquast_xlsx_feuille_detail($data) {
	$audit = $data['audit'];
	$rows = [];
	$merges = [];

	$row = audit_opquast_xlsx_add_row($rows, [
		1 => audit_opquast_xlsx_text('Detail des regles', 1),
	], 28);
	$merges[] = 'A' . $row . ':G' . $row;

	$row = audit_opquast_xlsx_add_row($rows, [
		1 => audit_opquast_xlsx_text(
			trim((string) ($audit['titre'] ?? '')) . ' - ' . trim((string) ($audit['url_cible'] ?? '')),
			2
		),
	], 22);
	$merges[] = 'A' . $row . ':G' . $row;

	audit_opquast_xlsx_add_row($rows, [], 8);

	audit_opquast_xlsx_add_row($rows, [
		1 => audit_opquast_xlsx_text('No', 6),
		2 => audit_opquast_xlsx_text('Famille', 6),
		3 => audit_opquast_xlsx_text('Intitule de la regle', 6),
		4 => audit_opquast_xlsx_text('Statut', 6),
		5 => audit_opquast_xlsx_text('Commentaire', 6),
		6 => audit_opquast_xlsx_text('Preuve / Note', 6),
		7 => audit_opquast_xlsx_text('Lien Opquast', 6),
	]);

	foreach ($data['detail'] as $detail) {
		audit_opquast_xlsx_add_row($rows, [
			1 => audit_opquast_xlsx_text((string) ($detail['numero'] ?? ''), 7),
			2 => audit_opquast_xlsx_text((string) ($detail['famille'] ?? ''), 7),
			3 => audit_opquast_xlsx_text((string) ($detail['titre'] ?? ''), 7),
			4 => audit_opquast_xlsx_text(
				(string) ($detail['statut_label'] ?? ''),
				audit_opquast_xlsx_style_statut($detail['statut'] ?? '')
			),
			5 => audit_opquast_xlsx_text((string) ($detail['commentaire'] ?? ''), 7),
			6 => audit_opquast_xlsx_text((string) ($detail['preuve'] ?? ''), 7),
			7 => audit_opquast_xlsx_text((string) ($detail['lien'] ?? ''), 12),
		]);
	}

	return [
		'name' => 'Detail des regles',
		'rows' => $rows,
		'merges' => $merges,
		'cols' => [
			1 => 8,
			2 => 22,
			3 => 70,
			4 => 18,
			5 => 36,
			6 => 36,
			7 => 38,
		],
		'tab_color' => 'FF3498DB',
	];
}

function audit_opquast_xlsx_feuille_familles($data) {
	$audit = $data['audit'];
	$rows = [];
	$merges = [];

	$row = audit_opquast_xlsx_add_row($rows, [
		1 => audit_opquast_xlsx_text('Par famille', 1),
	], 28);
	$merges[] = 'A' . $row . ':E' . $row;

	$row = audit_opquast_xlsx_add_row($rows, [
		1 => audit_opquast_xlsx_text(
			trim((string) ($audit['titre'] ?? '')) . ' - ' . trim((string) ($audit['url_cible'] ?? '')),
			2
		),
	], 22);
	$merges[] = 'A' . $row . ':E' . $row;

	audit_opquast_xlsx_add_row($rows, [], 8);

	foreach ($data['par_famille'] as $famille => $regles) {
		$score = audit_opquast_xlsx_score_famille($regles);
		$traitees = 0;

		foreach ($regles as $regle) {
			if (($regle['statut'] ?? '') !== 'a_verifier') {
				$traitees++;
			}
		}

		$row = audit_opquast_xlsx_add_row($rows, [
			1 => audit_opquast_xlsx_text(
				$famille . ' - ' . $traitees . ' / ' . count($regles) . ' regles traitees - score ' . audit_opquast_xlsx_score_label($score),
				13
			),
		]);
		$merges[] = 'A' . $row . ':E' . $row;

		audit_opquast_xlsx_add_row($rows, [
			1 => audit_opquast_xlsx_text('No', 6),
			2 => audit_opquast_xlsx_text('Intitule', 6),
			3 => audit_opquast_xlsx_text('Statut', 6),
			4 => audit_opquast_xlsx_text('Commentaire', 6),
			5 => audit_opquast_xlsx_text('Preuve / Note', 6),
		]);

		foreach ($regles as $regle) {
			audit_opquast_xlsx_add_row($rows, [
				1 => audit_opquast_xlsx_text((string) ($regle['numero'] ?? ''), 7),
				2 => audit_opquast_xlsx_text((string) ($regle['titre'] ?? ''), 7),
				3 => audit_opquast_xlsx_text(
					(string) ($regle['statut_label'] ?? ''),
					audit_opquast_xlsx_style_statut($regle['statut'] ?? '')
				),
				4 => audit_opquast_xlsx_text((string) ($regle['commentaire'] ?? ''), 7),
				5 => audit_opquast_xlsx_text((string) ($regle['preuve'] ?? ''), 7),
			]);
		}

		audit_opquast_xlsx_add_row($rows, []);
	}

	return [
		'name' => 'Par famille',
		'rows' => $rows,
		'merges' => $merges,
		'cols' => [
			1 => 8,
			2 => 78,
			3 => 18,
			4 => 34,
			5 => 34,
		],
		'tab_color' => 'FFF39C12',
	];
}

function audit_opquast_xlsx_feuille_non_conformites($data) {
	$audit = $data['audit'];
	$rows = [];
	$merges = [];

	$row = audit_opquast_xlsx_add_row($rows, [
		1 => audit_opquast_xlsx_text('Non conformites', 1),
	], 28);
	$merges[] = 'A' . $row . ':G' . $row;

	$row = audit_opquast_xlsx_add_row($rows, [
		1 => audit_opquast_xlsx_text(
			trim((string) ($audit['titre'] ?? '')) . ' - ' . trim((string) ($audit['url_cible'] ?? '')),
			2
		),
	], 22);
	$merges[] = 'A' . $row . ':G' . $row;

	audit_opquast_xlsx_add_row($rows, [], 8);

	if (!$data['non_conformes']) {
		$row = audit_opquast_xlsx_add_row($rows, [
			1 => audit_opquast_xlsx_text('Aucune non conformite pour cet audit.', 14),
		]);
		$merges[] = 'A' . $row . ':G' . $row;
	} else {
		audit_opquast_xlsx_add_row($rows, [
			1 => audit_opquast_xlsx_text('No', 6),
			2 => audit_opquast_xlsx_text('URL', 6),
			3 => audit_opquast_xlsx_text('Famille', 6),
			4 => audit_opquast_xlsx_text('Intitule', 6),
			5 => audit_opquast_xlsx_text('Commentaire', 6),
			6 => audit_opquast_xlsx_text('Preuve / Note', 6),
			7 => audit_opquast_xlsx_text('Lien', 6),
		]);

		foreach ($data['non_conformes'] as $detail) {
			audit_opquast_xlsx_add_row($rows, [
				1 => audit_opquast_xlsx_text((string) ($detail['numero'] ?? ''), 7),
				2 => audit_opquast_xlsx_text((string) ($detail['url'] ?? ''), 12),
				3 => audit_opquast_xlsx_text((string) ($detail['famille'] ?? ''), 7),
				4 => audit_opquast_xlsx_text((string) ($detail['titre'] ?? ''), 7),
				5 => audit_opquast_xlsx_text((string) ($detail['commentaire'] ?? ''), 7),
				6 => audit_opquast_xlsx_text((string) ($detail['preuve'] ?? ''), 7),
				7 => audit_opquast_xlsx_text((string) ($detail['lien'] ?? ''), 12),
			]);
		}
	}

	return [
		'name' => 'Non conformites',
		'rows' => $rows,
		'merges' => $merges,
		'cols' => [
			1 => 8,
			2 => 28,
			3 => 22,
			4 => 68,
			5 => 30,
			6 => 30,
			7 => 36,
		],
		'tab_color' => 'FFE74C3C',
	];
}

function audit_opquast_xlsx_feuille_site_tableau_bord($data) {
	$audit = $data['audit'];
	$resume = $data['resume'];
	$urls = $data['urls'] ?? [];
	$rows = [];
	$merges = [];
	$total_regles = intval($resume['total_regles'] ?? 0);
	$conforme = intval($resume['conforme'] ?? 0);
	$non_conforme = intval($resume['non_conforme'] ?? 0);
	$a_verifier = intval($resume['a_verifier'] ?? 0);
	$non_applicable = intval($resume['non_applicable'] ?? 0);
	$traitees = intval($resume['traitees'] ?? 0);

	$row = audit_opquast_xlsx_add_row($rows, [
		1 => audit_opquast_xlsx_text('AUDIT OPQUAST', 1),
	], 28);
	$merges[] = 'A' . $row . ':H' . $row;

	$row = audit_opquast_xlsx_add_row($rows, [
		1 => audit_opquast_xlsx_text(
			trim((string) ($audit['titre'] ?? '')) . ' - Audit de site - ' . count($urls) . ' URL(s)',
			2
		),
	], 22);
	$merges[] = 'A' . $row . ':H' . $row;

	audit_opquast_xlsx_add_row($rows, [], 8);

	for ($i = 4; $i <= 7; $i++) {
		audit_opquast_xlsx_add_row($rows, []);
	}
	$merges[] = 'A4:C7';
	$rows[3]['cells'][1] = audit_opquast_xlsx_text(audit_opquast_xlsx_score_label($resume['score_conformite'] ?? null), 15);

	$rows[3]['cells'][4] = audit_opquast_xlsx_text('Score global', 16);
	$merges[] = 'D4:H4';
	$rows[4]['cells'][4] = audit_opquast_xlsx_text(
		'Methode Opquast : Conformes / (Conformes + Non conformes) x 100',
		16
	);
	$merges[] = 'D5:H5';
	$rows[5]['cells'][4] = audit_opquast_xlsx_text(
		'Total regles : ' . $total_regles . ' - Evaluees : ' . ($conforme + $non_conforme) . ' - Conformes : ' . $conforme . ' - Non conformes : ' . $non_conforme,
		16
	);
	$merges[] = 'D6:H6';
	$rows[6]['cells'][4] = audit_opquast_xlsx_text(
		'A verifier : ' . $a_verifier . ' - N/A : ' . $non_applicable . ' - Progression : ' . intval($resume['progression'] ?? 0) . '%',
		16
	);
	$merges[] = 'D7:H7';

	audit_opquast_xlsx_add_row($rows, [], 10);

	audit_opquast_xlsx_add_row($rows, [
		1 => audit_opquast_xlsx_text('Conforme', 17),
		5 => audit_opquast_xlsx_text('Non conforme', 20),
	], 18);
	$merges[] = 'A9:D9';
	$merges[] = 'E9:H9';
	audit_opquast_xlsx_add_row($rows, [
		1 => audit_opquast_xlsx_text((string) $conforme, 18),
		5 => audit_opquast_xlsx_text((string) $non_conforme, 21),
	], 30);
	$merges[] = 'A10:D10';
	$merges[] = 'E10:H10';
	audit_opquast_xlsx_add_row($rows, [
		1 => audit_opquast_xlsx_text(audit_opquast_xlsx_regles_percent_label($conforme, $total_regles), 19),
		5 => audit_opquast_xlsx_text(audit_opquast_xlsx_regles_percent_label($non_conforme, $total_regles), 22),
	], 18);
	$merges[] = 'A11:D11';
	$merges[] = 'E11:H11';

	audit_opquast_xlsx_add_row($rows, [], 8);

	audit_opquast_xlsx_add_row($rows, [
		1 => audit_opquast_xlsx_text('A verifier', 23),
		5 => audit_opquast_xlsx_text('Non applicable', 26),
	], 18);
	$merges[] = 'A13:D13';
	$merges[] = 'E13:H13';
	audit_opquast_xlsx_add_row($rows, [
		1 => audit_opquast_xlsx_text((string) $a_verifier, 24),
		5 => audit_opquast_xlsx_text((string) $non_applicable, 27),
	], 30);
	$merges[] = 'A14:D14';
	$merges[] = 'E14:H14';
	audit_opquast_xlsx_add_row($rows, [
		1 => audit_opquast_xlsx_text(audit_opquast_xlsx_regles_percent_label($a_verifier, $total_regles), 25),
		5 => audit_opquast_xlsx_text(audit_opquast_xlsx_regles_percent_label($non_applicable, $total_regles), 28),
	], 18);
	$merges[] = 'A15:D15';
	$merges[] = 'E15:H15';

	audit_opquast_xlsx_add_row($rows, [], 10);

	$row = audit_opquast_xlsx_add_row($rows, [
		1 => audit_opquast_xlsx_text('Resultats par URL', 3),
	], 20);
	$merges[] = 'A' . $row . ':H' . $row;

	audit_opquast_xlsx_add_row($rows, [
		1 => audit_opquast_xlsx_text('URL', 6),
		2 => audit_opquast_xlsx_text('Traitees', 6),
		3 => audit_opquast_xlsx_text('Conforme', 6),
		4 => audit_opquast_xlsx_text('Non conforme', 6),
		5 => audit_opquast_xlsx_text('A verifier', 6),
		6 => audit_opquast_xlsx_text('Non applicable', 6),
		7 => audit_opquast_xlsx_text('Score', 6),
		8 => audit_opquast_xlsx_text('Feuille', 6),
	]);

	foreach ($urls as $url_data) {
		$url_resume = $url_data['resume'] ?? [];
		audit_opquast_xlsx_add_row($rows, [
			1 => audit_opquast_xlsx_text((string) ($url_data['label'] ?? ''), 12),
			2 => audit_opquast_xlsx_text((string) intval($url_resume['traitees'] ?? 0), 7),
			3 => audit_opquast_xlsx_text((string) intval($url_resume['conforme'] ?? 0), 8),
			4 => audit_opquast_xlsx_text((string) intval($url_resume['non_conforme'] ?? 0), 9),
			5 => audit_opquast_xlsx_text((string) intval($url_resume['a_verifier'] ?? 0), 10),
			6 => audit_opquast_xlsx_text((string) intval($url_resume['non_applicable'] ?? 0), 11),
			7 => audit_opquast_xlsx_text(audit_opquast_xlsx_score_label($url_resume['score_conformite'] ?? null), 7),
			8 => audit_opquast_xlsx_text((string) ($url_data['sheet_name'] ?? ''), 7),
		]);
	}

	return [
		'name' => 'Tableau de bord',
		'rows' => $rows,
		'merges' => $merges,
		'cols' => [
			1 => 30,
			2 => 12,
			3 => 12,
			4 => 14,
			5 => 14,
			6 => 16,
			7 => 14,
			8 => 12,
		],
		'tab_color' => 'FFF39C12',
	];
}

function audit_opquast_xlsx_feuille_site_comparatif($data) {
	$audit = $data['audit'];
	$urls = $data['urls'] ?? [];
	$familles = $data['comparatif_familles'] ?? [];
	$rows = [];
	$merges = [];
	$total_cols = max(2, 1 + (count($urls) * 5));

	$row = audit_opquast_xlsx_add_row($rows, [
		1 => audit_opquast_xlsx_text('Comparatif URLs', 1),
	], 28);
	$merges[] = 'A' . $row . ':' . audit_opquast_xlsx_column_name($total_cols) . $row;

	$row = audit_opquast_xlsx_add_row($rows, [
		1 => audit_opquast_xlsx_text(
			trim((string) ($audit['titre'] ?? '')) . ' - ' . count($urls) . ' URL(s) comparee(s)',
			2
		),
	], 22);
	$merges[] = 'A' . $row . ':' . audit_opquast_xlsx_column_name($total_cols) . $row;

	audit_opquast_xlsx_add_row($rows, [], 8);

	$group_cells = [];
	foreach ($urls as $index => $url_data) {
		$start = 2 + ($index * 5);
		$group_cells[$start] = audit_opquast_xlsx_text((string) ($url_data['label'] ?? ''), 13);
		$merges[] = audit_opquast_xlsx_column_name($start) . '4:' . audit_opquast_xlsx_column_name($start + 4) . '4';
	}
	$rows[] = ['cells' => $group_cells, 'height' => 20];

	$header_cells = [
		1 => audit_opquast_xlsx_text('Famille', 6),
	];
	foreach ($urls as $index => $url_data) {
		$start = 2 + ($index * 5);
		$header_cells[$start] = audit_opquast_xlsx_text('Conf.', 6);
		$header_cells[$start + 1] = audit_opquast_xlsx_text('Non conf.', 6);
		$header_cells[$start + 2] = audit_opquast_xlsx_text('A ver.', 6);
		$header_cells[$start + 3] = audit_opquast_xlsx_text('N/A', 6);
		$header_cells[$start + 4] = audit_opquast_xlsx_text('Score', 6);
	}
	audit_opquast_xlsx_add_row($rows, $header_cells);

	foreach ($familles as $famille_row) {
		$cells = [
			1 => audit_opquast_xlsx_text((string) ($famille_row['famille'] ?? ''), 7),
		];

		foreach ((array) ($famille_row['par_url'] ?? []) as $index => $resume_famille) {
			$start = 2 + ($index * 5);
			$cells[$start] = audit_opquast_xlsx_text((string) intval($resume_famille['conforme'] ?? 0), 8);
			$cells[$start + 1] = audit_opquast_xlsx_text((string) intval($resume_famille['non_conforme'] ?? 0), 9);
			$cells[$start + 2] = audit_opquast_xlsx_text((string) intval($resume_famille['a_verifier'] ?? 0), 10);
			$cells[$start + 3] = audit_opquast_xlsx_text((string) intval($resume_famille['non_applicable'] ?? 0), 11);
			$cells[$start + 4] = audit_opquast_xlsx_text(audit_opquast_xlsx_score_label($resume_famille['score_conformite'] ?? null), 7);
		}

		audit_opquast_xlsx_add_row($rows, $cells);
	}

	$cols = [1 => 24];
	foreach ($urls as $index => $url_data) {
		$start = 2 + ($index * 5);
		$cols[$start] = 10;
		$cols[$start + 1] = 12;
		$cols[$start + 2] = 10;
		$cols[$start + 3] = 10;
		$cols[$start + 4] = 12;
	}

	return [
		'name' => 'Comparatif URLs',
		'rows' => $rows,
		'merges' => $merges,
		'cols' => $cols,
		'tab_color' => 'FF3498DB',
	];
}

function audit_opquast_xlsx_feuille_site_detail($data) {
	$audit = $data['audit'];
	$rows = [];
	$merges = [];

	$row = audit_opquast_xlsx_add_row($rows, [
		1 => audit_opquast_xlsx_text('Detail des regles', 1),
	], 28);
	$merges[] = 'A' . $row . ':H' . $row;

	$row = audit_opquast_xlsx_add_row($rows, [
		1 => audit_opquast_xlsx_text(
			trim((string) ($audit['titre'] ?? '')) . ' - ' . count((array) ($data['urls'] ?? [])) . ' URL(s) - ' . count((array) ($data['detail'] ?? [])) . ' lignes',
			2
		),
	], 22);
	$merges[] = 'A' . $row . ':H' . $row;

	audit_opquast_xlsx_add_row($rows, [], 8);

	audit_opquast_xlsx_add_row($rows, [
		1 => audit_opquast_xlsx_text('No', 6),
		2 => audit_opquast_xlsx_text('URL', 6),
		3 => audit_opquast_xlsx_text('Famille', 6),
		4 => audit_opquast_xlsx_text('Intitule de la regle', 6),
		5 => audit_opquast_xlsx_text('Statut', 6),
		6 => audit_opquast_xlsx_text('Commentaire', 6),
		7 => audit_opquast_xlsx_text('Preuve / Note', 6),
		8 => audit_opquast_xlsx_text('Lien Opquast', 6),
	]);

	foreach ($data['detail'] as $detail) {
		audit_opquast_xlsx_add_row($rows, [
			1 => audit_opquast_xlsx_text((string) ($detail['numero'] ?? ''), 7),
			2 => audit_opquast_xlsx_text(audit_opquast_xlsx_site_url_label($detail['url'] ?? ''), 12),
			3 => audit_opquast_xlsx_text((string) ($detail['famille'] ?? ''), 7),
			4 => audit_opquast_xlsx_text((string) ($detail['titre'] ?? ''), 7),
			5 => audit_opquast_xlsx_text(
				(string) ($detail['statut_label'] ?? ''),
				audit_opquast_xlsx_style_statut($detail['statut'] ?? '')
			),
			6 => audit_opquast_xlsx_text((string) ($detail['commentaire'] ?? ''), 7),
			7 => audit_opquast_xlsx_text((string) ($detail['preuve'] ?? ''), 7),
			8 => audit_opquast_xlsx_text((string) ($detail['lien'] ?? ''), 12),
		]);
	}

	return [
		'name' => 'Detail des regles',
		'rows' => $rows,
		'merges' => $merges,
		'cols' => [
			1 => 8,
			2 => 28,
			3 => 22,
			4 => 66,
			5 => 18,
			6 => 30,
			7 => 30,
			8 => 38,
		],
		'tab_color' => 'FF5DADE2',
	];
}

function audit_opquast_xlsx_feuille_site_url($data, $url_data) {
	$audit = $url_data['audit'] ?? [];
	$resume = $url_data['resume'] ?? [];
	$rows = [];
	$merges = [];

	$row = audit_opquast_xlsx_add_row($rows, [
		1 => audit_opquast_xlsx_text((string) ($url_data['sheet_name'] ?? 'URL'), 1),
	], 28);
	$merges[] = 'A' . $row . ':G' . $row;

	$row = audit_opquast_xlsx_add_row($rows, [
		1 => audit_opquast_xlsx_text(
			(string) ($url_data['sheet_name'] ?? 'URL') . ' : ' . trim((string) ($audit['url_cible'] ?? '')),
			2
		),
	], 22);
	$merges[] = 'A' . $row . ':G' . $row;

	$row = audit_opquast_xlsx_add_row($rows, [
		1 => audit_opquast_xlsx_text(
			'Score : ' . audit_opquast_xlsx_score_label($resume['score_conformite'] ?? null)
			. ' - Conforme : ' . intval($resume['conforme'] ?? 0)
			. ' - Non conforme : ' . intval($resume['non_conforme'] ?? 0)
			. ' - A verifier : ' . intval($resume['a_verifier'] ?? 0)
			. ' - N/A : ' . intval($resume['non_applicable'] ?? 0)
			. ' - Total : ' . intval($resume['total_regles'] ?? 0),
			14
		),
	], 20);
	$merges[] = 'A' . $row . ':G' . $row;

	audit_opquast_xlsx_add_row($rows, [], 8);

	audit_opquast_xlsx_add_row($rows, [
		1 => audit_opquast_xlsx_text('No', 6),
		2 => audit_opquast_xlsx_text('Famille', 6),
		3 => audit_opquast_xlsx_text('Intitule', 6),
		4 => audit_opquast_xlsx_text('Statut', 6),
		5 => audit_opquast_xlsx_text('Commentaire', 6),
		6 => audit_opquast_xlsx_text('Preuve / Note', 6),
		7 => audit_opquast_xlsx_text('Lien', 6),
	]);

	foreach ((array) ($url_data['detail'] ?? []) as $detail) {
		audit_opquast_xlsx_add_row($rows, [
			1 => audit_opquast_xlsx_text((string) ($detail['numero'] ?? ''), 7),
			2 => audit_opquast_xlsx_text((string) ($detail['famille'] ?? ''), 7),
			3 => audit_opquast_xlsx_text((string) ($detail['titre'] ?? ''), 7),
			4 => audit_opquast_xlsx_text(
				(string) ($detail['statut_label'] ?? ''),
				audit_opquast_xlsx_style_statut($detail['statut'] ?? '')
			),
			5 => audit_opquast_xlsx_text((string) ($detail['commentaire'] ?? ''), 7),
			6 => audit_opquast_xlsx_text((string) ($detail['preuve'] ?? ''), 7),
			7 => audit_opquast_xlsx_text((string) ($detail['lien'] ?? ''), 12),
		]);
	}

	return [
		'name' => (string) ($url_data['sheet_name'] ?? 'URL'),
		'rows' => $rows,
		'merges' => $merges,
		'cols' => [
			1 => 8,
			2 => 22,
			3 => 68,
			4 => 18,
			5 => 30,
			6 => 30,
			7 => 38,
		],
		'tab_color' => 'FF85C1E9',
	];
}

function audit_opquast_xlsx_site_url_label($url) {
	$url = trim((string) $url);

	if ($url === '') {
		return '';
	}

	$url = preg_replace(',^https?://,i', '', $url);

	return $url ?: '';
}

function audit_opquast_xlsx_score_famille($regles) {
	$conforme = 0;
	$non_conforme = 0;

	foreach ((array) $regles as $regle) {
		$statut = (string) ($regle['statut'] ?? '');

		if ($statut === 'conforme') {
			$conforme++;
		} elseif ($statut === 'non_conforme') {
			$non_conforme++;
		}
	}

	$total = $conforme + $non_conforme;

	if (!$total) {
		return null;
	}

	return round(($conforme / $total) * 100, 2);
}

function audit_opquast_xlsx_score_label($score) {
	if ($score === null || $score === '') {
		return '--';
	}

	return audit_opquast_formater_score($score) . '%';
}

function audit_opquast_xlsx_regles_percent_label($count, $total) {
	$count = floatval($count);
	$total = floatval($total);

	if ($total <= 0) {
		return '--';
	}

	return number_format(($count / $total) * 100, 1, '.', '') . '% des regles';
}

function audit_opquast_xlsx_style_statut($statut) {
	switch ((string) $statut) {
		case 'conforme':
			return 8;
		case 'non_conforme':
			return 9;
		case 'a_verifier':
			return 10;
		case 'non_applicable':
			return 11;
		default:
			return 7;
	}
}

function audit_opquast_xlsx_text($value, $style = 0) {
	return [
		'type' => 'text',
		'value' => (string) $value,
		'style' => intval($style),
	];
}

function audit_opquast_xlsx_add_row(&$rows, $cells, $height = null) {
	$rows[] = [
		'cells' => $cells,
		'height' => $height === null ? null : floatval($height),
	];

	return count($rows);
}

function audit_opquast_xlsx_sheet_xml($sheet) {
	$xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
	$xml .= '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">';

	if (!empty($sheet['tab_color'])) {
		$xml .= '<sheetPr><tabColor rgb="' . audit_opquast_xlsx_escape($sheet['tab_color']) . '"/></sheetPr>';
	}

	if (!empty($sheet['cols'])) {
		$xml .= '<cols>';
		foreach ($sheet['cols'] as $index => $width) {
			$xml .= '<col min="' . intval($index) . '" max="' . intval($index) . '" width="' . audit_opquast_xlsx_number($width) . '" customWidth="1"/>';
		}
		$xml .= '</cols>';
	}

	$xml .= '<sheetData>';

	foreach ((array) ($sheet['rows'] ?? []) as $row_index => $row_data) {
		$cells = $row_data['cells'] ?? $row_data;
		$height = isset($row_data['height']) && $row_data['height'] ? floatval($row_data['height']) : null;
		$xml .= '<row r="' . ($row_index + 1) . '"';

		if ($height) {
			$xml .= ' ht="' . audit_opquast_xlsx_number($height) . '" customHeight="1"';
		}

		$xml .= '>';

		foreach ((array) $cells as $col_index => $cell) {
			$ref = audit_opquast_xlsx_column_name($col_index) . ($row_index + 1);
			$style = isset($cell['style']) ? ' s="' . intval($cell['style']) . '"' : '';
			$type = $cell['type'] ?? 'text';
			$value = $cell['value'] ?? '';

			if ($type === 'number') {
				$xml .= '<c r="' . $ref . '"' . $style . '><v>' . audit_opquast_xlsx_number($value) . '</v></c>';
				continue;
			}

			$xml .= '<c r="' . $ref . '"' . $style . ' t="inlineStr"><is><t xml:space="preserve">';
			$xml .= audit_opquast_xlsx_escape($value);
			$xml .= '</t></is></c>';
		}

		$xml .= '</row>';
	}

	$xml .= '</sheetData>';

	if (!empty($sheet['merges'])) {
		$xml .= '<mergeCells count="' . count($sheet['merges']) . '">';
		foreach ($sheet['merges'] as $merge) {
			$xml .= '<mergeCell ref="' . audit_opquast_xlsx_escape($merge) . '"/>';
		}
		$xml .= '</mergeCells>';
	}

	$xml .= '</worksheet>';

	return $xml;
}

function audit_opquast_xlsx_styles_xml() {
	return <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
	<fonts count="8">
		<font><sz val="11"/><name val="Calibri"/></font>
		<font><b/><sz val="20"/><color rgb="FFFFFFFF"/><name val="Calibri"/></font>
		<font><i/><sz val="11"/><color rgb="FFFFFFFF"/><name val="Calibri"/></font>
		<font><b/><sz val="12"/><name val="Calibri"/></font>
		<font><b/><sz val="18"/><name val="Calibri"/></font>
		<font><b/><sz val="26"/><color rgb="FFFFFFFF"/><name val="Calibri"/></font>
		<font><b/><sz val="12"/><color rgb="FFFFFFFF"/><name val="Calibri"/></font>
		<font><i/><sz val="10"/><color rgb="FF5E6C75"/><name val="Calibri"/></font>
	</fonts>
	<fills count="14">
		<fill><patternFill patternType="none"/></fill>
		<fill><patternFill patternType="gray125"/></fill>
		<fill><patternFill patternType="solid"><fgColor rgb="FFFDFBF7"/><bgColor indexed="64"/></patternFill></fill>
		<fill><patternFill patternType="solid"><fgColor rgb="FFD9F0E5"/><bgColor indexed="64"/></patternFill></fill>
		<fill><patternFill patternType="solid"><fgColor rgb="FFEAF7F0"/><bgColor indexed="64"/></patternFill></fill>
		<fill><patternFill patternType="solid"><fgColor rgb="FFFCEAEA"/><bgColor indexed="64"/></patternFill></fill>
		<fill><patternFill patternType="solid"><fgColor rgb="FFFFF3DD"/><bgColor indexed="64"/></patternFill></fill>
		<fill><patternFill patternType="solid"><fgColor rgb="FFF1F4F6"/><bgColor indexed="64"/></patternFill></fill>
		<fill><patternFill patternType="solid"><fgColor rgb="FF1E2A35"/><bgColor indexed="64"/></patternFill></fill>
		<fill><patternFill patternType="solid"><fgColor rgb="FF2BBCA5"/><bgColor indexed="64"/></patternFill></fill>
		<fill><patternFill patternType="solid"><fgColor rgb="FF2ECC71"/><bgColor indexed="64"/></patternFill></fill>
		<fill><patternFill patternType="solid"><fgColor rgb="FFF24E43"/><bgColor indexed="64"/></patternFill></fill>
		<fill><patternFill patternType="solid"><fgColor rgb="FFFFA000"/><bgColor indexed="64"/></patternFill></fill>
		<fill><patternFill patternType="solid"><fgColor rgb="FFA7B5BD"/><bgColor indexed="64"/></patternFill></fill>
	</fills>
	<borders count="2">
		<border><left/><right/><top/><bottom/><diagonal/></border>
		<border>
			<left style="thin"><color rgb="FFE4D7BF"/></left>
			<right style="thin"><color rgb="FFE4D7BF"/></right>
			<top style="thin"><color rgb="FFE4D7BF"/></top>
			<bottom style="thin"><color rgb="FFE4D7BF"/></bottom>
			<diagonal/>
		</border>
	</borders>
	<cellStyleXfs count="1">
		<xf numFmtId="0" fontId="0" fillId="0" borderId="0"/>
	</cellStyleXfs>
	<cellXfs count="30">
		<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>
		<xf numFmtId="0" fontId="1" fillId="8" borderId="0" xfId="0" applyFont="1" applyFill="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>
		<xf numFmtId="0" fontId="2" fillId="8" borderId="0" xfId="0" applyFont="1" applyFill="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>
		<xf numFmtId="0" fontId="3" fillId="3" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>
		<xf numFmtId="0" fontId="4" fillId="2" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>
		<xf numFmtId="0" fontId="3" fillId="2" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>
		<xf numFmtId="0" fontId="3" fillId="2" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>
		<xf numFmtId="0" fontId="0" fillId="2" borderId="1" xfId="0" applyFill="1" applyBorder="1" applyAlignment="1"><alignment vertical="top" wrapText="1"/></xf>
		<xf numFmtId="0" fontId="0" fillId="4" borderId="1" xfId="0" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>
		<xf numFmtId="0" fontId="0" fillId="5" borderId="1" xfId="0" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>
		<xf numFmtId="0" fontId="0" fillId="6" borderId="1" xfId="0" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>
		<xf numFmtId="0" fontId="0" fillId="7" borderId="1" xfId="0" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>
		<xf numFmtId="0" fontId="0" fillId="2" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment vertical="top" wrapText="1"/></xf>
		<xf numFmtId="0" fontId="3" fillId="3" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment vertical="center"/></xf>
		<xf numFmtId="0" fontId="2" fillId="2" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>
		<xf numFmtId="0" fontId="5" fillId="9" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>
		<xf numFmtId="0" fontId="3" fillId="3" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment vertical="center" wrapText="1"/></xf>
		<xf numFmtId="0" fontId="6" fillId="10" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>
		<xf numFmtId="0" fontId="4" fillId="4" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>
		<xf numFmtId="0" fontId="7" fillId="4" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>
		<xf numFmtId="0" fontId="6" fillId="11" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>
		<xf numFmtId="0" fontId="4" fillId="5" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>
		<xf numFmtId="0" fontId="7" fillId="5" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>
		<xf numFmtId="0" fontId="6" fillId="12" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>
		<xf numFmtId="0" fontId="4" fillId="6" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>
		<xf numFmtId="0" fontId="7" fillId="6" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>
		<xf numFmtId="0" fontId="6" fillId="13" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>
		<xf numFmtId="0" fontId="4" fillId="7" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>
		<xf numFmtId="0" fontId="7" fillId="7" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>
		<xf numFmtId="0" fontId="3" fillId="3" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>
	</cellXfs>
	<cellStyles count="1">
		<cellStyle name="Normal" xfId="0" builtinId="0"/>
	</cellStyles>
</styleSheet>
XML;
}

function audit_opquast_xlsx_content_types($sheet_count) {
	$xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
	<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
	<Default Extension="xml" ContentType="application/xml"/>
	<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>
	<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>
	<Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>
	<Override PartName="/docProps/app.xml" ContentType="application/vnd.openxmlformats-officedocument.extended-properties+xml"/>
XML;

	for ($i = 1; $i <= intval($sheet_count); $i++) {
		$xml .= '<Override PartName="/xl/worksheets/sheet' . $i . '.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
	}

	$xml .= '</Types>';

	return $xml;
}

function audit_opquast_xlsx_root_rels() {
	return <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
	<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>
	<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/>
	<Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/extended-properties" Target="docProps/app.xml"/>
</Relationships>
XML;
}

function audit_opquast_xlsx_core_xml() {
	$timestamp = gmdate('Y-m-d\TH:i:s\Z');

	return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
		. '<cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:dcterms="http://purl.org/dc/terms/" xmlns:dcmitype="http://purl.org/dc/dcmitype/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">'
		. '<dc:title>Audit Opquast</dc:title>'
		. '<dc:creator>Audit Opquast</dc:creator>'
		. '<cp:lastModifiedBy>Audit Opquast</cp:lastModifiedBy>'
		. '<dcterms:created xsi:type="dcterms:W3CDTF">' . $timestamp . '</dcterms:created>'
		. '<dcterms:modified xsi:type="dcterms:W3CDTF">' . $timestamp . '</dcterms:modified>'
		. '</cp:coreProperties>';
}

function audit_opquast_xlsx_app_xml($sheets) {
	$parts = '';

	foreach ($sheets as $sheet) {
		$parts .= '<vt:lpstr>' . audit_opquast_xlsx_escape($sheet['name']) . '</vt:lpstr>';
	}

	return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
		. '<Properties xmlns="http://schemas.openxmlformats.org/officeDocument/2006/extended-properties" xmlns:vt="http://schemas.openxmlformats.org/officeDocument/2006/docPropsVTypes">'
		. '<Application>Audit Opquast</Application>'
		. '<HeadingPairs><vt:vector size="2" baseType="variant"><vt:variant><vt:lpstr>Worksheets</vt:lpstr></vt:variant><vt:variant><vt:i4>' . count($sheets) . '</vt:i4></vt:variant></vt:vector></HeadingPairs>'
		. '<TitlesOfParts><vt:vector size="' . count($sheets) . '" baseType="lpstr">' . $parts . '</vt:vector></TitlesOfParts>'
		. '</Properties>';
}

function audit_opquast_xlsx_workbook_xml($sheets) {
	$xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
	$xml .= '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets>';

	foreach ($sheets as $index => $sheet) {
		$xml .= '<sheet name="' . audit_opquast_xlsx_escape($sheet['name']) . '" sheetId="' . ($index + 1) . '" r:id="rId' . ($index + 1) . '"/>';
	}

	$xml .= '</sheets></workbook>';

	return $xml;
}

function audit_opquast_xlsx_workbook_rels($sheet_count) {
	$xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
	$xml .= '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">';

	for ($i = 1; $i <= intval($sheet_count); $i++) {
		$xml .= '<Relationship Id="rId' . $i . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet' . $i . '.xml"/>';
	}

	$xml .= '<Relationship Id="rId' . ($sheet_count + 1) . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>';
	$xml .= '</Relationships>';

	return $xml;
}

function audit_opquast_xlsx_column_name($index) {
	$index = intval($index);
	$name = '';

	while ($index > 0) {
		$index--;
		$name = chr(65 + ($index % 26)) . $name;
		$index = intval($index / 26);
	}

	return $name;
}

function audit_opquast_xlsx_escape($value) {
	$value = (string) $value;
	$value = htmlspecialchars($value, ENT_XML1 | ENT_COMPAT, 'UTF-8');

	return str_replace(["\r\n", "\r", "\n"], '&#10;', $value);
}

function audit_opquast_xlsx_number($value) {
	return str_replace(',', '.', (string) (0 + $value));
}
