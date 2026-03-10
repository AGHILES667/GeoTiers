<?php
require '../../main.inc.php';

$langs->loadLangs(array('companies'));

print '<link rel="stylesheet" href="'.DOL_URL_ROOT.'/custom/geotiers/css/map.css">';

llxHeader('', 'Carte des tiers');

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



// Récupération des couleurs depuis la config
$clientsColor = getDolGlobalString('GEOTIERS_COLOR_CLIENT', '#eca76a');
$fournisseursColor = getDolGlobalString('GEOTIERS_COLOR_FOURNISSEUR', '#eca76a');
$prospectsColor = getDolGlobalString('GEOTIERS_COLOR_PROSPECT', '#eca76a');
?>

<style>
    :root {
        --color-clients: <?php echo $clientsColor; ?>;
        --color-fournisseurs: <?php echo $fournisseursColor; ?>;
        --color-prospects: <?php echo $prospectsColor; ?>;
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
        prospect: '<?php echo dol_escape_js($prospectsColor); ?>'
    };
</script>

<div class="fiche">
    <div class="flgeotiers-page-title">
        <h1>Carte des tiers géolocalisés</h1>
        <div class="flgeotiers-filters">
        <div class="flgeotiers-filter-group">
            <select id="filterTiers" class="flgeotiers-filter-multi" data-label="Tiers" multiple name="tiers[]">
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
                <span class="toggle-track">
                    <span class="toggle-thumb"></span>
                </span>
                <span class="toggle-label">Prospects</span>
            </label>

            <label class="toggle-pill fournisseurs">
                <input type="checkbox" id="filterShowFournisseurs" checked>
                <span class="toggle-track">
                    <span class="toggle-thumb"></span>
                </span>
                <span class="toggle-label">Fourniseurs</span>
            </label>

            <label class="toggle-pill clients">
                <input type="checkbox" id="filterShowClients" checked>
                <span class="toggle-track">
                    <span class="toggle-thumb"></span>
                </span>
                <span class="toggle-label">Clients</span>
            </label>
        </div>
    </div>
    </div>

    <div class="flgeotiers-map-wrapper">
        <div id="flgeotiers-loading" class="flgeotiers-loading">Chargement des points...</div>
        <div id="flgeotiers-count" class="flgeotiers-count is-hidden"></div>
        <div id="flgeotiers-map"></div>
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
                noResultsText: 'Aucun résultat',
                noChoicesText: 'Aucun choix disponible'
            });
        });
    });
</script>

<script src="<?php echo dol_buildpath('/custom/geotiers/js/map.js', 1); ?>"></script>

<?php
llxFooter();