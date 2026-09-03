@extends('layouts.app')

@section('title', $detector['name'] . ' — RadiaTrack')

@section('content')
  @php
    $icons = [
      'box' => '<path d="M21 8l-9-5-9 5 9 5 9-5z"/><path d="M3 8v8l9 5 9-5V8"/><path d="M12 13v8"/>',
      'server' => '<rect x="3" y="4" width="18" height="7" rx="1.5"/><rect x="3" y="13" width="18" height="7" rx="1.5"/><circle cx="7" cy="7.5" r="1"/><circle cx="7" cy="16.5" r="1"/>',
      'drop' => '<path d="M12 3s6 6.5 6 11a6 6 0 0 1-12 0c0-4.5 6-11 6-11z"/>'
    ];
  @endphp

  <div class="topbar">
    <a href="{{ route('dashboard') }}" class="back-link" id="backToDash">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M15 18l-6-6 6-6"/></svg>
      Dashboard
    </a>
  </div>
  
  <div class="topbar" style="padding-top:6px;">
    <div class="zone-icon">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
        {!! $icons[$detector['icon']] !!}
      </svg>
    </div>
    <h1>{{ $detector['name'] }}</h1>
  </div>

  <!-- Realtime Live Display -->
  <section class="cards" style="padding: 16px 32px 0; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));">
    <div class="card" style="cursor: default; pointer-events: none; border-color: var(--border);">
      <div class="card-head">
        <h3>LIVE RATE</h3>
        <div class="pulse-dot" style="margin-left: auto;"><span class="ring"></span><span class="core"></span></div>
      </div>
      <div class="metrics" style="grid-template-columns: 1fr;">
        <div class="metric"><div class="mval" id="liveRate" style="color: var(--accent);">- cpm</div></div>
      </div>
    </div>
    <div class="card" style="cursor: default; pointer-events: none; border-color: var(--border);">
      <div class="card-head">
        <h3>LIVE DOSE RATE</h3>
        <div class="pulse-dot" style="margin-left: auto;"><span class="ring"></span><span class="core"></span></div>
      </div>
      <div class="metrics" style="grid-template-columns: 1fr;">
        <div class="metric"><div class="mval" id="liveDose" style="color: var(--safe);">- µSv/h</div></div>
      </div>
    </div>
    <div class="card" style="cursor: default; pointer-events: none; border-color: var(--border);">
      <div class="card-head">
        <h3>LIVE TOTAL DOSE</h3>
        <div class="pulse-dot" style="margin-left: auto;"><span class="ring"></span><span class="core"></span></div>
      </div>
      <div class="metrics" style="grid-template-columns: 1fr;">
        <div class="metric"><div class="mval" id="liveTotal" style="color: var(--warn);">- mSv</div></div>
      </div>
    </div>
  </section>

  <!-- History Chart -->
  <section class="panel" style="margin-top: 24px;">
    <div class="panel-head">
      <h2>Grafik Riwayat Sensor (Database Lokal)</h2>
    </div>
    <div class="chart-wrap">
      <canvas id="zoneChartCanvas"></canvas>
    </div>
  </section>

  <!-- History Table -->
  <section class="panel">
    <div class="panel-head">
      <h2>Data Log Database 'magang' (Sync 40s)</h2>
      <div class="live-dot" style="color: var(--muted);">DB LOGS</div>
    </div>
    <div class="table-scroll">
      <table>
        <thead>
          <tr>
            <th>No</th>
            <th>Waktu Lokal</th>
            <th>Rate (cpm)</th>
            <th>Dose Rate (µSv/h)</th>
            <th>Total (mSv)</th>
            <th>Status</th>
            <th>Waktu Firebase</th>
          </tr>
        </thead>
        <tbody id="zoneTableBody">
          <!-- Populated dynamically and reversed (newest on top) -->
        </tbody>
      </table>
    </div>
  </section>

  <footer class="hint">Grafik dan tabel di atas memuat data riil yang disinkronkan ke database lokal 'magang' phpMyAdmin.</footer>
@endsection

@section('scripts')
  <!-- Firebase Compatibility SDKs -->
  <script src="https://www.gstatic.com/firebasejs/10.8.0/firebase-app-compat.js"></script>
  <script src="https://www.gstatic.com/firebasejs/10.8.0/firebase-database-compat.js"></script>

  <script>
    // Initialize Firebase
    const firebaseConfig = {
      apiKey: "{{ env('FIREBASE_API_KEY') }}",
      authDomain: "{{ env('FIREBASE_AUTH_DOMAIN') }}",
      databaseURL: "{{ env('FIREBASE_DATABASE_URL') }}",
      projectId: "{{ env('FIREBASE_PROJECT_ID') }}",
      storageBucket: "{{ env('FIREBASE_STORAGE_BUCKET') }}",
      messagingSenderId: "{{ env('FIREBASE_MESSAGING_SENDER_ID') }}",
      appId: "{{ env('FIREBASE_APP_ID') }}",
      measurementId: "{{ env('FIREBASE_MEASUREMENT_ID') }}"
    };

    firebase.initializeApp(firebaseConfig);
    const db = firebase.database();

    const detectorId = "{{ $detectorId }}";
    let readings = @json($readings);
    let readingsList = Array.isArray(readings) ? readings : Object.values(readings);
    let zoneChart = null;

    // Listen to Firebase Realtime Database for Live metrics
    const detectorRef = db.ref('detectors/' + detectorId);
    detectorRef.on('value', (snapshot) => {
      const val = snapshot.val();
      if (!val) return;

      const rateEl = document.getElementById('liveRate');
      const doseEl = document.getElementById('liveDose');
      const totalEl = document.getElementById('liveTotal');

      const rate = (typeof val.rate === 'number') ? val.rate : 0.0;
      const doseRate = (typeof val.dose_rate === 'number') ? val.dose_rate : 0.0;
      const total = (typeof val.total === 'number') ? val.total : 0.0;

      if (rateEl) rateEl.textContent = rate.toFixed(1) + ' cpm';
      if (doseEl) doseEl.textContent = doseRate.toFixed(2) + ' µSv/h';
      if (totalEl) totalEl.textContent = total.toFixed(2) + ' mSv';

      // Push new reading to the chart and table in real-time
      const now = new Date();
      const newReading = {
        id: Date.now(),
        created_at: now.toISOString(),
        rate: rate,
        dose_rate: doseRate,
        total: total,
        status: (doseRate > 0.35) ? 'warn' : 'safe',
        firebase_last_updated: val.last_updated || Date.now()
      };

      // Avoid duplication if the callback triggers on the same timestamp
      const lastReading = readingsList[readingsList.length - 1];
      const lastTime = lastReading ? new Date(lastReading.created_at || lastReading.updated_at) : null;
      if (!lastTime || Math.abs(now - lastTime) > 800) {
        readingsList.push(newReading);
        if (readingsList.length > 20) {
          readingsList.shift();
        }
        renderTableAndChart(readingsList);
      }
    });

    // Populate table and render chart initially
    function renderTableAndChart(data) {
      // 1. Table Render (Newest at the top)
      const tbody = document.getElementById('zoneTableBody');
      if (tbody) {
        if (data.length === 0) {
          tbody.innerHTML = `<tr><td colspan="7" style="text-align: center; color: var(--muted-2);">Belum ada data disinkronkan ke database lokal.</td></tr>`;
        } else {
          // Copy and reverse for showing newest first in the table
          const sorted = [...data].reverse();
          tbody.innerHTML = sorted.map((r, idx) => {
            const localTime = new Date(r.created_at).toLocaleString('id-ID');
            const firebaseTime = r.firebase_last_updated ? new Date(Number(r.firebase_last_updated)).toLocaleString('id-ID') : '-';
            const elevated = r.status === 'warn';
            return `
              <tr>
                <td>${idx + 1}</td>
                <td>${localTime}</td>
                <td class="num">${Number(r.rate).toFixed(1)}</td>
                <td class="num">${Number(r.dose_rate).toFixed(2)}</td>
                <td class="num">${Number(r.total).toFixed(2)}</td>
                <td>
                  <span class="status-chip ${elevated ? 'elevated' : 'normal'}">
                    ${elevated ? 'Elevated' : 'Normal'}
                  </span>
                </td>
                <td>${firebaseTime}</td>
              </tr>
            `;
          }).join('');
        }
      }

      // 2. Chart Render (Chronological order)
      if (zoneChart) {
        zoneChart.data.labels = data.map(r => {
          const date = new Date(r.created_at);
          return date.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
        });
        zoneChart.data.datasets[0].data = data.map(r => Number(r.rate));
        zoneChart.data.datasets[1].data = data.map(r => Number(r.dose_rate));
        zoneChart.update();
      }
    }

    function isLightMode() {
      return document.documentElement.classList.contains('light-mode');
    }

    // Initialize Chart.js
    function initChart() {
      const ctx = document.getElementById('zoneChartCanvas').getContext('2d');
      const isLight = isLightMode();

      zoneChart = new Chart(ctx, {
        type: 'line',
        data: {
          labels: [],
          datasets: [
            {
              label: 'Count Rate (cpm)',
              data: [],
              borderColor: isLight ? '#d97706' : '#ffc53d',
              backgroundColor: isLight ? 'rgba(217,119,6,0.08)' : 'rgba(255,197,61,0.04)',
              borderWidth: 2.2,
              pointRadius: 3,
              pointBackgroundColor: isLight ? '#d97706' : '#ffc53d',
              tension: 0.35,
              yAxisID: 'y'
            },
            {
              label: 'Dose Rate (µSv/h)',
              data: [],
              borderColor: isLight ? '#059669' : '#38d996',
              backgroundColor: isLight ? 'rgba(5,150,105,0.08)' : 'rgba(56,217,150,0.04)',
              borderWidth: 2.2,
              pointRadius: 3,
              pointBackgroundColor: isLight ? '#059669' : '#38d996',
              tension: 0.35,
              yAxisID: 'y1'
            }
          ]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          plugins: {
            legend: {
              display: true,
              labels: {
                color: isLight ? '#475569' : '#94a3b8',
                font: { family: "'Inter'", size: 12 }
              }
            }
          },
          scales: {
            x: {
              grid: { color: isLight ? 'rgba(0,0,0,0.06)' : 'rgba(255,255,255,0.06)' },
              ticks: { color: isLight ? '#64748b' : '#94a3b8', font: { family: "'IBM Plex Mono'", size: 10 } }
            },
            y: {
              position: 'left',
              grid: { color: isLight ? 'rgba(0,0,0,0.06)' : 'rgba(255,255,255,0.06)' },
              ticks: { color: isLight ? '#d97706' : '#ffc53d', font: { family: "'IBM Plex Mono'", size: 10 } }
            },
            y1: {
              position: 'right',
              grid: { display: false },
              ticks: { color: isLight ? '#059669' : '#38d996', font: { family: "'IBM Plex Mono'", size: 10 } }
            }
          }
        }
      });
    }

    window.addEventListener('themeChanged', (e) => {
      if (!zoneChart) return;
      const isLight = e.detail.isLight;
      zoneChart.options.scales.x.grid.color = isLight ? 'rgba(0,0,0,0.06)' : 'rgba(255,255,255,0.06)';
      zoneChart.options.scales.x.ticks.color = isLight ? '#64748b' : '#94a3b8';
      zoneChart.options.scales.y.grid.color = isLight ? 'rgba(0,0,0,0.06)' : 'rgba(255,255,255,0.06)';
      zoneChart.options.scales.y.ticks.color = isLight ? '#d97706' : '#ffc53d';
      zoneChart.options.scales.y1.ticks.color = isLight ? '#059669' : '#38d996';
      zoneChart.options.plugins.legend.labels.color = isLight ? '#475569' : '#94a3b8';
      zoneChart.update();
    });

    // Initialize layout
    initChart();
    renderTableAndChart(readingsList);

    // Poll database for new records every 10 seconds
    let lastFetchedId = readingsList.length > 0 ? readingsList[readingsList.length - 1].id : 0;
    
    function checkForUpdates() {
      fetch("/detector/" + detectorId + "/history")
        .then(res => res.json())
        .then(data => {
          if (data && data.length > 0) {
            const latest = data[data.length - 1];
            if (latest.id !== lastFetchedId) {
              lastFetchedId = latest.id;
              readingsList = data;
              renderTableAndChart(readingsList);
            }
          }
        })
        .catch(err => console.error("Error polling history:", err));
    }

    setInterval(checkForUpdates, 10000);
  </script>
@endsection
