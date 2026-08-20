@extends('layouts.app')

@section('title', $zone['name'] . ' — RadiaTrack')

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
        {!! $icons[$zone['icon']] !!}
      </svg>
    </div>
    <h1>{{ $zone['name'] }}</h1>
    <button class="add-sensor" id="addSensorBtn">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4"><path d="M12 5v14M5 12h14"/></svg>
      Tambah Sensor
    </button>
  </div>

  <section class="panel">
    <div class="panel-head">
      <h2>Data Sensor</h2>
      <div class="live-dot">LIVE</div>
    </div>
    <div class="table-scroll">
      <table>
        <thead>
          <tr>
            <th>No</th>
            <th>Name</th>
            <th>Time</th>
            <th>Count Rate</th>
            <th>AVG Count</th>
            <th>Dose Rate</th>
            <th>AVG Dose</th>
            <th>Total</th>
            <th>Status</th>
          </tr>
        </thead>
        <tbody id="zoneTableBody">
          <!-- Dynamically filled by JavaScript -->
        </tbody>
      </table>
    </div>
  </section>

  <section class="panel">
    <div class="panel-head">
      <h2>Grafik Sensor — {{ $zone['name'] }}</h2>
    </div>
    <div class="chart-wrap">
      <canvas id="zoneChartCanvas"></canvas>
    </div>
  </section>

  <footer class="hint">Data bersifat simulasi untuk keperluan desain ulang dashboard.</footer>
@endsection

@section('scripts')
  <script>
    const zoneId = {{ $zoneId }};
    const zoneMeta = @json($zone);
    let zoneChart = null;

    function seedZoneRows(){
      const nodeLetter = String.fromCharCode(65 + zoneId);
      const rows = [];
      for(let i=0; i<6; i++){
        const jitter = (v,f) => +(v*(1+(Math.random()-0.5)*f));
        const cr = jitter(zoneMeta.rate, 0.15);
        const dr = jitter(zoneMeta.doseRate, 0.18);
        rows.push({
          no: i+1,
          name: `Node ${nodeLetter}${i+1}`,
          time: new Date(Date.now() - i*60000).toLocaleTimeString('id-ID',{hour:'2-digit',minute:'2-digit',second:'2-digit'}),
          countRate: cr,
          avgCount: cr*(0.95+Math.random()*0.1),
          doseRate: dr,
          avgDose: dr*(0.95+Math.random()*0.1),
          total: jitter(zoneMeta.total, 0.06)
        });
      }
      return rows;
    }

    let rows = seedZoneRows();

    function renderZoneTable(){
      const body = document.getElementById('zoneTableBody');
      if(!body) return;
      body.innerHTML = rows.map(r => {
        const elevated = r.doseRate > zoneMeta.doseRate * 1.25;
        return `
        <tr>
          <td>${r.no}</td>
          <td>${r.name}</td>
          <td>${r.time}</td>
          <td class="num">${r.countRate.toFixed(0)}</td>
          <td class="num">${r.avgCount.toFixed(0)}</td>
          <td class="num">${r.doseRate.toFixed(2)}</td>
          <td class="num">${r.avgDose.toFixed(2)}</td>
          <td class="num">${r.total.toFixed(2)}</td>
          <td><span class="status-chip ${elevated ? 'elevated' : 'normal'}">${elevated ? 'Elevated' : 'Normal'}</span></td>
        </tr>`;
      }).join('');
    }

    function renderZoneChart(){
      const chartRows = [...rows].reverse();
      const ctx = document.getElementById('zoneChartCanvas').getContext('2d');
      if(zoneChart) zoneChart.destroy();
      zoneChart = new Chart(ctx, {
        type: 'line',
        data: {
          labels: chartRows.map(r => r.time),
          datasets: [
            {
              label: 'Count Rate (cpm)',
              data: chartRows.map(r => +r.countRate.toFixed(1)),
              borderColor: '#ffc53d',
              backgroundColor: 'rgba(255,197,61,0.08)',
              borderWidth: 2, pointRadius: 2, tension: 0.35, yAxisID: 'y'
            },
            {
              label: 'Dose Rate (µSv/h)',
              data: chartRows.map(r => +r.doseRate.toFixed(2)),
              borderColor: '#38d996',
              backgroundColor: 'rgba(56,217,150,0.08)',
              borderWidth: 2, pointRadius: 2, tension: 0.35, yAxisID: 'y1'
            }
          ]
        },
        options: {
          responsive: true, maintainAspectRatio: false,
          plugins: { legend: { display: true, labels: { color: '#7c8798', font:{family:"'Inter'", size:11} } } },
          scales: {
            x: { grid: { color: '#1c2531' }, ticks: { color: '#7c8798', font:{family:"'IBM Plex Mono'", size:10} } },
            y: { position:'left', grid: { color: '#1c2531' }, ticks: { color: '#ffc53d', font:{family:"'IBM Plex Mono'", size:10} } },
            y1: { position:'right', grid: { display:false }, ticks: { color: '#38d996', font:{family:"'IBM Plex Mono'", size:10} } }
          }
        }
      });
    }

    function tick(){
      zoneMeta.rate = Math.max(20, +(zoneMeta.rate + (Math.random()-0.5)*10).toFixed(0));
      zoneMeta.doseRate = Math.max(0.05, +(zoneMeta.doseRate + (Math.random()-0.5)*0.02).toFixed(2));
      zoneMeta.total = +(zoneMeta.total + zoneMeta.doseRate/3600*5).toFixed(2);

      const nodeLetter = String.fromCharCode(65 + zoneId);
      const jitter = (v,f) => +(v*(1+(Math.random()-0.5)*f));
      const cr = jitter(zoneMeta.rate, 0.15);
      const dr = jitter(zoneMeta.doseRate, 0.18);
      
      rows.unshift({
        no: rows[0].no + 1,
        name: `Node ${nodeLetter}${Math.ceil(Math.random()*3)}`,
        time: new Date().toLocaleTimeString('id-ID',{hour:'2-digit',minute:'2-digit',second:'2-digit'}),
        countRate: cr,
        avgCount: cr*(0.95+Math.random()*0.1),
        doseRate: dr,
        avgDose: dr*(0.95+Math.random()*0.1),
        total: jitter(zoneMeta.total, 0.06)
      });
      rows = rows.slice(0, 6);

      renderZoneTable();
      renderZoneChart();
    }

    document.getElementById('addSensorBtn').addEventListener('click', () => {
      const nodeLetter = String.fromCharCode(65 + zoneId);
      const nextNo = rows.length > 0 ? Math.max(...rows.map(r => r.no)) + 1 : 1;
      
      const jitter = (v,f) => +(v*(1+(Math.random()-0.5)*f));
      const cr = jitter(zoneMeta.rate, 0.15);
      const dr = jitter(zoneMeta.doseRate, 0.18);
      
      const newNode = {
        no: nextNo,
        name: `Node ${nodeLetter}${nextNo}`,
        time: new Date().toLocaleTimeString('id-ID',{hour:'2-digit',minute:'2-digit',second:'2-digit'}),
        countRate: cr,
        avgCount: cr*(0.95+Math.random()*0.1),
        doseRate: dr,
        avgDose: dr*(0.95+Math.random()*0.1),
        total: jitter(zoneMeta.total, 0.06)
      };
      
      rows.unshift(newNode);
      rows = rows.slice(0, 6);
      
      renderZoneTable();
      renderZoneChart();
    });

    renderZoneTable();
    renderZoneChart();
    setInterval(tick, 5000);
  </script>
@endsection
