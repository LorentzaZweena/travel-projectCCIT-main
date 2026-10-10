let map;
let currentRouteLayer = null;
let originMarker = null;
let destMarker = null;
let selectedMode = 'driving';
let mapMarkers = {};

const locations = {
  indonesia: { lat: -2.548926, lng: 118.014863, zoom: 5, title: "Indonesia" },
  bali: { 
    lat: -8.409518, lng: 115.188916, zoom: 10, title: "Bali Island", 
    desc: "Pantai tropis, kebudayaan unik & pegunungan eksotis.", 
    img: "https://images.unsplash.com/photo-1537996194471-e657df975ab4?auto=format&fit=crop&w=400&q=80"
  },
  rajaampat: { 
    lat: -0.2333, lng: 130.5167, zoom: 9, title: "Raja Ampat", 
    desc: "Surga menyelam dunia & gugusan pulau karang.", 
    img: "https://images.unsplash.com/photo-1516690561799-46d8f74f9abf?auto=format&fit=crop&w=400&q=80"
  },
  komodo: { 
    lat: -8.5583, lng: 119.4886, zoom: 10, title: "Komodo Island", 
    desc: "Habitat komodo & pantai pasir merah muda (Pink Beach).", 
    img: "https://images.unsplash.com/photo-1588668214407-6ea9a6d8c272?auto=format&fit=crop&w=400&q=80"
  },
  bromo: { 
    lat: -7.9425, lng: 112.9530, zoom: 11, title: "Mount Bromo", 
    desc: "Pemandangan matahari terbit & lautan pasir membentang.", 
    img: "https://images.unsplash.com/photo-1544644181-1484b3fdfc62?auto=format&fit=crop&w=400&q=80"
  },
  laketoba: { 
    lat: 2.6845, lng: 98.8756, zoom: 10, title: "Lake Toba", 
    desc: "Danau vulkanik raksasa dengan Pulau Samosir di tengahnya.", 
    img: "https://images.unsplash.com/photo-1518548419970-58e3b4079ab2?auto=format&fit=crop&w=400&q=80"
  },
  lombok: { 
    lat: -8.6509, lng: 116.3249, zoom: 10, title: "Lombok & Gili", 
    desc: "Pantai pasir putih eksotis & Gunung Rinjani.", 
    img: "https://images.unsplash.com/photo-1570077188670-e3a8d69ac5ff?auto=format&fit=crop&w=400&q=80"
  },
  sumba: { 
    lat: -9.6556, lng: 120.2642, zoom: 10, title: "Sumba Island", 
    desc: "Padang sabana luas, air terjun & kebudayaan lokal.", 
    img: "https://images.unsplash.com/photo-1555400038-63f5ba517a47?auto=format&fit=crop&w=400&q=80"
  }
};

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
  }).setView([-6.2088, 106.8456], 9);

  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '&copy; OpenStreetMap contributors',
    maxZoom: 19
  }).addTo(map);

  Object.keys(locations).forEach(key => {
    if (key === 'indonesia') return;
    const spot = locations[key];

    const popupContent = `
      <div class="card border-0 bg-white overflow-hidden w-100" style="border-radius: 16px;">
        <img src="${spot.img}" class="card-img-top" style="height: 110px; object-fit: cover; width: 100%;" alt="${spot.title}">
        <div class="card-body p-3 text-dark">
          <h6 class="fw-bold mb-1 text-primary" style="font-size: 0.9rem;">${spot.title}</h6>
          <p class="text-secondary mb-2" style="font-size: 0.75rem; line-height: 1.35;">${spot.desc}</p>
          <button onclick="quickRoute('Jakarta', '${spot.title}')" class="btn btn-primary btn-sm w-100 rounded-pill py-1.5 fw-semibold" style="font-size:0.75rem;">
            <i class="fa-solid fa-route me-1"></i> Rute ke Sini
          </button>
        </div>
      </div>
    `;

    const marker = L.marker([spot.lat, spot.lng], { icon: createIslandIcon(spot.title) })
      .addTo(map)
      .bindPopup(popupContent);

    mapMarkers[key] = marker;
  });

  document.getElementById('originInput').addEventListener('keypress', (e) => {
    if (e.key === 'Enter') searchAndDrawRoute();
  });
  document.getElementById('destInput').addEventListener('keypress', (e) => {
    if (e.key === 'Enter') searchAndDrawRoute();
  });
});

function flyToLocation(locKey, btnElement = null) {
  if (!locations[locKey] || !map) return;

  const loc = locations[locKey];
  map.flyTo([loc.lat, loc.lng], loc.zoom, {
    animate: true,
    duration: 1.5
  });

  if (mapMarkers[locKey]) {
    setTimeout(() => {
      mapMarkers[locKey].openPopup();
    }, 1500);
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

// Render Info Rute Tanpa Bug Teks
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