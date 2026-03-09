<?php
require '../../../main.inc.php';

top_httphead('application/json; charset=UTF-8');

if (!$user->id) {
	http_response_code(401);
	echo json_encode(array(
		'success' => false,
		'error' => 'Unauthorized'
	));
	exit;
}

$sql = "SELECT
			s.rowid,
			s.nom,
			s.address,
			s.zip,
			s.town,
			s.client,
			s.fournisseur,
			se.fl_geotiers_lat,
			se.fl_geotiers_long
		FROM ".MAIN_DB_PREFIX."societe as s
		INNER JOIN ".MAIN_DB_PREFIX."societe_extrafields as se ON se.fk_object = s.rowid
		WHERE s.entity IN (".getEntity('societe').")
		  AND se.fl_geotiers_lat IS NOT NULL
		  AND se.fl_geotiers_lat <> ''
		  AND se.fl_geotiers_long IS NOT NULL
		  AND se.fl_geotiers_long <> ''
		ORDER BY s.nom ASC";

$resql = $db->query($sql);

if (!$resql) {
	http_response_code(500);
	echo json_encode(array(
		'success' => false,
		'error' => $db->lasterror()
	));
	exit;
}

$points = array();

while ($obj = $db->fetch_object($resql)) {
	if ($obj->fl_geotiers_lat === null || $obj->fl_geotiers_lat === '') {
		continue;
	}
	if ($obj->fl_geotiers_long === null || $obj->fl_geotiers_long === '') {
		continue;
	}

	$points[] = array(
	'id' => (int) $obj->rowid,
	'name' => $obj->nom,
	'lat' => (float) $obj->fl_geotiers_lat,
	'lng' => (float) $obj->fl_geotiers_long,
	'address' => $obj->address,
	'zip' => $obj->zip,
	'town' => $obj->town,
	'url' => DOL_URL_ROOT.'/societe/card.php?socid='.(int) $obj->rowid,
	'client' => (int) $obj->client,
	'fournisseur' => (int) $obj->fournisseur
    );
}

echo json_encode(array(
	'success' => true,
	'points' => $points
));
exit;