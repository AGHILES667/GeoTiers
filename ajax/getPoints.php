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



//

// Contacts
$contacts = array();
$points = array();
require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';

$socIds = array();
$rawPoints = array();
while ($obj = $db->fetch_object($resql)) {
    $socIds[] = (int) $obj->rowid;
    $rawPoints[] = $obj;
}

// contacts
$contacts = array();
if (!empty($socIds)) {
    $sqlContacts = "SELECT
            sp.fk_soc,
            sp.firstname,
            sp.lastname,
            sp.email,
            sp.phone,
            sp.phone_mobile,
			sp.poste
        FROM ".MAIN_DB_PREFIX."socpeople as sp
        WHERE sp.fk_soc IN (".implode(',', $socIds).")
          AND sp.statut = 1
        ORDER BY sp.fk_soc, sp.rowid ASC";

    $resContacts = $db->query($sqlContacts);
    if ($resContacts) {
        while ($objC = $db->fetch_object($resContacts)) {
            $socId = (int) $objC->fk_soc;
            $contacts[$socId][] = array(
                'name'   => trim($objC->firstname.' '.$objC->lastname),
                'email'  => $objC->email        ?: '',
                'phone'  => $objC->phone        ?: '',
                'mobile' => $objC->phone_mobile ?: '',
				'poste'  => $objC->poste        ?: '',
            );
        }
    }
}

// commerciaux
$commerciaux = array();
if (!empty($socIds)) {
    $sqlCommerciaux = "SELECT
            sc.fk_soc,
            u.rowid as user_id,
            u.firstname,
            u.lastname,
            u.email,
            u.office_phone,
            u.user_mobile
        FROM ".MAIN_DB_PREFIX."societe_commerciaux as sc
        JOIN ".MAIN_DB_PREFIX."user as u ON u.rowid = sc.fk_user
        WHERE sc.fk_soc IN (".implode(',', $socIds).")
          AND u.statut = 1
        ORDER BY sc.fk_soc, u.lastname ASC";

    $resCommerciaux = $db->query($sqlCommerciaux);
    if ($resCommerciaux) {
        while ($objU = $db->fetch_object($resCommerciaux)) {
            $socId = (int) $objU->fk_soc;
            $commerciaux[$socId][] = array(
                'name'   => trim($objU->firstname.' '.$objU->lastname),
                'email'  => $objU->email        ?: '',
                'phone'  => $objU->office_phone ?: '',
                'mobile' => $objU->user_mobile  ?: ''
            );
        }
    }
}

// 
$companystatic = new Societe($db);
foreach ($rawPoints as $obj) {
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
        'contacts'    => $contacts[(int) $obj->rowid] ?? array(), 
		'commerciaux' => $commerciaux[(int) $obj->rowid] ?? array()
    );
}

//


echo json_encode(array(
	'success' => true,
	'points'  => $points
));
exit;