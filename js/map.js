var radiusCircle = null;


document.addEventListener('DOMContentLoaded', function () {
    'use strict';


    document.getElementById('btnFullscreen').addEventListener('click', function () {
    var isFullscreen = document.body.classList.toggle('flgeotiers-fullscreen-mode');
    document.getElementById('iconExpand').style.display   = isFullscreen ? 'none'  : '';
    document.getElementById('iconCompress').style.display = isFullscreen ? ''      : 'none';
    // Invalider la taille de la carte Leaflet après transition
    setTimeout(function () {
        var mapEl = document.getElementById('flgeotiers-map');
        if (mapEl && mapEl._leaflet_id) {
            window._flGeoTiersMap && window._flGeoTiersMap.invalidateSize();
        }
    }, 100);
});

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

        let lat = (point.geo).split(',')[0];
        let lng = (point.geo).split(',')[1];

        if (point.url) {
            html += '<div><strong><a href="' + escapeHtml(point.url) + '">' + escapeHtml(point.name || 'Tiers') + '</a></strong></div>';
        } else {
            html += '<div><strong>' + escapeHtml(point.name || 'Tiers') + '</strong></div>';
        }

        // Type
        if (point.typent) {
            html += '<div style="margin-top:4px;font-size:11px;color:#666;">' + escapeHtml(point.typent) + '</div>';
        }

        if (point.typeHtml) {
            html += ' '
            html += '<div style="margin-top:6px;"> <a style="margin-right:6px;" href="https://www.google.com/maps?q=&layer=c&cbll=' + point.geo+ '" target="_blank"><i class="fa fa-street-view"></i></a>' + point.typeHtml + '</div>';
        }

        if (point.address || point.zip || point.town) {
            html += '<div style="margin-top:6px;">';
            if (point.address) {
                html += escapeHtml(point.address) + '<br>';
            }
            html += escapeHtml((point.zip || '') + ' ' + (point.town || ''));
            html += '</div>';
        }

        if (point.contacts && point.contacts.length > 0) {
            html += '<div style="margin-top:6px;border-top:1px solid #eee;padding-top:6px;">';
            html += '<span style="font-size:10px;color:#999;text-transform:uppercase;">Contacts</span>';
            point.contacts.forEach(function(contact) {
                html += '<div style="margin-bottom:7px; margin-left:10px;font-size:11px;">';
                html += '<strong>' + escapeHtml(contact.name) + '</strong>';
                if (contact.poste){
                    html += '<br> 👷 ' + escapeHtml(contact.poste);
                }
                if (contact.phone) {
                    html += '<br>📞 ' + escapeHtml(contact.phone);
                }
                if (contact.mobile) {
                    html += '<br>📱 ' + escapeHtml(contact.mobile);
                }
                if (contact.email) {
                    html += '<br>✉️ <a href="mailto:' + escapeHtml(contact.email) + '">' 
                        + escapeHtml(contact.email) + '</a>';
                }
                html += '</div>';
            });
            html += '</div>';
        }

        if (point.commerciaux && point.commerciaux.length > 0) {
            html += '<div style="margin-top:6px;border-top:1px solid #eee;padding-top:6px;">';
            html += '<span style="font-size:10px;color:#999;text-transform:uppercase;">Commerciaux</span>';
            point.commerciaux.forEach(function(commercial) {
                html += '<div style="margin-bottom:4px; margin-left:10px;font-size:11px;">';
                html += '<strong>' + escapeHtml(commercial.name) + '</strong>';
                if (commercial.phone) {
                    html += '<br>📞 ' + escapeHtml(commercial.phone);
                }
                if (commercial.mobile) {
                    html += '<br>📱 ' + escapeHtml(commercial.mobile);
                }
                if (commercial.email) {
                    html += '<br>✉️ <a href="mailto:' + escapeHtml(commercial.email) + '">'
                        + escapeHtml(commercial.email) + '</a>';
                }
                html += '</div>';
            });
            html += '</div>';
        }



        html += '</div>';

        html += '<div style="margin-top:8px;border-top:1px solid #eee;padding-top:8px;">';
        html += '<div style="display:flex;align-items:center;gap:6px;">';
        html += '<span style="display:inline-block;">📍</span>';
        html += '<input id="flgeotiers-radius-input" type="number" min="1" max="500" placeholder="km"'
            + ' style="width:60px;padding:3px 6px;border:1px solid #d1d5db;border-radius:4px;font-size:12px;">';
        html += '<span style="font-size:11px;color:#6b7280;">km</span>';
        html += '<button onclick="applyRadiusFromPoint(' + lat + ',' + lng + ')"'
            + ' style="padding:3px 8px;background:#3b82f6;color:#fff;border:none;border-radius:4px;cursor:pointer;font-size:14px;line-height:1;">→</button>';
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
            countEl.textContent = count + ' ' + GEO_TIERS_TEXT.tiersDisplayed;
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

    async function fetchPoints() {

        let filters = getFilters();

        let params = new URLSearchParams();
        params.append('tiers', filters.tiersString);
        params.append('showProspects', filters.showProspects ? 1 : 0);
        params.append('showFournisseurs', filters.showFournisseurs ? 1 : 0);
        params.append('showClients', filters.showClients ? 1 : 0);


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

    function buildIcon( color) {

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
        let types = [];

        if (parseInt(point.fournisseur, 10) > 0) {
            types.push('fournisseur');
        }

        if (parseInt(point.client, 10) === 2) {
            types.push('prospect');
        } else if (parseInt(point.client, 10) > 0) {
            types.push('client');
        }

        if (types.length >= 2) {
            return 'multiType';
        }

        return types[0] || '';
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

        window._flGeoTiersMap = L.map('flgeotiers-map', {
            zoomControl: false  // désactive le zoom par défaut (haut gauche)
        });
                
        var map = window._flGeoTiersMap;
        L.control.zoom({
            position: 'topright'
        }).addTo(map);
        window._flGeoTiersMarkerLayer = L.layerGroup().addTo(map);
        var markerLayer = window._flGeoTiersMarkerLayer;

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19
        }).addTo(map);

        var leafletIcons = {
            client:      buildIcon(colors.client),
            fournisseur: buildIcon(colors.fournisseur),
            prospect:    buildIcon(colors.prospect),
            multiType:   buildIcon(colors.multiType)
        };

        async function refreshMap() {
            // setLoading('Chargement des points...');

            try {
                var rawPoints = await fetchPoints();
                var points = rawPoints.filter(function (point) {
                    return isValidCoordinate(point.geo);
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
                    point.lat = (point.geo).split(',')[0];
                    point.lng = (point.geo).split(',')[1];

                    var lat = (point.geo).split(',')[0];
                    var lng = (point.geo).split(',')[1];

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
        window._flGeoTiersRefreshMap = refreshMap;

        ['filterTiers', 'filterShowProspects', 'filterShowFournisseurs', 'filterShowClients'].forEach(function (elementId) {
            var element = document.getElementById(elementId);
            if (element) {
                element.addEventListener('change', refreshMap);
            }
        });
    }

    initMap();
});


function applyRadiusFromPoint(lat, lng) {
    var map = window._flGeoTiersMap;
    var markerLayer = window._flGeoTiersMarkerLayer;
    if (!map || !markerLayer) return;

    var input = document.getElementById('flgeotiers-radius-input');
    var radius = parseFloat(input ? input.value : 0);
    if (!radius || radius <= 0) return;

    map.closePopup();

    if (radiusCircle) map.removeLayer(radiusCircle);
    radiusCircle = L.circle([lat, lng], {
        radius: radius * 1000,
        color: '#3b82f6',
        fillColor: '#93c5fd',
        fillOpacity: 0.15,
        weight: 2
    }).addTo(map);

    var visibleCount = 0;
    markerLayer.eachLayer(function (marker) {
        var latlng = marker.getLatLng();
        var dist = haversineDistance(lat, lng, latlng.lat, latlng.lng);
        if (dist <= radius) {
            marker.setOpacity(1);
            marker.options._hiddenByRadius = false;
            visibleCount++;
        } else {
            marker.setOpacity(0);
            marker.options._hiddenByRadius = true;
        }
    });

    setCount(visibleCount);
    map.fitBounds(radiusCircle.getBounds(), { padding: [30, 30] });

    var resetBtn = document.getElementById('flgeotiers-radius-reset');
    if (resetBtn) resetBtn.hidden = false;
}

function setCount(count) {
    var countEl = document.getElementById('flgeotiers-count');
    if (countEl) {
        countEl.textContent = count + ' ' + GEO_TIERS_TEXT.tiersDisplayed;
        countEl.style.display = 'block';
    }
}

function haversineDistance(lat1, lng1, lat2, lng2) {
    var R = 6371;
    var dLat = (lat2 - lat1) * Math.PI / 180;
    var dLng = (lng2 - lng1) * Math.PI / 180;
    var a = Math.sin(dLat / 2) * Math.sin(dLat / 2)
        + Math.cos(lat1 * Math.PI / 180) * Math.cos(lat2 * Math.PI / 180)
        * Math.sin(dLng / 2) * Math.sin(dLng / 2);
    return R * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
}

document.getElementById('flgeotiers-radius-reset').addEventListener('click', function() {
    var map = window._flGeoTiersMap;
    var markerLayer = window._flGeoTiersMarkerLayer;

    var mapEl = document.getElementById('flgeotiers-map');
    mapEl.style.transition = 'opacity 0.15s';
    mapEl.style.opacity = '0.4';
    setTimeout(function() { mapEl.style.opacity = '1'; }, 150);

    if (radiusCircle) {
        map.removeLayer(radiusCircle);
        radiusCircle = null;
    }
    markerLayer.eachLayer(function(marker) {
        marker.setOpacity(1);
        marker.options._hiddenByRadius = false;
    });
    setCount(markerLayer.getLayers().length);
    var bounds = L.latLngBounds([]);
    markerLayer.eachLayer(function(m) { bounds.extend(m.getLatLng()); });
    if (bounds.isValid()) map.fitBounds(bounds, { padding: [30, 30] });

    this.hidden = true;

    if (window._flGeoTiersRefreshMap) {
        window._flGeoTiersRefreshMap();
    }
});