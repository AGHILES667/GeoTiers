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

$canRead = !empty($user->rights->geotiers->read);
$canWrite = !empty($user->rights->geotiers->write);

if (!$canRead && !$canWrite) {
	echo json_encode(array(
		'success' => true,
		'points' => array()
	));
	exit;
}

$tiersRaw = GETPOST('tiers', 'restricthtml');
$typesRaw = GETPOST('type', 'restricthtml');

$tiersFilter = array();
if (!empty($tiersRaw)) {
	foreach (explode(',', $tiersRaw) as $tierId) {
		$tierId = (int) trim($tierId);
		if ($tierId > 0) {
			$tiersFilter[] = $tierId;
		}
	}
	$tiersFilter = array_values(array_unique($tiersFilter));
}

$allowedTypes = array('client', 'fournisseur', 'prospect');
$typesFilter = array();
if (!empty($typesRaw)) {
	foreach (explode(',', $typesRaw) as $type) {
		$type = trim((string) $type);
		if (in_array($type, $allowedTypes, true)) {
			$typesFilter[] = $type;
		}
	}
	$typesFilter = array_values(array_unique($typesFilter));
}

$sql = "SELECT DISTINCT
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
		INNER JOIN ".MAIN_DB_PREFIX."societe_extrafields as se ON se.fk_object = s.rowid";

if (!$canWrite && $canRead) {
	$sql .= " INNER JOIN ".MAIN_DB_PREFIX."societe_commerciaux as sc ON sc.fk_soc = s.rowid";
}

$sql .= " WHERE s.entity IN (".getEntity('societe').")
		  AND s.status = 1
		  AND se.fl_geotiers_lat IS NOT NULL
		  AND se.fl_geotiers_lat <> ''
		  AND se.fl_geotiers_long IS NOT NULL
		  AND se.fl_geotiers_long <> ''";

if (!$canWrite && $canRead) {
	$sql .= " AND sc.fk_user = ".((int) $user->id);
}

if (!empty($tiersFilter)) {
	$sql .= " AND s.rowid IN (".implode(',', $tiersFilter).")";
}

if (!empty($typesFilter)) {
	$typeConditions = array();

	if (in_array('fournisseur', $typesFilter, true)) {
		$typeConditions[] = 's.fournisseur > 0';
	}
	if (in_array('prospect', $typesFilter, true)) {
		$typeConditions[] = 's.client IN (2, 3)';
	}
	if (in_array('client', $typesFilter, true)) {
		$typeConditions[] = 's.client IN (1, 3)';
	}

	if (!empty($typeConditions)) {
		$sql .= ' AND ('.implode(' OR ', $typeConditions).')';
	}
}

$sql .= " ORDER BY s.nom ASC";

$resql = $db->query($sql);

if (!$resql) {
	http_response_code(500);
	echo json_encode(array(
		'success' => false,
		'error' => $db->lasterror(),
		'sql' => $sql
	));
	exit;
}

$points = array();

while ($obj = $db->fetch_object($resql)) {
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