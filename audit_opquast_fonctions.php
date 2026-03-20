<?php

/**
 * Fonctions utiles au plugin Audit OpQuast
 *
 * @plugin     Audit OpQuast
 * @copyright  2026
 * @author     Mikaël
 * @licence    GNU/GPL
 * @package    SPIP\Audit OpQuast\Fonctions
 */

if (!defined('_ECRIRE_INC_VERSION')) {
	return;
}


/**
 * Exemple de filtre personnalisé
 *
 * @param string $texte
 * @return string
 */
function filtre_audit_opquast_exemple($texte) {
	return strtoupper($texte);
}
