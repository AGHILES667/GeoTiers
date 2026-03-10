<?php
require '../../main.inc.php';

$langs->loadLangs(array('companies'));

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

print '<link rel="stylesheet" href="https://unpkg.com/leaflet/dist/leaflet.css">';
print '<script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>';

print '<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/choices.js/public/assets/styles/choices.min.css" />';
print '<script src="https://cdn.jsdelivr.net/npm/choices.js/public/assets/scripts/choices.min.js"></script>';

print '<link rel="stylesheet" href="' . dol_buildpath('/custom/geotiers/css/map.css', 1) . '">';
?>

<script>
    var DOL_URL_ROOT = '<?php echo DOL_URL_ROOT; ?>';

    window.flGeoTiersIcons = {
        client: '<?php echo dol_escape_js(getDolGlobalString('GEOTIERS_ICON_CLIENT')); ?>',
        fournisseur: '<?php echo dol_escape_js(getDolGlobalString('GEOTIERS_ICON_FOURNISSEUR')); ?>',
        prospect: '<?php echo dol_escape_js(getDolGlobalString('GEOTIERS_ICON_PROSPECT')); ?>'
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

        <div class="flgeotiers-filter-group">
            <select id="filterType" class="flgeotiers-filter-multi" data-label="Type" multiple name="type[]">
                <option value="client">Client</option>
                <option value="fournisseur">Fournisseur</option>
                <option value="prospect">Prospect</option>
            </select>
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