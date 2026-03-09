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

    async function fetchPoints() {
        var url = DOL_URL_ROOT + '/custom/geotiers/ajax/getPoints.php';

        var response = await fetch(url, {
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

    function buildIcon(iconUrl) {
    if (!iconUrl) {
        return null;
    }

    return L.icon({
        iconUrl: iconUrl,
        iconSize: [32, 32],
        iconAnchor: [16, 32],
        popupAnchor: [0, -32]
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

        setLoading('Chargement des points...');

        var map = L.map('flgeotiers-map');
        var bounds = L.latLngBounds([]);

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19
        }).addTo(map);

        try {
            var rawPoints = await fetchPoints();

            var points = rawPoints.filter(function (point) {
                return isValidCoordinate(point.lat) && isValidCoordinate(point.lng);
            });

            hideLoading();
            setCount(points.length);

            if (!points.length) {
                map.setView([46.603354, 1.888334], 6);
                return;
            }

            var iconsConfig = window.flGeoTiersIcons || {};

            var leafletIcons = {
                client: buildIcon(iconsConfig.client),
                fournisseur: buildIcon(iconsConfig.fournisseur),
                prospect: buildIcon(iconsConfig.prospect)
            };

            points.forEach(function (point) {
                var lat = parseFloat(point.lat);
                var lng = parseFloat(point.lng);

                var pointType = getPointType(point);
                var markerOptions = {};

                if (leafletIcons[pointType]) {
                    markerOptions.icon = leafletIcons[pointType];
                }

                var marker = L.marker([lat, lng], markerOptions).addTo(map);
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
            setLoading('Erreur lors du chargement des points.');
            map.setView([46.603354, 1.888334], 6);
        }
    }

    initMap();
});