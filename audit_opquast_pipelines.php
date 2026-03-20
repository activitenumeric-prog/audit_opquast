<?php

/**
 * Utilisations de pipelines par Audit OpQuast
 *
 * @plugin     Audit OpQuast
 * @copyright  2026
 * @licence    GNU/GPL
 */

if (!defined('_ECRIRE_INC_VERSION')) {
	return;
}

function audit_opquast_insert_head($flux) {
	$css = find_in_path('css/audit_opquast.css');

	if ($css) {
		$flux .= '<link rel="stylesheet" href="' . $css . '" type="text/css" media="all" />';
	}

	return $flux;
}
