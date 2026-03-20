<?php

if (!defined('_ECRIRE_INC_VERSION')) {
	return;
}

function audit_opquast_autoriser() {
}

function audit_opquast_auteur_autorise($qui) {
	if (empty($qui['id_auteur']) || empty($qui['statut'])) {
		return false;
	}

	return in_array($qui['statut'], ['0minirezo', '1comite']);
}

function autoriser_audit_opquast_voir_dist($faire, $type, $id, $qui, $opt) {
	return audit_opquast_auteur_autorise($qui);
}

function autoriser_audit_opquast_creer_dist($faire, $type, $id, $qui, $opt) {
	return audit_opquast_auteur_autorise($qui);
}

function autoriser_audit_opquast_modifier_dist($faire, $type, $id, $qui, $opt) {
	return audit_opquast_auteur_autorise($qui);
}

function autoriser_audit_opquast_configurer_dist($faire, $type, $id, $qui, $opt) {
	return autoriser('webmestre', $type, $id, $qui, $opt);
}
