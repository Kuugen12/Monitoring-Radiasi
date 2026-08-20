@extends('layouts.app')

@section('title', 'Dashboard — RadiaTrack')

@section('content')
  <div class="topbar">
    <svg class="subicon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="2" y="3" width="20" height="14" rx="2"/><path d="M8 21h8M12 17v4"/></svg>
    <h1>Pantauan Radiasi</h1>
    <button class="add-sensor" id="btnOpenAddZoneModal">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4"><path d="M12 5v14M5 12h14"/></svg>
      Tambah Zona
    </button>
  </div>
  <p class="page-sub">Pilih zona untuk melihat detail sensor.</p>

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
    @foreach($zones as $idx => $z)
      @php
        $dotClass = $z['status'] === 'warn' ? 'warn' : '';
        $icons = [
          'box' => '<path d="M21 8l-9-5-9 5 9 5 9-5z"/><path d="M3 8v8l9 5 9-5V8"/><path d="M12 13v8"/>',
          'server' => '<rect x="3" y="4" width="18" height="7" rx="1.5"/><rect x="3" y="13" width="18" height="7" rx="1.5"/><circle cx="7" cy="7.5" r="1"/><circle cx="7" cy="16.5" r="1"/>',
          'drop' => '<path d="M12 3s6 6.5 6 11a6 6 0 0 1-12 0c0-4.5 6-11 6-11z"/>'
        ];
      @endphp
      <a href="{{ route('zone', $idx) }}" class="card" data-idx="{{ $idx }}" id="card-{{ $idx }}">
        <div class="card-head">
          <div class="zone-icon">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
              {!! $icons[$z['icon']] !!}
            </svg>
          </div>
          <h3>{{ $z['name'] }}</h3>
          <div class="pulse-dot {{ $dotClass }}"><span class="ring"></span><span class="core"></span></div>
          <svg class="chev" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 18l6-6-6-6"/></svg>
        </div>
        <div class="metrics">
          <div class="metric"><div class="mlabel">RATE</div><div class="mval"><span class="val-rate">{{ number_format($z['rate'], 0) }}</span><span class="unit">cpm</span></div></div>
          <div class="metric"><div class="mlabel">DOSE RATE</div><div class="mval"><span class="val-doseRate">{{ number_format($z['doseRate'], 2) }}</span><span class="unit">µSv/h</span></div></div>
          <div class="metric"><div class="mlabel">TOTAL</div><div class="mval"><span class="val-total">{{ number_format($z['total'], 2) }}</span><span class="unit">mSv</span></div></div>
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

  <footer class="hint">Data bersifat simulasi untuk keperluan desain ulang dashboard.</footer>

  <!-- Modal Tambah Zona Baru -->
  <div class="modal-overlay" id="addZoneModal">
    <div class="modal-container">
      <h2>Tambah Zona Baru</h2>
      <form action="{{ route('zone.store') }}" method="POST" class="modal-form">
        @csrf
        <div class="field">
          <label for="zone_name">Nama Zona</label>
          <input type="text" name="name" id="zone_name" placeholder="cth. Zona D — Ruang Server" required autocomplete="off">
        </div>
        <div class="field">
          <label for="zone_icon">Ikon</label>
          <select name="icon" id="zone_icon" required>
            <option value="box">Kotak (Gudang)</option>
            <option value="server">Server (R. Kontrol)</option>
            <option value="drop">Tetes Air (Laboratorium)</option>
          </select>
        </div>
        <div class="modal-actions">
          <button type="button" class="btn-cancel" id="btnCancelAddZone">Batal</button>
          <button type="submit" class="btn-submit">Tambah Zona</button>
        </div>
      </form>
    </div>
  </div>
@endsection

@section('scripts')
  <script>
    const zones = @json($zones);

    function sparkPath(seed, w, h){
      let pts = []; let v = 0.5;
      for(let i=0;i<20;i++){
        v += (Math.sin(seed + i*0.7) * 0.06) + (Math.random()-0.5)*0.03;
        v = Math.max(0.15, Math.min(0.85, v));
        pts.push([ (i/19)*w, h - v*h ]);
      }
      return pts.map((p,i)=> (i===0?'M':'L') + p[0].toFixed(1) + ',' + p[1].toFixed(1)).join(' ');
    }

    function updateSparks() {
      zones.forEach((z, idx) => {
        const card = document.getElementById(`card-${idx}`);
        if(card) {
          const path1 = card.querySelector('.spark-path-1');
          const path2 = card.querySelector('.spark-path-2');
          path1.setAttribute('d', sparkPath(idx*3 + Date.now()/100000, 300, 32));
          path2.setAttribute('d', sparkPath(idx*3 + 9 + Date.now()/100000, 300, 32));
        }
      });
    }

    function tick(){
      zones.forEach((z, idx) => {
        z.rate = Math.max(20, +(z.rate + (Math.random()-0.5)*10).toFixed(0));
        z.doseRate = Math.max(0.05, +(z.doseRate + (Math.random()-0.5)*0.02).toFixed(2));
        z.total = +(z.total + z.doseRate/3600*5).toFixed(2);
        z.status = z.doseRate > 0.35 ? 'warn' : 'safe';

        const card = document.getElementById(`card-${idx}`);
        if(card) {
          card.querySelector('.val-rate').textContent = z.rate;
          card.querySelector('.val-doseRate').textContent = z.doseRate.toFixed(2);
          card.querySelector('.val-total').textContent = z.total.toFixed(2);
          
          const dot = card.querySelector('.pulse-dot');
          if (z.status === 'warn') {
            dot.classList.add('warn');
          } else {
            dot.classList.remove('warn');
          }
        }
      });
      updateSparks();
    }

    updateSparks();
    setInterval(tick, 5000);

    // Modal Interaction
    const modal = document.getElementById('addZoneModal');
    const btnOpen = document.getElementById('btnOpenAddZoneModal');
    const btnCancel = document.getElementById('btnCancelAddZone');

    if (btnOpen && modal) {
      btnOpen.addEventListener('click', () => {
        modal.classList.add('show');
        document.getElementById('zone_name').value = '';
        document.getElementById('zone_name').focus();
      });
    }

    if (btnCancel && modal) {
      btnCancel.addEventListener('click', () => {
        modal.classList.remove('show');
      });
    }

    // Close modal when clicking outside the container
    if (modal) {
      modal.addEventListener('click', (e) => {
        if (e.target === modal) {
          modal.classList.remove('show');
        }
      });
    }
  </script>
@endsection
