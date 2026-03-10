document.addEventListener('DOMContentLoaded', function () {
    'use strict';

    function escapeHtml(value) {
        return value
        if (value === null || value === undefined) return '';
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function isValidCoordinate(value) {
        return value !== null && value !== undefined && value !== '' && !isNaN(parseFloat(value));
    }

    function buildPopup(point) {
        var html = '<div class="flgeotiers-popup">';

        if (point.url) {
            html += '<div><strong><a href="' + escapeHtml(point.url) + '">' + escapeHtml(point.name || 'Tiers') + '</a></strong></div>';
        } else {
            html += '<div><strong>' + escapeHtml(point.name || 'Tiers') + '</strong></div>';
        }

        if (point.address || point.zip || point.town) {
            html += '<div style="margin-top:6px;">';
            if (point.address) {
                html += escapeHtml(point.address) + '<br>';
            }
            html += escapeHtml((point.zip || '') + ' ' + (point.town || ''));
            html += '</div>';
        }

        html += '<div style="margin-top:6px;font-size:11px;color:#666;">';
        
        html += '</div>';

        html += '</div>';

        return html;
    }

    function setLoading(message) {
        var loadingEl = document.getElementById('flgeotiers-loading');
        if (loadingEl) {
            loadingEl.textContent = message;
            loadingEl.style.display = 'block';
        }
    }

    function hideLoading() {
        var loadingEl = document.getElementById('flgeotiers-loading');
        if (loadingEl) {
            loadingEl.style.display = 'none';
        }
    }

    function setCount(count) {
        var countEl = document.getElementById('flgeotiers-count');
        if (countEl) {
            countEl.textContent = count + ' tiers affiché(s)';
            countEl.style.display = 'block';
        }
    }

    function getSelectedValues(elementId) {
        var element = document.getElementById(elementId);
        if (!element) {
            return [];
        }

        return Array.from(element.selectedOptions || []).map(function (option) {
            return option.value;
        }).filter(function (value) {
            return value !== '';
        });
    }

    function getCurrentFilters() {
        return {
            tiers: getSelectedValues('filterTiers'),
            types: getSelectedValues('filterType')
        };
    }

    async function fetchPoints() {

        let filters = getFilters();

        let params = new URLSearchParams();
        params.append('tiers', filters.tiersString);
        params.append('showProspects', filters.showProspects ? 1 : 0);
        params.append('showFournisseurs', filters.showFournisseurs ? 1 : 0);
        params.append('showClients', filters.showClients ? 1 : 0);

        // params.append('tiers', (filters.tiers || []).join(','));
        // params.append('type', (filters.types || []).join(','));

        var response = await fetch(DOL_URL_ROOT + '/custom/geotiers/ajax/getPoints.php?' + params.toString(), {
            method: 'GET',
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            },
            credentials: 'same-origin'
        });

        if (!response.ok) {
            throw new Error('Erreur HTTP ' + response.status);
        }

        var data = await response.json();

        if (!data || !data.success) {
            throw new Error((data && data.error) ? data.error : 'Erreur inconnue');
        }

        return Array.isArray(data.points) ? data.points : [];
    }

    function buildIcon(iconUrl, color) {
        if (iconUrl) {
            return L.icon({
                iconUrl: iconUrl,
                iconSize: [32, 32],
                iconAnchor: [16, 32],
                popupAnchor: [0, -32]
            });
        }

        var fillColor = color || '#eca76a';

        const markerHtmlStyles = `
        background-color: ${fillColor};
        width: 23px;
        height: 23px;
        display: block;
        left: -8px;
        top: -8px;
        position: relative;
        border-radius: 16px 16px 0;
        transform: rotate(45deg);
        border: 2px solid #FFFFFF;`;

        return L.divIcon({
            className: '',
            iconAnchor: [4, 20],
            popupAnchor: [0, -24],
            html: `<span style="${markerHtmlStyles}"></span>`
        });
    }

function getPointType(point) {
    if (parseInt(point.fournisseur, 10) > 0) {
        return 'fournisseur';
    }
    if (parseInt(point.client, 10) === 2) {
        return 'prospect';
    }
    if (parseInt(point.client, 10) > 0) {
        return 'client';
    }
    return 'client';
}

    function getFilters() {
        let selectElement = document.getElementById('filterTiers');
        let selectedValues = Array.from(selectElement.selectedOptions).map(option => option.value);

        let tiersString = selectedValues.join(',');

        return {
            tiersString: tiersString,
            showProspects: document.getElementById('filterShowProspects').checked,
            showFournisseurs: document.getElementById('filterShowFournisseurs').checked,
            showClients: document.getElementById('filterShowClients').checked
        };
    }

    async function initMap() {
        var mapContainer = document.getElementById('flgeotiers-map');
        if (!mapContainer) {
            return;
        }

        if (typeof L === 'undefined') {
            console.error('Leaflet n\'est pas chargé.');
            setLoading('Leaflet n\'est pas chargé.');
            return;
        }

        var colors = window.flGeoTiersColors || {};
        var iconsConfig = window.flGeoTiersIcons || {};

        var map = L.map('flgeotiers-map');
        var markerLayer = L.layerGroup().addTo(map);

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19
        }).addTo(map);

        var iconsConfig = window.flGeoTiersIcons || {};
        var leafletIcons = {
            client:      buildIcon(iconsConfig.client,      colors.client),
            fournisseur: buildIcon(iconsConfig.fournisseur, colors.fournisseur),
            prospect:    buildIcon(iconsConfig.prospect,    colors.prospect)
        };

        async function refreshMap() {
            // setLoading('Chargement des points...');

            try {
                var rawPoints = await fetchPoints();
                var points = rawPoints.filter(function (point) {
                    return isValidCoordinate(point.lat) && isValidCoordinate(point.lng);
                });
                var bounds = L.latLngBounds([]);

                markerLayer.clearLayers();
                hideLoading();
                setCount(points.length);

                if (!points.length) {
                    map.setView([46.603354, 1.888334], 6);
                    return;
                }

                points.forEach(function (point) {
                    var lat = parseFloat(point.lat);
                    var lng = parseFloat(point.lng);

                    var pointType = getPointType(point);
                    var markerOptions = {};

                    if (leafletIcons[pointType]) {
                        markerOptions.icon = leafletIcons[pointType];
                    }

                    var marker = L.marker([lat, lng], markerOptions).addTo(markerLayer);
                    marker.bindPopup(buildPopup(point));

                    bounds.extend([lat, lng]);
                });

                if (points.length === 1) {
                    map.setView([parseFloat(points[0].lat), parseFloat(points[0].lng)], 13);
                } else {
                    map.fitBounds(bounds, { padding: [30, 30] });
                }
            } catch (error) {
                console.error(error);
                markerLayer.clearLayers();
                setLoading('Erreur lors du chargement des points.');
                map.setView([46.603354, 1.888334], 6);
            }
        }

        await refreshMap();

        ['filterTiers', 'filterShowProspects', 'filterShowFournisseurs', 'filterShowClients'].forEach(function (elementId) {
            var element = document.getElementById(elementId);
            if (element) {
                element.addEventListener('change', refreshMap);
            }
        });
    }

    initMap();
});
