<?php

/**
 * Définit les autorisations du plugin Audit OpQuast
 *
 * @plugin     Audit OpQuast
 * @copyright  2026
 * @author     Mikaël
 * @licence    GNU/GPL
 * @package    SPIP\Audit OpQuast\Autorisations
 */

if (!defined('_ECRIRE_INC_VERSION')) {
	return;
}


/**
 * Fonction d'appel pour le pipeline
 * @pipeline autoriser
 */
function audit_opquast_autoriser() {
}


/**
 * Autoriser la configuration du plugin
 *
 * @return bool
 */
function autoriser_audit_opquast_configurer_dist($faire, $type, $id, $qui, $opt) {
	return autoriser('webmestre', $type, $id, $qui, $opt);
}
