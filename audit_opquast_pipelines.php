<?php

/**
 * Utilisations de pipelines par Audit OpQuast
 *
 * @plugin     Audit OpQuast
 * @copyright  2026
 * @author     Mikaël
 * @licence    GNU/GPL
 * @package    SPIP\Audit OpQuast\Pipelines
 */

if (!defined('_ECRIRE_INC_VERSION')) {
	return;
}


/**
 * Insertion dans le head
 *
 * @pipeline insert_head
 * @param string $flux
 * @return string
 */
function audit_opquast_insert_head($flux) {
	// Ajouter des CSS ou JS ici
	// $flux .= '<link rel="stylesheet" href="' . find_in_path('css/style.css') . '" />';
	return $flux;
}
