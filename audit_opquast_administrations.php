<?php

if (!defined('_ECRIRE_INC_VERSION')) {
	return;
}

function audit_opquast_upgrade($nom_meta_base_version, $version_cible) {
	$tables = [
		'spip_audit_opquast_regles',
		'spip_audit_opquast_audits',
		'spip_audit_opquast_resultats',
	];

	$maj = [];
	$maj['create'] = [
		['maj_tables', $tables],
		['audit_opquast_peupler_referentiel'],
	];
	$maj['1.1.0'] = [
		['maj_tables', $tables],
		['audit_opquast_peupler_referentiel'],
	];
	$maj['1.1.1'] = [
		['maj_tables', $tables],
		['audit_opquast_peupler_referentiel'],
	];
	$maj['1.1.2'] = [
		['maj_tables', $tables],
		['audit_opquast_peupler_referentiel'],
	];

	include_spip('base/upgrade');
	maj_plugin($nom_meta_base_version, $version_cible, $maj);
}

function audit_opquast_peupler_referentiel() {
	include_spip('base/abstract_sql');
	include_spip('base/upgrade');
	include_spip('inc/utils');
	include_spip('inc/audit_opquast_referentiel');

	$regles = audit_opquast_referentiel_regles();

	if (!$regles || !is_array($regles)) {
		effacer_meta('audit_opquast_import_index');
		return;
	}

	$index_depart = intval($GLOBALS['meta']['audit_opquast_import_index'] ?? 0);
	$total = count($regles);

	if ($index_depart >= $total) {
		effacer_meta('audit_opquast_import_index');
		return;
	}

	$maintenant = date('Y-m-d H:i:s');
	$lot_max = 25;
	$traitees = 0;

	for ($i = $index_depart; $i < $total; $i++) {
		$regle = $regles[$i];
		$set = [
			'numero' => intval($regle['numero']),
			'titre' => $regle['titre'],
			'famille' => $regle['famille'],
			'slug_famille' => $regle['slug_famille'],
			'mots_cles' => $regle['mots_cles'],
			'phases' => $regle['phases'],
			'url_source' => $regle['url_source'],
			'version_referentiel' => $regle['version_referentiel'],
			'actif' => intval($regle['actif']),
			'maj' => $maintenant,
		];

		$existant = sql_fetsel(
			'id_regle, niveau_automatisation',
			'spip_audit_opquast_regles',
			'numero=' . intval($regle['numero'])
		);

		if ($existant) {
			if (empty($existant['niveau_automatisation']) || $existant['niveau_automatisation'] === 'non_classe') {
				$set['niveau_automatisation'] = $regle['niveau_automatisation'];
			}

			sql_updateq(
				'spip_audit_opquast_regles',
				$set,
				'id_regle=' . intval($existant['id_regle'])
			);
		} else {
			$set['niveau_automatisation'] = $regle['niveau_automatisation'];
			sql_insertq('spip_audit_opquast_regles', $set);
		}

		$traitees++;
		ecrire_meta('audit_opquast_import_index', (string) ($i + 1));

		if (
			(defined('_TIME_OUT') && time() >= _TIME_OUT)
			|| $traitees >= $lot_max
		) {
			relance_maj('audit_opquast_base_version', 'meta', generer_url_ecrire('admin_plugin'));
		}
	}

	effacer_meta('audit_opquast_import_index');
}

function audit_opquast_vider_tables($nom_meta_base_version) {
	sql_drop_table('spip_audit_opquast_resultats');
	sql_drop_table('spip_audit_opquast_audits');
	sql_drop_table('spip_audit_opquast_regles');

	effacer_meta($nom_meta_base_version);
	effacer_meta('audit_opquast');
	effacer_meta('audit_opquast_import_index');
}
