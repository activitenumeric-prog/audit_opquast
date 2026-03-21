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
	include_spip('inc/utils');

	$css = find_in_path('css/audit_opquast.css');

	if ($css) {
		$flux .= '<link rel="stylesheet" href="' . $css . '" type="text/css" media="all" />';
	}

	if (in_array(_request('page'), ['audit_opquast', 'audit_opquast_audit', 'audit_opquast_site'], true)) {
		$js = find_in_path('javascript/audit_opquast_navigation.js');

		if ($js) {
			$flux .= '<script src="' . $js . '" defer="defer"></script>';
		}
	}

	return $flux;
}
