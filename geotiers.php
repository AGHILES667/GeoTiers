<?php
require '../../main.inc.php';

if (!$user->rights->geotiers->read) {
    accessforbidden();
}

$langs->loadLangs(array('companies'));

print '<link rel="stylesheet" href="'.DOL_URL_ROOT.'/custom/geotiers/css/map.css">';

llxHeader('', $langs->trans("GeoTiersMap"));

// Récupération des tiers géolocalisés
$sql = "SELECT s.rowid, s.nom, s.client, s.fournisseur
        FROM " . MAIN_DB_PREFIX . "societe s
        WHERE s.entity IN (" . getEntity('societe') . ")
        AND s.status = 1
        AND s.nom IS NOT NULL
        AND s.nom <> ''
        ORDER BY s.nom ASC";

$resql = $db->query($sql);
$tiers = array();

if ($resql) {
    while ($obj = $db->fetch_object($resql)) {
        $tiers[] = $obj;
    }
}

$sqlCount = "SELECT 
    SUM(CASE WHEN s.client = 1 THEN 1 ELSE 0 END) as nb_clients,
    SUM(CASE WHEN s.fournisseur = 1 THEN 1 ELSE 0 END) as nb_fournisseurs,
    SUM(CASE WHEN s.client = 2 THEN 1 ELSE 0 END) as nb_prospects
    FROM ".MAIN_DB_PREFIX."societe s
    WHERE s.entity IN (".getEntity('societe').")
    AND s.address IS NOT NULL AND s.zip IS NOT NULL AND s.town IS NOT NULL
    AND s.status = 1";

$resCount = $db->query($sqlCount);
$counts = $db->fetch_object($resCount);
$nb_clients      = $counts ? (int)$counts->nb_clients      : 0;
$nb_fournisseurs = $counts ? (int)$counts->nb_fournisseurs  : 0;
$nb_prospects    = $counts ? (int)$counts->nb_prospects     : 0;



// Récupération des couleurs depuis la config
$clientsColor = getDolGlobalString('GEOTIERS_COLOR_CLIENT', '#eca76a');
$fournisseursColor = getDolGlobalString('GEOTIERS_COLOR_FOURNISSEUR', '#eca76a');
$prospectsColor = getDolGlobalString('GEOTIERS_COLOR_PROSPECT', '#eca76a');
$multiTypeColor = getDolGlobalString('GEOTIERS_COLOR_MULTI_TYPE', '#eca76a');
?>

<style>
    :root {
        --color-clients: <?php echo $clientsColor; ?>;
        --color-fournisseurs: <?php echo $fournisseursColor; ?>;
        --color-prospects: <?php echo $prospectsColor; ?>;
        --color-multi-type: <?php echo $multiTypeColor; ?>;
    }
</style>

<link href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.css" rel="stylesheet">
<title>Planning des OF</title>
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.js"></script>
<link rel="stylesheet" href="https://unpkg.com/tippy.js@6/dist/tippy.css" />
<script src="https://unpkg.com/@popperjs/core@2"></script>
<script src="https://unpkg.com/tippy.js@6"></script>
<link rel="stylesheet" href="https://unpkg.com/leaflet/dist/leaflet.css">
<script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/choices.js/public/assets/styles/choices.min.css" />
<script src="https://cdn.jsdelivr.net/npm/choices.js/public/assets/scripts/choices.min.js"></script>

<script>
    var DOL_TOKEN = '<?php echo $_SESSION['newtoken']; ?>';
    var DOL_URL_ROOT = '<?php echo DOL_URL_ROOT; ?>';
</script>

<script>
    var DOL_URL_ROOT = '<?php echo DOL_URL_ROOT; ?>';

    window.flGeoTiersColors = {
        client: '<?php echo dol_escape_js($clientsColor); ?>',
        fournisseur: '<?php echo dol_escape_js($fournisseursColor); ?>',
        prospect: '<?php echo dol_escape_js($prospectsColor); ?>',
        multiType: '<?php echo dol_escape_js($multiTypeColor); ?>'
    };

    var GEO_TIERS_TEXT = {
        tiersDisplayed: "<?php echo $langs->transnoentities('TiersDisplayed'); ?>"
    };
</script>

<script>

    
</script>

<div class="fiche">
    <div class="flgeotiers-page-title">
        
        <div style="display: flex">
            
            <h1 style="font-size:18px!important"><?php echo $langs->trans("GeoTiersMapTitle"); ?></h1>

            <button id="btnFullscreen" class="flgeotiers-fullscreen-btn" title="<?php echo $langs->trans("FullScreen"); ?>">
                <svg id="iconExpand" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 3 21 3 21 9"/><polyline points="9 21 3 21 3 15"/><line x1="21" y1="3" x2="14" y2="10"/><line x1="3" y1="21" x2="10" y2="14"/></svg>
                <svg id="iconCompress" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="display:none"><polyline points="4 14 10 14 10 20"/><polyline points="20 10 14 10 14 4"/><line x1="10" y1="14" x2="3" y2="21"/><line x1="21" y1="3" x2="14" y2="10"/></svg>
            </button>
        
        </div>

        <div class="flgeotiers-filters">
        <div class="flgeotiers-filter-group">
            <select id="filterTiers" class="flgeotiers-filter-multi" data-label="<?php echo $langs->trans("ThirdParties"); ?>" multiple name="tiers[]">
                <?php foreach ($tiers as $tier): ?>
                    <option value="<?php echo (int) $tier->rowid; ?>">
                        <?php echo dol_htmlentities($tier->nom); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <!-- <div class="flgeotiers-filter-group">
            <select id="filterType" class="flgeotiers-filter-multi" data-label="Type" multiple name="type[]">
                <option value="client">Client</option>
                <option value="fournisseur">Fournisseur</option>
                <option value="prospect">Prospect</option>
            </select>
        </div> -->

        <div class="filter-divider"></div>

        <div class="filter-toggles">
            <label class="toggle-pill prospects">
                <input type="checkbox" id="filterShowProspects" checked>
                <span class="toggle-track"><span class="toggle-thumb"></span></span>
                <span class="toggle-label"><?php echo $langs->trans("Prospects"); ?> (<span class="toggle-count"><?php echo $nb_prospects; ?>)</span></span>
            </label>

            <label class="toggle-pill fournisseurs">
                <input type="checkbox" id="filterShowFournisseurs" checked>
                <span class="toggle-track"><span class="toggle-thumb"></span></span>
                <span class="toggle-label"><?php echo $langs->trans("Suppliers"); ?> (<span class="toggle-count"><?php echo $nb_fournisseurs; ?>)</span></span>
            </label>

            <label class="toggle-pill clients">
                <input type="checkbox" id="filterShowClients" checked>
                <span class="toggle-track"><span class="toggle-thumb"></span></span>
                <span class="toggle-label"><?php echo $langs->trans("Customers"); ?> (<span class="toggle-count"><?php echo $nb_clients; ?>)</span></span>
            </label>
        </div>
    </div>
    </div>

    <div class="flgeotiers-map-wrapper">
        <div id="flgeotiers-loading" class="flgeotiers-loading"><?php echo $langs->trans("MarkersLoading"); ?></div>
        <div id="flgeotiers-map">
            <div id="flgeotiers-count" class="flgeotiers-count is-hidden" hidden></div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        var filterElements = document.querySelectorAll('.flgeotiers-filter-multi');

        filterElements.forEach(function (element) {
            new Choices(element, {
                removeItemButton: true,
                searchEnabled: true,
                searchResultLimit: 8,
                shouldSort: false,
                placeholder: true,
                placeholderValue: element.dataset.label || 'Sélectionner',
                itemSelectText: '',
                noResultsText: '<?php echo $langs->trans("ChoicesNoResults"); ?>',
                noChoicesText: '<?php echo $langs->trans("ChoicesNoChoice"); ?>'
            });
        });
    });
</script>

<script src="<?php echo dol_buildpath('/custom/geotiers/js/map.js', 1); ?>"></script>

<?php
llxFooter();