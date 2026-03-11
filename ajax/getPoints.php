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
$tiersRaw        = GETPOST('tiers', 'restricthtml');
$showProspects   = (int) GETPOST('showProspects', 'int');
$showFournisseurs = (int) GETPOST('showFournisseurs', 'int');
$showClients     = (int) GETPOST('showClients', 'int');

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

$sql = "SELECT DISTINCT
			s.rowid,
			s.nom,
			s.address,
			s.zip,
			s.town,
			s.client,
			s.fournisseur,
			s.fk_typent,
			te.libelle as typent_libelle,
			se.fl_geotiers_lat,
			se.fl_geotiers_long
		FROM ".MAIN_DB_PREFIX."societe as s
		INNER JOIN ".MAIN_DB_PREFIX."societe_extrafields as se ON se.fk_object = s.rowid
		LEFT JOIN llx_c_typent as te ON te.id = s.fk_typent";
		
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

// Filtre par type via les 3 switches
$typeConditions = array();
if ($showFournisseurs) {
	$typeConditions[] = 's.fournisseur > 0';
}
if ($showProspects) {
	$typeConditions[] = 's.client IN (2, 3)';
}
if ($showClients) {
	$typeConditions[] = 's.client IN (1, 3)';
}

if (!empty($typeConditions)) {
	$sql .= ' AND ('.implode(' OR ', $typeConditions).')';
} else {
	// Aucun switch actif → aucun résultat
	echo json_encode(array(
		'success' => true,
		'points' => array()
	));
	exit;
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
require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';
$companystatic = new Societe($db);

while ($obj = $db->fetch_object($resql)) {
    $companystatic->id          = (int) $obj->rowid;
    $companystatic->client      = (int) $obj->client;
    $companystatic->fournisseur = (int) $obj->fournisseur;


    $points[] = array(
        'id'          => (int) $obj->rowid,
        'name'        => $obj->nom,
        'lat'         => (float) $obj->fl_geotiers_lat,
        'lng'         => (float) $obj->fl_geotiers_long,
        'address'     => $obj->address,
        'zip'         => $obj->zip,
        'town'        => $obj->town,
        'url'         => DOL_URL_ROOT.'/societe/card.php?socid='.(int) $obj->rowid,
        'client'      => (int) $obj->client,
        'fournisseur' => (int) $obj->fournisseur,
        'typeHtml'    => $companystatic->getTypeUrl(1),
        'typent'      => $obj->typent_libelle ?: '',
    );
}
echo json_encode(array(
	'success' => true,
	'points'  => $points
));
exit;