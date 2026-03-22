<?php

if (!defined('_ECRIRE_INC_VERSION')) {
	return;
}

function audit_opquast_referentiel_regles() {
	static $regles = null;

	if (is_array($regles)) {
		return $regles;
	}

	$json = audit_opquast_referentiel_charger_json();

	if ($json === '') {
		$regles = [];
		return $regles;
	}

	$items = json_decode($json, true);
	$complements = audit_opquast_referentiel_complements();

	if (!is_array($items)) {
		$regles = [];
		return $regles;
	}

	$regles = [];

	foreach ($items as $item) {
		$numero = intval($item['name']);
		$complement = $complements[(string) $numero] ?? [];
		$regles[] = [
			'numero' => $numero,
			'titre' => isset($item['description']) ? trim($item['description']) : '',
			'famille' => isset($item['thema'][0]['name']) ? trim($item['thema'][0]['name']) : '',
			'slug_famille' => isset($item['thema'][0]['slugify']) ? trim($item['thema'][0]['slugify']) : '',
			'objectif' => audit_opquast_referentiel_html($complement['objectif'] ?? ($item['goal'] ?? '')),
			'mise_en_oeuvre' => audit_opquast_referentiel_html($complement['mise_en_oeuvre'] ?? ''),
			'controle' => audit_opquast_referentiel_html($complement['controle'] ?? ''),
			'mots_cles' => audit_opquast_referentiel_liste_noms(isset($item['tags']) ? $item['tags'] : []),
			'phases' => audit_opquast_referentiel_liste_noms(isset($item['steps']) ? $item['steps'] : []),
			'url_source' => 'https://checklists.opquast.com' . (isset($item['url']) ? $item['url'] : ''),
			'niveau_automatisation' => 'non_classe',
			'actif' => 1,
			'version_referentiel' => 'v5-2025-2030',
		];
	}

	return $regles;
}

function audit_opquast_referentiel_html($html) {
	$html = trim((string) $html);

	if ($html === '') {
		return '';
	}

	$html = html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8');
	$html = audit_opquast_referentiel_reparer_encodage($html);
	$html = preg_replace("/\r\n|\r/u", "\n", $html);

	return trim((string) $html);
}

function audit_opquast_referentiel_reparer_encodage($texte) {
	$texte = (string) $texte;

	if ($texte === '' || !preg_match('/Ã.|Â.|â€™|â€œ|â€\x9d|â€"|â€“|â€”/u', $texte)) {
		return $texte;
	}

	$repare = @mb_convert_encoding($texte, 'UTF-8', 'Windows-1252');

	return is_string($repare) && $repare !== '' ? $repare : $texte;
}

function audit_opquast_referentiel_liste_noms($items) {
	if (!is_array($items)) {
		return '';
	}

	$noms = [];

	foreach ($items as $item) {
		if (is_array($item) && !empty($item['name'])) {
			$noms[] = trim($item['name']);
		}
	}

	return implode(', ', $noms);
}

function audit_opquast_referentiel_charger_json() {
	$fichier = audit_opquast_referentiel_fichier_source();

	if (!is_file($fichier) || !is_readable($fichier)) {
		return '';
	}

	$b64 = file_get_contents($fichier);

	if (!is_string($b64) || $b64 === '') {
		return '';
	}

	return audit_opquast_referentiel_decoder($b64);
}

function audit_opquast_referentiel_complements() {
	static $complements = null;

	if (is_array($complements)) {
		return $complements;
	}

	$fichier = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'referentiel' . DIRECTORY_SEPARATOR . 'opquast_v5_2025_2030_complements.json';

	if (!is_file($fichier) || !is_readable($fichier)) {
		$complements = [];
		return $complements;
	}

	$json = file_get_contents($fichier);

	if (!is_string($json) || $json === '') {
		$complements = [];
		return $complements;
	}

	$data = json_decode($json, true);
	$complements = is_array($data['items'] ?? null) ? $data['items'] : [];

	return $complements;
}

function audit_opquast_referentiel_fichier_source() {
	return dirname(__DIR__) . DIRECTORY_SEPARATOR . 'referentiel' . DIRECTORY_SEPARATOR . 'opquast_v5_2025_2030.b64';
}

function audit_opquast_referentiel_decoder($b64) {
	$gzip = base64_decode(preg_replace('/\s+/', '', $b64));

	if ($gzip === false) {
		return '';
	}

	if (function_exists('gzdecode')) {
		$json = gzdecode($gzip);
		return is_string($json) ? $json : '';
	}

	$stream = fopen('php://temp', 'r+');

	if (!$stream) {
		return '';
	}

	fwrite($stream, $gzip);
	rewind($stream);
	stream_filter_append($stream, 'zlib.inflate', STREAM_FILTER_READ, ['window' => 31]);
	$json = stream_get_contents($stream);
	fclose($stream);

	return is_string($json) ? $json : '';
}
