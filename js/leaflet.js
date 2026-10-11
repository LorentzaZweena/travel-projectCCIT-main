let map;
let currentRouteLayer = null;
let originMarker = null;
let destMarker = null;
let selectedMode = 'driving';
let mapMarkers = {};
let placesMapKey = {};

function createIslandIcon(title) {
  return L.divIcon({
    className: 'custom-island-marker',
    html: `
      <div class="d-inline-flex align-items-center gap-1 bg-dark text-white px-2 py-1 rounded-pill shadow-lg border border-2 border-info fw-semibold" style="font-size: 0.75rem; white-space: nowrap; transform: translate(-50%, -100%);">
        <i class="fa-solid fa-location-dot text-warning"></i>
        <span>${title}</span>
      </div>
    `,
    iconSize: [0, 0]
  });
}

function createOriginIcon(name) {
  return L.divIcon({
    className: 'custom-origin-marker',
    html: `
      <div class="d-inline-flex align-items-center gap-1 bg-success text-white px-2 py-1 rounded-pill shadow-lg border border-2 border-white fw-bold" style="font-size: 0.78rem; white-space: nowrap; transform: translate(-50%, -100%);">
        <i class="fa-solid fa-circle-dot text-light"></i>
        <span>Asal: ${name}</span>
      </div>
    `,
    iconSize: [0, 0]
  });
}

function createDestIcon(name) {
  return L.divIcon({
    className: 'custom-dest-marker',
    html: `
      <div class="d-inline-flex align-items-center gap-1 bg-danger text-white px-2 py-1 rounded-pill shadow-lg border border-2 border-white fw-bold" style="font-size: 0.78rem; white-space: nowrap; transform: translate(-50%, -100%);">
        <i class="fa-solid fa-flag-checkered text-warning"></i>
        <span>Tujuan: ${name}</span>
      </div>
    `,
    iconSize: [0, 0]
  });
}

window.addEventListener('load', () => {
  map = L.map('map', {
    zoomControl: true,
    scrollWheelZoom: false
  }).setView([-2.548926, 118.014863], 5);

  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '&copy; OpenStreetMap contributors',
    maxZoom: 19
  }).addTo(map);

  if (typeof dbPlaces !== 'undefined' && dbPlaces.length > 0) {
    dbPlaces.forEach(place => {
      const lat = parseFloat(place.latitude);
      const lng = parseFloat(place.longitude);

      if (!isNaN(lat) && !isNaN(lng)) {
        const nameKey = place.name.toLowerCase().replace(/[^a-z0-9]/g, '');
        const locationKey = place.location.toLowerCase().replace(/[^a-z0-9]/g, '');

        const safeImage = place.image_url && place.image_url.startsWith('http') 
          ? place.image_url 
          : 'https://images.unsplash.com/photo-1537996194471-e657df975ab4?auto=format&fit=crop&w=400&q=80';

        const popupContent = `
          <div class="card border-0 bg-white overflow-hidden" style="width: 210px; border-radius: 12px;">
            <img src="${safeImage}" class="card-img-top" style="height: 95px; object-fit: cover;" alt="${place.name}">
            <div class="card-body p-2 text-dark">
              <span class="badge bg-info text-dark mb-1">${place.category || 'Destinasi'}</span>
              <h6 class="fw-bold mb-1 text-primary" style="font-size: 0.85rem;">${place.name}</h6>
              <p class="text-secondary mb-1" style="font-size: 0.72rem;"><i class="fa-solid fa-location-dot me-1"></i>${place.location}</p>
              <div class="fw-bold text-success small mb-2">Rp ${parseInt(place.price || 0).toLocaleString('id-ID')}</div>
              <button onclick="quickRoute('Jakarta', '${place.name.replace(/'/g, "\\'")}')" class="btn btn-primary btn-sm w-100 rounded-pill py-1" style="font-size:0.7rem;">
                <i class="fa-solid fa-route me-1"></i> Rute ke Sini
              </button>
            </div>
          </div>
        `;

        const marker = L.marker([lat, lng], { icon: createIslandIcon(place.name) })
          .addTo(map)
          .bindPopup(popupContent);

        mapMarkers[place.place_id] = { marker, lat, lng, name: place.name };
        placesMapKey[nameKey] = place.place_id;
        placesMapKey[locationKey] = place.place_id;
      }
    });
  }

  document.getElementById('originInput').addEventListener('keypress', (e) => {
    if (e.key === 'Enter') searchAndDrawRoute();
  });
  document.getElementById('destInput').addEventListener('keypress', (e) => {
    if (e.key === 'Enter') searchAndDrawRoute();
  });
});

function flyToLocation(locKey, btnElement = null) {
  if (!map) return;

  if (locKey === 'indonesia') {
    map.flyTo([-2.548926, 118.014863], 5, { animate: true, duration: 1.5 });
  } else {
    const cleanKey = locKey.toLowerCase().replace(/[^a-z0-9]/g, '');
    const placeId = placesMapKey[cleanKey];

    if (placeId && mapMarkers[placeId]) {
      const item = mapMarkers[placeId];
      map.flyTo([item.lat, item.lng], 11, { animate: true, duration: 1.5 });
      setTimeout(() => {
        item.marker.openPopup();
      }, 1500);
    } else {
      const foundEntry = Object.values(mapMarkers).find(m => 
        m.name.toLowerCase().includes(locKey.toLowerCase())
      );
      if (foundEntry) {
        map.flyTo([foundEntry.lat, foundEntry.lng], 11, { animate: true, duration: 1.5 });
        setTimeout(() => {
          foundEntry.marker.openPopup();
        }, 1500);
      }
    }
  }

  if (btnElement) {
    document.querySelectorAll('.map-filter-btn').forEach(b => b.classList.remove('active'));
    btnElement.classList.add('active');
  }
}

function selectTransportMode(mode, btnElement) {
  selectedMode = mode;
  document.querySelectorAll('.mode-btn').forEach(btn => btn.classList.remove('active'));
  btnElement.classList.add('active');
  
  const origin = document.getElementById('originInput').value;
  const dest = document.getElementById('destInput').value;
  if (origin && dest) {
    searchAndDrawRoute();
  }
}

function quickRoute(asal, tujuan) {
  document.getElementById('originInput').value = asal;
  document.getElementById('destInput').value = tujuan;
  searchAndDrawRoute();
}

async function searchAndDrawRoute() {
  const originQuery = document.getElementById('originInput').value.trim();
  const destQuery = document.getElementById('destInput').value.trim();

  if (!originQuery || !destQuery) {
    alert("Silakan isi lokasi asal dan destinasi terlebih dahulu!");
    return;
  }

  try {
    const originCoords = await geocodeLocation(originQuery);
    const destCoords = await geocodeLocation(destQuery);

    if (!originCoords || !destCoords) {
      alert("Lokasi tidak ditemukan. Coba ketik nama kota/tempat yang lebih spesifik.");
      return;
    }

    if (currentRouteLayer) map.removeLayer(currentRouteLayer);
    if (originMarker) map.removeLayer(originMarker);
    if (destMarker) map.removeLayer(destMarker);

    const straightDistKm = (map.distance(
      [originCoords.lat, originCoords.lon], 
      [destCoords.lat, destCoords.lon]
    ) / 1000).toFixed(1);

    originMarker = L.marker([originCoords.lat, originCoords.lon], { icon: createOriginIcon(originQuery) })
      .addTo(map);

    destMarker = L.marker([destCoords.lat, destCoords.lon], { icon: createDestIcon(destQuery) })
      .addTo(map);

    if (selectedMode === 'plane') {
      if (straightDistKm < 100) {
        alert("Mode pesawat tidak tersedia untuk rute jarak dekat (<100 km). Silakan gunakan Mobil, Motor, atau Jalan Kaki!");
        displayRouteInfo(`${straightDistKm} km`, "Tidak Tersedia", [
          { text: "Rute terlalu dekat untuk penerbangan. Silakan pilih Mobil, Motor, atau Jalan Kaki.", dist: "", icon: "fa-triangle-exclamation text-warning" }
        ]);
        return;
      }

      const latlngs = [
        [originCoords.lat, originCoords.lon],
        [destCoords.lat, destCoords.lon]
      ];
      currentRouteLayer = L.polyline(latlngs, {
        color: '#00b4d8',
        weight: 4,
        dashArray: '8, 8'
      }).addTo(map);

      map.fitBounds(currentRouteLayer.getBounds(), { padding: [60, 60] });

      const flightDuration = Math.round(straightDistKm / 650 * 60 + 45); 
      displayRouteInfo(`${straightDistKm} km`, `~${flightDuration} menit`, [
        { text: `Berangkat dari Bandara terdekat di ${originQuery}`, dist: "Sesuai Jadwal", icon: "fa-plane-departure text-info" },
        { text: "Penerbangan langsung antar wilayah", dist: `${straightDistKm} km`, icon: "fa-plane text-primary" },
        { text: `Mendarat di Bandara tujuan terdekat di ${destQuery}`, dist: "Tiba", icon: "fa-plane-arrival text-success" }
      ]);

    } else {
      let osrmProfile = 'driving';
      if (selectedMode === 'foot') osrmProfile = 'foot';

      const osrmUrl = `https://router.project-osrm.org/route/v1/${osrmProfile}/${originCoords.lon},${originCoords.lat};${destCoords.lon},${destCoords.lat}?overview=full&geometries=geojson&steps=true`;

      const res = await fetch(osrmUrl);
      const data = await res.json();

      if (data.code === 'Ok' && data.routes.length > 0) {
        const route = data.routes[0];
        
        currentRouteLayer = L.geoJSON(route.geometry, {
          style: {
            color: selectedMode === 'foot' ? '#10b981' : (selectedMode === 'bike' ? '#f59e0b' : '#00b4d8'),
            weight: 5,
            opacity: 0.85
          }
        }).addTo(map);

        map.fitBounds(currentRouteLayer.getBounds(), { padding: [60, 60] });

        const distanceKm = (route.distance / 1000).toFixed(1);
        let durationMin = Math.round(route.duration / 60);

        if (selectedMode === 'bike') {
          durationMin = Math.round((distanceKm / 35) * 60);
        } else if (selectedMode === 'foot') {
          durationMin = Math.round((distanceKm / 4.5) * 60);
        } else if (selectedMode === 'driving') {
          durationMin = Math.max(durationMin, Math.round((distanceKm / 45) * 60));
        }

        const formattedSteps = route.legs[0].steps.map(s => parseGoogleMapsInstruction(s));

        displayRouteInfo(`${distanceKm} km`, `${durationMin} menit`, formattedSteps);
      } else {
        alert("Rute jalan tidak ditemukan untuk lokasi ini.");
      }
    }
  } catch (err) {
    console.error(err);
    alert("Gagal mengambil data rute jalan.");
  }
}

function parseGoogleMapsInstruction(step) {
  const m = step.maneuver;
  const streetName = step.name ? `<b>${step.name}</b>` : '';
  const distText = step.distance < 1000 ? `${Math.round(step.distance)} m` : `${(step.distance / 1000).toFixed(1)} km`;

  let text = '';
  let icon = 'fa-arrow-up text-primary';

  switch (m.type) {
    case 'depart':
      text = `Mulai perjalanan ${streetName ? 'dari ' + streetName : ''}`;
      icon = 'fa-circle-dot text-success';
      break;
    case 'arrive':
      text = `Tiba di tujuan ${streetName ? '(' + streetName + ')' : ''}`;
      icon = 'fa-flag-checkered text-danger';
      break;
    case 'turn':
    case 'on ramp':
    case 'off ramp':
      if (m.modifier && m.modifier.includes('right')) {
        text = `Belok ${m.modifier.includes('slight') ? 'sedikit ' : ''}kanan ${streetName ? 'ke ' + streetName : ''}`;
        icon = 'fa-arrow-turn-right text-info';
      } else if (m.modifier && m.modifier.includes('left')) {
        text = `Belok ${m.modifier.includes('slight') ? 'sedikit ' : ''}kiri ${streetName ? 'ke ' + streetName : ''}`;
        icon = 'fa-arrow-turn-left text-info';
      } else {
        text = `Teruskan perjalanan ${streetName ? 'di ' + streetName : ''}`;
        icon = 'fa-arrow-up text-primary';
      }
      break;
    case 'continue':
    case 'new name':
      text = `Lurus terus ${streetName ? 'melintasi ' + streetName : ''}`;
      icon = 'fa-arrow-up text-primary';
      break;
    case 'roundabout':
    case 'rotary':
      text = `Di bundaran, ambil jalan keluar ke-${m.exit || 1} ${streetName ? 'menuju ' + streetName : ''}`;
      icon = 'fa-arrows-spin text-warning';
      break;
    default:
      text = `Lanjutkan ke ${streetName || 'jalan selanjutnya'}`;
      icon = 'fa-diamond-turn-right text-info';
  }

  return { text, dist: distText, icon };
}

async function geocodeLocation(query) {
  const url = `https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(query)}&limit=1`;
  const res = await fetch(url);
  const data = await res.json();
  if (data && data.length > 0) {
    return {
      lat: parseFloat(data[0].lat),
      lon: parseFloat(data[0].lon),
      display_name: data[0].display_name
    };
  }
  return null;
}

function displayRouteInfo(dist, time, steps) {
  const infoBox = document.getElementById('routeInfoBox');
  const distEl = document.getElementById('routeDist');
  const timeEl = document.getElementById('routeTime');
  const stepsEl = document.getElementById('routeInstructions');

  infoBox.classList.remove('d-none');
  distEl.innerText = `Jarak: ${dist}`;
  timeEl.innerText = `Estimasi: ${time}`;

  let stepsHtml = '<div class="d-flex flex-column gap-2 mt-1">';
  steps.forEach((step) => {
    const distBadge = step.dist ? '<div class="text-info fw-semibold" style="font-size:0.68rem;">' + step.dist + '</div>' : '';
    stepsHtml += `
      <div class="d-flex align-items-start gap-2 border-bottom border-secondary pb-1 opacity-90">
        <div class="mt-1" style="min-width:18px;"><i class="fa-solid ${step.icon}"></i></div>
        <div class="flex-grow-1">
          <div>${step.text}</div>
          ${distBadge}
        </div>
      </div>
    `;
  });
  stepsHtml += '</div>';

  stepsEl.innerHTML = stepsHtml;
}