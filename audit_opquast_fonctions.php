<?php

if (!defined('_ECRIRE_INC_VERSION')) {
	return;
}

include_spip('inc/actions');

function audit_opquast_statuts_audit($valeur = null) {
	$statuts = [
		'brouillon' => _T('audit_opquast:statut_audit_brouillon'),
		'en_cours' => _T('audit_opquast:statut_audit_en_cours'),
		'termine' => _T('audit_opquast:statut_audit_termine'),
	];

	if ($valeur === null) {
		return $statuts;
	}

	return $statuts[$valeur] ?? $valeur;
}

function audit_opquast_statuts_verification($valeur = null) {
	$statuts = [
		'a_verifier' => _T('audit_opquast:statut_verification_a_verifier'),
		'conforme' => _T('audit_opquast:statut_verification_conforme'),
		'non_conforme' => _T('audit_opquast:statut_verification_non_conforme'),
		'non_applicable' => _T('audit_opquast:statut_verification_non_applicable'),
	];

	if ($valeur === null) {
		return $statuts;
	}

	return $statuts[$valeur] ?? $valeur;
}

function audit_opquast_classe_badge_statut_verification($valeur = null) {
	$classes = [
		'a_verifier' => 'audit-opquast-badge--a-verifier',
		'conforme' => 'audit-opquast-badge--conforme',
		'non_conforme' => 'audit-opquast-badge--non-conforme',
		'non_applicable' => 'audit-opquast-badge--non-applicable',
	];

	if ($valeur === null) {
		return $classes;
	}

	return $classes[$valeur] ?? '';
}

function audit_opquast_formater_score($valeur) {
	if ($valeur === null || $valeur === '') {
		return '--';
	}

	return number_format((float) $valeur, 2, '.', '');
}

function audit_opquast_tri_regles($valeur = null) {
	$tris = [
		'priorite' => _T('audit_opquast:tri_regles_priorite'),
		'numero' => _T('audit_opquast:tri_regles_numero'),
		'famille' => _T('audit_opquast:tri_regles_famille'),
		'statut' => _T('audit_opquast:tri_regles_statut'),
	];

	if ($valeur === null) {
		return $tris;
	}

	return $tris[$valeur] ?? $tris['priorite'];
}

function audit_opquast_types_cible($valeur = null) {
	$types = [
		'url' => _T('audit_opquast:type_cible_url'),
		'site' => _T('audit_opquast:type_cible_site'),
		'objet' => _T('audit_opquast:type_cible_objet'),
	];

	if ($valeur === null) {
		return $types;
	}

	return $types[$valeur] ?? $valeur;
}

function audit_opquast_lister_audits($valeur = null) {
	include_spip('base/abstract_sql');

	$audits = sql_allfetsel(
		'id_audit, titre, url_cible, type_cible, statut, date_creation, date_modif, id_auteur',
		'spip_audit_opquast_audits',
		'',
		'',
		'date_modif DESC, id_audit DESC'
	);

	return is_array($audits) ? $audits : [];
}

function audit_opquast_lire_audit($id_audit) {
	include_spip('base/abstract_sql');
	$id_audit = intval($id_audit);

	if (!$id_audit) {
		return [];
	}

	$audit = sql_fetsel('*', 'spip_audit_opquast_audits', 'id_audit=' . $id_audit);

	return is_array($audit) ? $audit : [];
}

function audit_opquast_est_url_externe($valeur) {
	$valeur = trim((string) $valeur);

	if ($valeur === '') {
		return false;
	}

	return (bool) preg_match(',^https?://,i', $valeur);
}

function audit_opquast_lire_regle($id_regle) {
	include_spip('base/abstract_sql');
	$id_regle = intval($id_regle);

	if (!$id_regle) {
		return [];
	}

	$regle = sql_fetsel('*', 'spip_audit_opquast_regles', 'id_regle=' . $id_regle);

	return is_array($regle) ? $regle : [];
}

function audit_opquast_lire_resultat($id_audit, $id_regle) {
	include_spip('base/abstract_sql');
	$id_audit = intval($id_audit);
	$id_regle = intval($id_regle);

	if (!$id_audit || !$id_regle) {
		return [];
	}

	$resultat = sql_fetsel(
		'*',
		'spip_audit_opquast_resultats',
		'id_audit=' . $id_audit . ' AND id_regle=' . $id_regle
	);

	return is_array($resultat) ? $resultat : [];
}

function audit_opquast_slug($texte) {
	$texte = translitteration(trim((string) $texte));
	$texte = strtolower($texte);
	$texte = preg_replace('/[^a-z0-9]+/', '-', $texte);
	$texte = trim((string) $texte, '-');

	return $texte !== '' ? $texte : 'audit';
}

function audit_opquast_nom_fichier_export_audit($audit, $format = 'csv') {
	$titre = audit_opquast_slug($audit['titre'] ?? 'audit-opquast');
	$format = trim((string) $format) ?: 'csv';

	return 'audit-opquast-' . $titre . '.' . $format;
}

function audit_opquast_donnees_export_audit($id_audit) {
	include_spip('base/abstract_sql');

	$id_audit = intval($id_audit);
	$audit = audit_opquast_lire_audit($id_audit);

	if (!$id_audit || !$audit) {
		return [];
	}

	$rows = sql_allfetsel(
		'r.numero, r.titre, r.famille, r.url_source, res.statut_verification, res.commentaire, res.preuve',
		'spip_audit_opquast_regles AS r LEFT JOIN spip_audit_opquast_resultats AS res ON (res.id_regle=r.id_regle AND res.id_audit=' . $id_audit . ')',
		'',
		'',
		'r.numero ASC'
	);

	$rows = audit_opquast_normaliser_regles($rows);

	foreach ($rows as &$row) {
		$row['audit_titre'] = $audit['titre'] ?? '';
		$row['url_cible'] = $audit['url_cible'] ?? '';
		$row['type_cible'] = audit_opquast_types_cible($audit['type_cible'] ?? '');
		$row['statut_audit'] = audit_opquast_statuts_audit($audit['statut'] ?? '');
		$row['statut_verification'] = audit_opquast_statuts_verification($row['statut_verification'] ?? 'a_verifier');
		$row['preuve'] = trim((string) ($row['preuve'] ?? ''));
	}
	unset($row);

	return $rows;
}

function audit_opquast_appliquer_statut_famille($id_audit, $famille, $statut_verification, $id_auteur = 0) {
	include_spip('base/abstract_sql');

	$id_audit = intval($id_audit);
	$famille = trim((string) $famille);
	$statut_verification = trim((string) $statut_verification);
	$id_auteur = intval($id_auteur);

	if (
		!$id_audit
		|| $famille === ''
		|| !in_array($statut_verification, ['a_verifier', 'non_applicable'], true)
	) {
		return 0;
	}

	$regles = sql_allfetsel(
		'id_regle',
		'spip_audit_opquast_regles',
		'famille=' . sql_quote($famille),
		'',
		'numero ASC'
	);

	if (!$regles) {
		return 0;
	}

	$maintenant = date('Y-m-d H:i:s');
	$nb = 0;

	foreach ($regles as $regle) {
		$id_regle = intval($regle['id_regle'] ?? 0);

		if (!$id_regle) {
			continue;
		}

		$existant = sql_fetsel(
			'id_resultat',
			'spip_audit_opquast_resultats',
			'id_audit=' . $id_audit . ' AND id_regle=' . $id_regle
		);

		if ($existant) {
			sql_updateq(
				'spip_audit_opquast_resultats',
				[
					'statut_verification' => $statut_verification,
					'date_modif' => $maintenant,
					'id_auteur' => $id_auteur,
				],
				'id_resultat=' . intval($existant['id_resultat'])
			);
		} else {
			sql_insertq(
				'spip_audit_opquast_resultats',
				[
					'id_audit' => $id_audit,
					'id_regle' => $id_regle,
					'statut_verification' => $statut_verification,
					'commentaire' => '',
					'preuve' => '',
					'date_modif' => $maintenant,
					'id_auteur' => $id_auteur,
				]
			);
		}

		$nb++;
	}

	return $nb;
}

function audit_opquast_resume_audit($id_audit) {
	include_spip('base/abstract_sql');
	$id_audit = intval($id_audit);

	$resume = [
		'total_regles' => 0,
		'traitees' => 0,
		'progression' => 0,
		'score_conformite' => null,
		'conforme' => 0,
		'non_conforme' => 0,
		'non_applicable' => 0,
		'a_verifier' => 0,
	];

	if (!$id_audit) {
		return $resume;
	}

	$resume['total_regles'] = intval(sql_countsel('spip_audit_opquast_regles'));
	$resume['conforme'] = intval(sql_countsel('spip_audit_opquast_resultats', 'id_audit=' . $id_audit . " AND statut_verification='conforme'"));
	$resume['non_conforme'] = intval(sql_countsel('spip_audit_opquast_resultats', 'id_audit=' . $id_audit . " AND statut_verification='non_conforme'"));
	$resume['non_applicable'] = intval(sql_countsel('spip_audit_opquast_resultats', 'id_audit=' . $id_audit . " AND statut_verification='non_applicable'"));
	$resume['traitees'] = $resume['conforme'] + $resume['non_conforme'] + $resume['non_applicable'];
	$resume['a_verifier'] = max(0, $resume['total_regles'] - $resume['traitees']);
	$resume['progression'] = $resume['total_regles']
		? intval(round(($resume['traitees'] / $resume['total_regles']) * 100))
		: 0;
	$total_conformite = $resume['conforme'] + $resume['non_conforme'];
	$resume['score_conformite'] = $total_conformite
		? round(($resume['conforme'] / $total_conformite) * 100, 2)
		: null;

	return $resume;
}

function audit_opquast_lister_familles($valeur = null) {
	include_spip('base/abstract_sql');

	$familles = sql_allfetsel(
		'DISTINCT famille',
		'spip_audit_opquast_regles',
		"famille!=''",
		'',
		'famille ASC'
	);

	$options = [];

	if (is_array($familles)) {
		foreach ($familles as $famille) {
			if (!empty($famille['famille'])) {
				$options[] = ['valeur' => $famille['famille'], 'label' => $famille['famille']];
			}
		}
	}

	return $options;
}

function audit_opquast_statut_ordre($statut) {
	$ordre = [
		'non_conforme' => 0,
		'a_verifier' => 1,
		'conforme' => 2,
		'non_applicable' => 3,
	];

	return $ordre[$statut] ?? 9;
}

function audit_opquast_regle_est_prioritaire($regle) {
	$statut = (string) ($regle['statut_verification'] ?? 'a_verifier');

	return in_array($statut, ['non_conforme', 'a_verifier'], true);
}

function audit_opquast_normaliser_regles($regles) {
	if (!is_array($regles)) {
		return [];
	}

	foreach ($regles as &$regle) {
		if (empty($regle['statut_verification'])) {
			$regle['statut_verification'] = 'a_verifier';
		}

		$regle['prioritaire'] = audit_opquast_regle_est_prioritaire($regle) ? 1 : 0;
		$regle['ordre_statut'] = audit_opquast_statut_ordre($regle['statut_verification']);
		$regle['commentaire'] = trim((string) ($regle['commentaire'] ?? ''));
		$regle['famille'] = trim((string) ($regle['famille'] ?? ''));
		if ($regle['famille'] === '') {
			$regle['famille'] = _T('audit_opquast:label_famille_non_renseignee');
		}
	}
	unset($regle);

	return $regles;
}

function audit_opquast_trier_regles($regles, $tri = 'priorite') {
	$tri = in_array($tri, ['priorite', 'numero', 'famille', 'statut'], true) ? $tri : 'priorite';

	usort($regles, function ($a, $b) use ($tri) {
		$numeroA = intval($a['numero'] ?? 0);
		$numeroB = intval($b['numero'] ?? 0);
		$familleA = strtolower((string) ($a['famille'] ?? ''));
		$familleB = strtolower((string) ($b['famille'] ?? ''));
		$ordreA = intval($a['ordre_statut'] ?? 9);
		$ordreB = intval($b['ordre_statut'] ?? 9);
		$commentaireA = trim((string) ($a['commentaire'] ?? '')) !== '' ? 0 : 1;
		$commentaireB = trim((string) ($b['commentaire'] ?? '')) !== '' ? 0 : 1;

		if ($tri === 'famille') {
			if ($familleA !== $familleB) {
				return $familleA <=> $familleB;
			}
			if ($ordreA !== $ordreB) {
				return $ordreA <=> $ordreB;
			}
			return $numeroA <=> $numeroB;
		}

		if ($tri === 'statut') {
			if ($ordreA !== $ordreB) {
				return $ordreA <=> $ordreB;
			}
			if ($familleA !== $familleB) {
				return $familleA <=> $familleB;
			}
			return $numeroA <=> $numeroB;
		}

		if ($tri === 'numero') {
			return $numeroA <=> $numeroB;
		}

		if ($ordreA !== $ordreB) {
			return $ordreA <=> $ordreB;
		}
		if ($commentaireA !== $commentaireB) {
			return $commentaireA <=> $commentaireB;
		}
		if ($familleA !== $familleB) {
			return $familleA <=> $familleB;
		}
		return $numeroA <=> $numeroB;
	});

	return $regles;
}

function audit_opquast_lister_regles_audit($id_audit, $filtres = []) {
	include_spip('base/abstract_sql');
	$id_audit = intval($id_audit);

	if (!$id_audit) {
		return [];
	}

	$where = [];
	$famille = trim((string) ($filtres['famille'] ?? ''));
	$statut = trim((string) ($filtres['statut_verification'] ?? ''));
	$recherche = trim((string) ($filtres['q'] ?? ''));
	$tri = trim((string) ($filtres['tri'] ?? 'priorite'));

	if ($famille !== '') {
		$where[] = 'r.famille=' . sql_quote($famille);
	}

	if ($statut !== '') {
		if ($statut === 'a_verifier') {
			$where[] = "(res.statut_verification IS NULL OR res.statut_verification='a_verifier')";
		} else {
			$where[] = 'res.statut_verification=' . sql_quote($statut);
		}
	}

	if ($recherche !== '') {
		$mot = sql_quote('%' . $recherche . '%');
		$where[] = '(r.numero=' . intval($recherche) . ' OR r.titre LIKE ' . $mot . ' OR r.famille LIKE ' . $mot . ')';
	}

	$regles = sql_allfetsel(
		'r.id_regle, r.numero, r.titre, r.famille, r.url_source, res.id_resultat, res.statut_verification, res.commentaire',
		'spip_audit_opquast_regles AS r LEFT JOIN spip_audit_opquast_resultats AS res ON (res.id_regle=r.id_regle AND res.id_audit=' . $id_audit . ')',
		$where ? implode(' AND ', $where) : '',
		'',
		'r.numero ASC'
	);

	$regles = audit_opquast_normaliser_regles($regles);

	return audit_opquast_trier_regles($regles, $tri);
}

function audit_opquast_parametres_filtres($overrides = []) {
	$filtres = [];

	$q = trim((string) _request('q'));
	$famille = trim((string) _request('famille'));
	$tri = trim((string) _request('tri')) ?: 'priorite';
	$statut = trim((string) _request('statut_verification_filtre'));
	if (!array_key_exists('statut_verification_filtre', $_REQUEST ?? [])) {
		$statut = trim((string) _request('statut_verification'));
	}

	if ($q !== '') {
		$filtres['q'] = $q;
	}

	if ($famille !== '') {
		$filtres['famille'] = $famille;
	}

	if ($statut !== '') {
		$filtres['statut_verification'] = $statut;
	}

	if ($tri !== '' && $tri !== 'priorite') {
		$filtres['tri'] = $tri;
	}

	foreach ($overrides as $cle => $valeur) {
		if ($valeur === null || $valeur === '') {
			unset($filtres[$cle]);
		} else {
			$filtres[$cle] = $valeur;
		}
	}

	return $filtres;
}

function audit_opquast_lister_regles_mvp($id_audit) {
	return audit_opquast_lister_regles_audit($id_audit, audit_opquast_parametres_filtres());
}

function audit_opquast_resume_familles($id_audit) {
	$regles = audit_opquast_lister_regles_audit($id_audit, ['tri' => 'priorite']);
	$familles = [];

	foreach ($regles as $regle) {
		$famille = $regle['famille'];

		if (!isset($familles[$famille])) {
			$familles[$famille] = [
				'famille' => $famille,
				'total_regles' => 0,
				'traitees' => 0,
				'progression' => 0,
				'conforme' => 0,
				'non_conforme' => 0,
				'non_applicable' => 0,
				'a_verifier' => 0,
				'priorite' => 0,
			];
		}

		$statut = $regle['statut_verification'];
		$familles[$famille]['total_regles']++;

		if ($statut === 'conforme') {
			$familles[$famille]['conforme']++;
			$familles[$famille]['traitees']++;
		} elseif ($statut === 'non_conforme') {
			$familles[$famille]['non_conforme']++;
			$familles[$famille]['traitees']++;
			$familles[$famille]['priorite'] += 3;
		} elseif ($statut === 'non_applicable') {
			$familles[$famille]['non_applicable']++;
			$familles[$famille]['traitees']++;
		} else {
			$familles[$famille]['a_verifier']++;
			$familles[$famille]['priorite'] += 1;
		}
	}

	foreach ($familles as &$famille) {
		$famille['progression'] = $famille['total_regles']
			? intval(round(($famille['traitees'] / $famille['total_regles']) * 100))
			: 0;
	}
	unset($famille);

	uasort($familles, function ($a, $b) {
		if ($a['priorite'] !== $b['priorite']) {
			return $b['priorite'] <=> $a['priorite'];
		}
		if ($a['non_conforme'] !== $b['non_conforme']) {
			return $b['non_conforme'] <=> $a['non_conforme'];
		}
		if ($a['a_verifier'] !== $b['a_verifier']) {
			return $b['a_verifier'] <=> $a['a_verifier'];
		}
		return strcasecmp((string) $a['famille'], (string) $b['famille']);
	});

	return array_values($familles);
}

function audit_opquast_synthese_decisionnelle($id_audit) {
	$regles = audit_opquast_lister_regles_audit($id_audit, ['tri' => 'priorite']);
	$familles = audit_opquast_resume_familles($id_audit);
	$synthese = [
		'nb_non_conformes' => 0,
		'nb_a_verifier' => 0,
		'familles_prioritaires' => array_slice($familles, 0, 3),
		'regles_prioritaires' => [],
	];

	foreach ($regles as $regle) {
		if ($regle['statut_verification'] === 'non_conforme') {
			$synthese['nb_non_conformes']++;
		} elseif ($regle['statut_verification'] === 'a_verifier') {
			$synthese['nb_a_verifier']++;
		}

		if (
			count($synthese['regles_prioritaires']) < 5
			&& in_array($regle['statut_verification'], ['non_conforme', 'a_verifier'], true)
		) {
			$synthese['regles_prioritaires'][] = $regle;
		}
	}

	$top = $synthese['familles_prioritaires'][0] ?? [];
	$synthese['famille_prioritaire_nom'] = $top['famille'] ?? '';
	$synthese['famille_prioritaire_non_conforme'] = intval($top['non_conforme'] ?? 0);

	return $synthese;
}

function audit_opquast_navigation_regle($id_audit, $id_regle) {
	$id_regle = intval($id_regle);
	$regles = audit_opquast_lister_regles_mvp($id_audit);
	$total = count($regles);

	$navigation = [
		'position' => 0,
		'total' => $total,
		'courante_id_regle' => 0,
		'courante_numero' => '',
		'courante_titre' => '',
		'courante_statut_verification' => 'a_verifier',
		'courante_statut_label' => audit_opquast_statuts_verification('a_verifier'),
		'precedente_id_regle' => 0,
		'precedente_numero' => '',
		'precedente_titre' => '',
		'suivante_id_regle' => 0,
		'suivante_numero' => '',
		'suivante_titre' => '',
	];

	if (!$total) {
		return $navigation;
	}

	if (!$id_regle) {
		$id_regle = intval($regles[0]['id_regle'] ?? 0);
	}

	$index_courant = null;

	foreach ($regles as $index => $regle) {
		if (intval($regle['id_regle'] ?? 0) !== $id_regle) {
			continue;
		}

		$index_courant = $index;
		break;
	}

	if ($index_courant === null) {
		$index_courant = 0;
	}

	$regle = $regles[$index_courant] ?? [];

	if (!$regle) {
		return $navigation;
	}

	$navigation['position'] = $index_courant + 1;
	$navigation['courante_id_regle'] = intval($regle['id_regle'] ?? 0);
	$navigation['courante_numero'] = $regle['numero'] ?? '';
	$navigation['courante_titre'] = $regle['titre'] ?? '';
	$navigation['courante_statut_verification'] = $regle['statut_verification'] ?? 'a_verifier';
	$navigation['courante_statut_label'] = audit_opquast_statuts_verification($navigation['courante_statut_verification']);

	if (isset($regles[$index_courant - 1])) {
		$precedente = $regles[$index_courant - 1];
		$navigation['precedente_id_regle'] = intval($precedente['id_regle'] ?? 0);
		$navigation['precedente_numero'] = $precedente['numero'] ?? '';
		$navigation['precedente_titre'] = $precedente['titre'] ?? '';
	}

	if (isset($regles[$index_courant + 1])) {
		$suivante = $regles[$index_courant + 1];
		$navigation['suivante_id_regle'] = intval($suivante['id_regle'] ?? 0);
		$navigation['suivante_numero'] = $suivante['numero'] ?? '';
		$navigation['suivante_titre'] = $suivante['titre'] ?? '';
	}

	return $navigation;
}

function audit_opquast_regle_visible_dans_filtres($id_audit, $id_regle, $filtres = []) {
	$id_regle = intval($id_regle);

	if (!$id_regle) {
		return false;
	}

	$regles = audit_opquast_lister_regles_audit($id_audit, $filtres);

	foreach ($regles as $regle) {
		if (intval($regle['id_regle'] ?? 0) === $id_regle) {
			return true;
		}
	}

	return false;
}

function audit_opquast_premiere_regle_visible($id_audit, $filtres = []) {
	$regles = audit_opquast_lister_regles_audit($id_audit, $filtres);

	if (!$regles) {
		return 0;
	}

	return intval($regles[0]['id_regle'] ?? 0);
}

function audit_opquast_regle_suivante_visible($id_audit, $id_regle, $filtres = []) {
	$id_regle = intval($id_regle);
	$regles = audit_opquast_lister_regles_audit($id_audit, $filtres);

	if (!$id_regle || !$regles) {
		return 0;
	}

	foreach ($regles as $index => $regle) {
		if (intval($regle['id_regle'] ?? 0) !== $id_regle) {
			continue;
		}

		return intval($regles[$index + 1]['id_regle'] ?? 0);
	}

	return 0;
}

function audit_opquast_regle_redirect_apres_resultat($id_audit, $id_regle, $filtres = []) {
	$id_regle = intval($id_regle);

	if (audit_opquast_regle_visible_dans_filtres($id_audit, $id_regle, $filtres)) {
		return $id_regle;
	}

	$id_regle_redirect = audit_opquast_regle_suivante_visible($id_audit, $id_regle, $filtres);

	if ($id_regle_redirect) {
		return $id_regle_redirect;
	}

	return audit_opquast_premiere_regle_visible($id_audit, $filtres);
}

function audit_opquast_raccourcis_statuts($id_audit) {
	$resume = audit_opquast_resume_audit($id_audit);
	$statuts = [
		[
			'cle' => '',
			'label' => _T('audit_opquast:raccourci_toutes'),
			'compteur' => intval($resume['total_regles'] ?? 0),
		],
		[
			'cle' => 'non_conforme',
			'label' => _T('audit_opquast:statut_verification_non_conforme'),
			'compteur' => intval($resume['non_conforme'] ?? 0),
		],
		[
			'cle' => 'a_verifier',
			'label' => _T('audit_opquast:statut_verification_a_verifier'),
			'compteur' => intval($resume['a_verifier'] ?? 0),
		],
		[
			'cle' => 'conforme',
			'label' => _T('audit_opquast:statut_verification_conforme'),
			'compteur' => intval($resume['conforme'] ?? 0),
		],
		[
			'cle' => 'non_applicable',
			'label' => _T('audit_opquast:statut_verification_non_applicable'),
			'compteur' => intval($resume['non_applicable'] ?? 0),
		],
	];

	$actif = trim((string) _request('statut_verification'));

	foreach ($statuts as &$statut) {
		$statut['actif'] = ($actif === '' && $statut['cle'] === '') || $actif === $statut['cle'] ? 1 : 0;
		$statut['url'] = audit_opquast_url_audit_filtre($id_audit, 0, ['statut_verification' => $statut['cle']]);
	}
	unset($statut);

	return $statuts;
}

function audit_opquast_url_audit($id_audit, $id_regle = 0) {
	include_spip('inc/utils');

	$args = ['id_audit' => intval($id_audit)];

	if (intval($id_regle)) {
		$args['id_regle'] = intval($id_regle);
	}

	return generer_url_public('audit_opquast_audit', http_build_query($args, '', '&'));
}

function audit_opquast_url_audit_filtre($id_audit, $id_regle = 0, $overrides = []) {
	include_spip('inc/utils');

	$args = array_merge(
		['id_audit' => intval($id_audit)],
		audit_opquast_parametres_filtres($overrides)
	);

	if (intval($id_regle)) {
		$args['id_regle'] = intval($id_regle);
	}

	return generer_url_public('audit_opquast_audit', http_build_query($args, '', '&'));
}

function audit_opquast_url_navigation_filtre($id_audit, $id_regle = 0, $overrides = []) {
	include_spip('inc/utils');

	$args = array_merge(
		['id_audit' => intval($id_audit)],
		audit_opquast_parametres_filtres($overrides)
	);

	if (intval($id_regle)) {
		$args['id_regle'] = intval($id_regle);
	}

	return generer_url_public('audit_opquast_navigation', http_build_query($args, '', '&'));
}

function audit_opquast_url_audit_prioritaire($id_audit, $id_regle = 0) {
	return audit_opquast_url_audit_filtre($id_audit, $id_regle, [
		'q' => null,
		'famille' => null,
		'statut_verification' => null,
	]);
}

function audit_opquast_url_navigation_prioritaire($id_audit, $id_regle = 0) {
	return audit_opquast_url_navigation_filtre($id_audit, $id_regle, [
		'q' => null,
		'famille' => null,
		'statut_verification' => null,
	]);
}

function audit_opquast_url_resultats_famille($id_audit, $famille = '') {
	include_spip('inc/utils');

	$args = array_merge(
		['id_audit' => intval($id_audit)],
		audit_opquast_parametres_filtres([
			'id_regle' => null,
			'q' => null,
			'famille' => trim((string) $famille),
			'statut_verification' => null,
		])
	);

	return generer_url_public('audit_opquast_resultats', http_build_query($args, '', '&'));
}

function audit_opquast_url_resultats($id_audit, $overrides = []) {
	include_spip('inc/utils');

	$args = array_merge(
		['id_audit' => intval($id_audit)],
		audit_opquast_parametres_filtres(array_merge([
			'id_regle' => null,
		], $overrides))
	);

	unset($args['id_regle']);

	return generer_url_public('audit_opquast_resultats', http_build_query($args, '', '&'));
}

function audit_opquast_url_parametres($id_audit, $mode = '') {
	include_spip('inc/utils');

	$args = array_merge(
		['id_audit' => intval($id_audit)],
		audit_opquast_parametres_filtres()
	);

	$id_regle = intval(_request('id_regle'));

	if ($id_regle) {
		$args['id_regle'] = $id_regle;
	}

	if ($mode !== '') {
		$args['parametres_mode'] = trim((string) $mode);
	}

	return generer_url_public('audit_opquast_parametres', http_build_query($args, '', '&'));
}

function audit_opquast_url_audit_parametres($id_audit, $mode = '') {
	include_spip('inc/utils');

	$args = array_merge(
		['id_audit' => intval($id_audit)],
		audit_opquast_parametres_filtres()
	);

	$id_regle = intval(_request('id_regle'));

	if ($id_regle) {
		$args['id_regle'] = $id_regle;
	}

	if ($mode !== '') {
		$args['parametres_mode'] = trim((string) $mode);
	}

	return generer_url_public('audit_opquast_audit', http_build_query($args, '', '&'));
}

function audit_opquast_url_audit_famille($id_audit, $famille = '') {
	return audit_opquast_url_audit_filtre($id_audit, 0, ['famille' => trim((string) $famille)]);
}

function audit_opquast_url_liste($valeur = null) {
	include_spip('inc/utils');

	return generer_url_public('audit_opquast');
}

function audit_opquast_url_creation_audit($mode = '') {
	include_spip('inc/utils');

	$args = [];

	if ($mode !== '') {
		$args['creation_mode'] = trim((string) $mode);
	}

	return generer_url_public('audit_opquast', http_build_query($args, '', '&'));
}

function audit_opquast_url_creation_region($mode = '') {
	include_spip('inc/utils');

	$args = [];

	if ($mode !== '') {
		$args['creation_mode'] = trim((string) $mode);
	}

	return generer_url_public('audit_opquast_creation', http_build_query($args, '', '&'));
}

function audit_opquast_url_audit_export($id_audit, $mode = '') {
	include_spip('inc/utils');

	$args = array_merge(
		['id_audit' => intval($id_audit)],
		audit_opquast_parametres_filtres()
	);

	$id_regle = intval(_request('id_regle'));

	if ($id_regle) {
		$args['id_regle'] = $id_regle;
	}

	if ($mode !== '') {
		$args['export_mode'] = trim((string) $mode);
	}

	return generer_url_public('audit_opquast_audit', http_build_query($args, '', '&'));
}

function audit_opquast_url_export_region($id_audit, $mode = '') {
	include_spip('inc/utils');

	$args = array_merge(
		['id_audit' => intval($id_audit)],
		audit_opquast_parametres_filtres()
	);

	$id_regle = intval(_request('id_regle'));

	if ($id_regle) {
		$args['id_regle'] = $id_regle;
	}

	if ($mode !== '') {
		$args['export_mode'] = trim((string) $mode);
	}

	return generer_url_public('audit_opquast_export', http_build_query($args, '', '&'));
}

function audit_opquast_url_export_telechargement($id_audit) {
	$id_audit = intval($id_audit);

	if (!$id_audit) {
		return '';
	}

	return generer_action_auteur(
		'audit_opquast_exporter_audit',
		(string) $id_audit,
		audit_opquast_url_audit($id_audit)
	);
}

function audit_opquast_url_enregistrer_resultat($id_audit, $id_regle) {
	$id_audit = intval($id_audit);
	$id_regle = intval($id_regle);

	if (!$id_audit || !$id_regle) {
		return '';
	}

	return generer_action_auteur(
		'audit_opquast_enregistrer_resultat',
		$id_audit . '-' . $id_regle,
		audit_opquast_url_audit($id_audit, $id_regle)
	);
}

function audit_opquast_url_supprimer_audit($id_audit) {
	$id_audit = intval($id_audit);

	if (!$id_audit) {
		return audit_opquast_url_liste();
	}

	return generer_action_auteur(
		'audit_opquast_supprimer_audit',
		(string) $id_audit,
		audit_opquast_url_liste()
	);
}
