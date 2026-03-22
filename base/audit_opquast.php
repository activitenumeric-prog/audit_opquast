<?php

if (!defined('_ECRIRE_INC_VERSION')) {
	return;
}

function audit_opquast_declarer_tables_principales($tables_principales) {
	$tables_principales['spip_audit_opquast_regles'] = [
		'field' => [
			'id_regle' => 'bigint(21) NOT NULL',
			'numero' => 'int(11) NOT NULL DEFAULT 0',
			'titre' => "varchar(255) NOT NULL DEFAULT ''",
			'famille' => "varchar(100) NOT NULL DEFAULT ''",
			'slug_famille' => "varchar(100) NOT NULL DEFAULT ''",
			'objectif' => "text NOT NULL DEFAULT ''",
			'mise_en_oeuvre' => "text NOT NULL DEFAULT ''",
			'controle' => "text NOT NULL DEFAULT ''",
			'mots_cles' => "text NOT NULL DEFAULT ''",
			'phases' => "text NOT NULL DEFAULT ''",
			'url_source' => "varchar(255) NOT NULL DEFAULT ''",
			'niveau_automatisation' => "varchar(20) NOT NULL DEFAULT 'non_classe'",
			'actif' => 'tinyint(1) NOT NULL DEFAULT 1',
			'version_referentiel' => "varchar(32) NOT NULL DEFAULT 'v5-2025-2030'",
			'maj' => "datetime NOT NULL DEFAULT '0000-00-00 00:00:00'",
		],
		'key' => [
			'PRIMARY KEY' => 'id_regle',
			'UNIQUE KEY numero' => 'numero',
			'KEY famille' => 'famille',
			'KEY slug_famille' => 'slug_famille',
			'KEY niveau_automatisation' => 'niveau_automatisation',
			'KEY actif' => 'actif',
		],
	];

	$tables_principales['spip_audit_opquast_audits'] = [
		'field' => [
			'id_audit' => 'bigint(21) NOT NULL',
			'titre' => "varchar(255) NOT NULL DEFAULT ''",
			'url_cible' => "text NOT NULL DEFAULT ''",
			'type_cible' => "varchar(20) NOT NULL DEFAULT 'url'",
			'objet' => "varchar(25) NOT NULL DEFAULT ''",
			'id_objet' => 'bigint(21) NOT NULL DEFAULT 0',
			'statut' => "varchar(20) NOT NULL DEFAULT 'brouillon'",
			'resume' => "text NOT NULL DEFAULT ''",
			'date_creation' => "datetime NOT NULL DEFAULT '0000-00-00 00:00:00'",
			'date_modif' => "datetime NOT NULL DEFAULT '0000-00-00 00:00:00'",
			'id_auteur' => 'bigint(21) NOT NULL DEFAULT 0',
		],
		'key' => [
			'PRIMARY KEY' => 'id_audit',
			'KEY statut' => 'statut',
			'KEY type_cible' => 'type_cible',
			'KEY objet_id_objet' => 'objet, id_objet',
			'KEY id_auteur' => 'id_auteur',
		],
	];

	$tables_principales['spip_audit_opquast_resultats'] = [
		'field' => [
			'id_resultat' => 'bigint(21) NOT NULL',
			'id_audit' => 'bigint(21) NOT NULL DEFAULT 0',
			'id_regle' => 'bigint(21) NOT NULL DEFAULT 0',
			'statut_verification' => "varchar(20) NOT NULL DEFAULT 'a_verifier'",
			'commentaire' => "text NOT NULL DEFAULT ''",
			'preuve' => "text NOT NULL DEFAULT ''",
			'date_modif' => "datetime NOT NULL DEFAULT '0000-00-00 00:00:00'",
			'id_auteur' => 'bigint(21) NOT NULL DEFAULT 0',
		],
		'key' => [
			'PRIMARY KEY' => 'id_resultat',
			'UNIQUE KEY audit_regle' => 'id_audit, id_regle',
			'KEY id_audit' => 'id_audit',
			'KEY id_regle' => 'id_regle',
			'KEY statut_verification' => 'statut_verification',
			'KEY id_auteur' => 'id_auteur',
		],
	];

	return $tables_principales;
}
