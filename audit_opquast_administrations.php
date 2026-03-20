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

	include_spip('base/upgrade');
	maj_plugin($nom_meta_base_version, $version_cible, $maj);
}

function audit_opquast_peupler_referentiel() {
	include_spip('base/abstract_sql');
	include_spip('inc/audit_opquast_referentiel');

	$regles = audit_opquast_referentiel_regles();

	if (!$regles || !is_array($regles)) {
		return;
	}

	$maintenant = date('Y-m-d H:i:s');

	foreach ($regles as $regle) {
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
	}
}

function audit_opquast_vider_tables($nom_meta_base_version) {
	sql_drop_table('spip_audit_opquast_resultats');
	sql_drop_table('spip_audit_opquast_audits');
	sql_drop_table('spip_audit_opquast_regles');

	effacer_meta($nom_meta_base_version);
	effacer_meta('audit_opquast');
}
