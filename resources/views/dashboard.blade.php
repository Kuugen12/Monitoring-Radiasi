@extends('layouts.app')

@section('title', 'Dashboard — RadiaTrack')

@section('content')
  <div class="topbar" style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;">
    <div style="display:flex;align-items:center;gap:10px;">
      <svg class="subicon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="2" y="3" width="20" height="14" rx="2"/><path d="M8 21h8M12 17v4"/></svg>
      <h1>Pantauan Radiasi</h1>
    </div>
    <div class="dash-sync-badge" style="display:flex;align-items:center;gap:8px;background:var(--panel-2);border:1px solid var(--border);padding:6px 14px;border-radius:20px;font-size:12px;">
      <span style="display:inline-block;width:7px;height:7px;border-radius:50%;background:#34D399;box-shadow:0 0 8px #34D399;"></span>
      <span style="color:var(--muted);">Data Terakhir:</span>
      <strong id="dashLastSyncTime" style="color:var(--text);font-family:var(--mono);">Memuat data...</strong>
    </div>
  </div>
  <p class="page-sub">Pilih detektor untuk melihat grafik riwayat dan detail sensor.</p>

  @if(session('success'))
    <div class="form-success" style="margin: 16px 32px 0;">
      {{ session('success') }}
    </div>
  @endif
  @if($errors->any())
    <div class="form-err" style="margin: 16px 32px 0;">
      {{ $errors->first() }}
    </div>
  @endif

  <section class="cards" id="cardsRoot">
    @foreach($detectors as $id => $d)
      @php
        $icons = [
          'box' => '<path d="M21 8l-9-5-9 5 9 5 9-5z"/><path d="M3 8v8l9 5 9-5V8"/><path d="M12 13v8"/>',
          'server' => '<rect x="3" y="4" width="18" height="7" rx="1.5"/><rect x="3" y="13" width="18" height="7" rx="1.5"/><circle cx="7" cy="7.5" r="1"/><circle cx="7" cy="16.5" r="1"/>',
          'drop' => '<path d="M12 3s6 6.5 6 11a6 6 0 0 1-12 0c0-4.5 6-11 6-11z"/>'
        ];
      @endphp
      <a href="{{ route('zone', $id) }}" class="card" id="card-{{ $id }}">
        <div class="card-head">
          <div class="zone-icon">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
              {!! $icons[$d['icon']] !!}
            </svg>
          </div>
          <h3>{{ $d['name'] }}</h3>
          <div class="pulse-dot" id="dot-{{ $id }}"><span class="ring"></span><span class="core"></span></div>
          <svg class="chev" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 18l6-6-6-6"/></svg>
        </div>
        <div class="metrics">
          <div class="metric">
            <div class="mlabel">RATE</div>
            <div class="mval"><span class="val-rate" id="rate-{{ $id }}">-</span><span class="unit">cpm</span></div>
          </div>
          <div class="metric">
            <div class="mlabel">DOSE RATE</div>
            <div class="mval"><span class="val-doseRate" id="dose-{{ $id }}">-</span><span class="unit">µSv/h</span></div>
          </div>
          <div class="metric">
            <div class="mlabel">TOTAL</div>
            <div class="mval"><span class="val-total" id="total-{{ $id }}">-</span><span class="unit">mSv</span></div>
          </div>
        </div>
        <div class="spark">
          <svg viewBox="0 0 300 32" preserveAspectRatio="none">
            <path class="spark-path-1" d="" fill="none" stroke="#ffc53d" stroke-width="1.6"/>
            <path class="spark-path-2" d="" fill="none" stroke="#4d5768" stroke-width="1.2" stroke-dasharray="3 3"/>
          </svg>
        </div>
      </a>
    @endforeach
  </section>

  <footer class="hint">Data terhubung langsung secara realtime dengan Firebase Realtime Database.</footer>
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
    
    // Sparkline history records
    const historyData = {
      detektor1: [],
      detektor2: [],
      detektor3: [],
      detektor4: []
    };

    // Reference to detectors node
    const detectorsRef = db.ref('detectors');
    
    // Listen for changes in Firebase in real-time
    detectorsRef.on('value', (snapshot) => {
      const data = snapshot.val();
      if (!data) return;

      const now = new Date();
      const timeStr = now.toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' }) + ' ' + 
                      now.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit', second: '2-digit' }) + ' WIB';
      const syncEl = document.getElementById('dashLastSyncTime');
      if (syncEl) syncEl.textContent = timeStr;

      Object.keys(data).forEach((id) => {
        const detData = data[id];
        if (!detData) return;

        // Get elements
        const rateEl = document.getElementById(`rate-${id}`);
        const doseEl = document.getElementById(`dose-${id}`);
        const totalEl = document.getElementById(`total-${id}`);
        const dotEl = document.getElementById(`dot-${id}`);

        // Extract values
        const rate = (typeof detData.rate === 'number') ? detData.rate.toFixed(1) : '-';
        const doseRate = (typeof detData.dose_rate === 'number') ? detData.dose_rate.toFixed(2) : '-';
        const total = (typeof detData.total === 'number') ? detData.total.toFixed(2) : '-';
        const status = (detData.dose_rate > 0.35) ? 'warn' : 'safe';

        // Update HTML
        if (rateEl) rateEl.textContent = rate;
        if (doseEl) doseEl.textContent = doseRate;
        if (totalEl) totalEl.textContent = total;

        if (dotEl) {
          if (status === 'warn') {
            dotEl.classList.add('warn');
          } else {
            dotEl.classList.remove('warn');
          }
        }

        // Push to spark history
        if (typeof detData.rate === 'number') {
          historyData[id].push(detData.rate);
          if (historyData[id].length > 20) {
            historyData[id].shift();
          }
        }
      });

      updateAllSparks();
    });

    // Generate SVG path for sparklines
    function sparkPath(dataArr, w, h) {
      if (dataArr.length === 0) return '';
      const min = Math.min(...dataArr);
      const max = Math.max(...dataArr);
      const range = max - min || 1;
      
      let pts = [];
      for (let i = 0; i < dataArr.length; i++) {
        const val = dataArr[i];
        const normalized = (val - min) / range;
        const x = (i / (dataArr.length - 1 || 1)) * w;
        const y = h - (normalized * 0.7 + 0.15) * h;
        pts.push([x, y]);
      }
      return pts.map((p, i) => (i === 0 ? 'M' : 'L') + p[0].toFixed(1) + ',' + p[1].toFixed(1)).join(' ');
    }

    function updateAllSparks() {
      Object.keys(historyData).forEach((id) => {
        const card = document.getElementById(`card-${id}`);
        if (card) {
          const path1 = card.querySelector('.spark-path-1');
          const path2 = card.querySelector('.spark-path-2');
          const data = historyData[id];
          
          if (data.length > 1) {
            path1.setAttribute('d', sparkPath(data, 300, 32));
            
            // Draw average line
            const avg = data.reduce((a, b) => a + b, 0) / data.length;
            const avgData = new Array(data.length).fill(avg);
            path2.setAttribute('d', sparkPath(avgData, 300, 32));
          }
        }
      });
    }
  </script>
@endsection
