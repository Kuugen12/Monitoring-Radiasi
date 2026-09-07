@extends('layouts.app')

@section('title', 'RADIOSCAN MATRIX v2.0 — 72-Ch Radiation Array Detector')

@section('styles')
  <link rel="stylesheet" href="{{ asset('css/matrix.css') }}">
@endsection

@section('content')
<div class="matrix-layout">

  <!-- ========================================================================
       1. TOP SCIENTIFIC HUD HEADER
       ======================================================================== -->
  <header class="matrix-header">
    <div class="matrix-brand">
      <div class="nuclear-badge" title="72-Channel Radiation Detector Array">
        <svg viewBox="0 0 24 24" fill="currentColor">
          <circle cx="12" cy="12" r="3"/>
          <path d="M12 2a10 10 0 0 0-4.9 1.3l2.5 4.3A5 5 0 0 1 12 7V2zM16.9 3.3A10 10 0 0 0 12 2v5a5 5 0 0 1 2.5.7l2.4-4.4zM2.3 14.1a10 10 0 0 0 2.6 4.1l3.7-3.3A5 5 0 0 1 7 12H2c0 .7.1 1.4.3 2.1zM2.3 9.9H7a5 5 0 0 1 1.6-2.9L4.9 3.7A10 10 0 0 0 2.3 9.9zM21.7 9.9A10 10 0 0 0 19.1 3.7l-3.7 3.3A5 5 0 0 1 17 12h5c0-.7-.1-1.4-.3-2.1zM15.4 14.9l3.7 3.3a10 10 0 0 0 2.6-4.1H17a5 5 0 0 1-1.6 2.9zM7.1 20.7A10 10 0 0 0 12 22v-5a5 5 0 0 1-2.5-.7l-2.4 4.4zM16.9 20.7l-2.5-4.4A5 5 0 0 1 12 17v5a10 10 0 0 0 4.9-1.3z"/>
        </svg>
      </div>
      <div class="matrix-title-group">
        <h1>RADIOSCAN <span class="brand-accent">MATRIX</span></h1>
      </div>
    </div>

    <!-- HUD Status Chips & Modules -->
    <div class="header-hud-bar">
      <!-- Objek Target Chip & Dynamic Switcher (Sinkron TiDB Cloud) -->
      <div class="hud-chip object-chip" id="hudObjectChip" title="Pilih objek scan dari TiDB Cloud / Raspberry Pi 5">
        <svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.2" style="color:#A78BFA;flex-shrink:0;">
          <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/>
          <polyline points="3.27 6.96 12 12.01 20.73 6.96"/>
          <line x1="12" y1="22.08" x2="12" y2="12"/>
        </svg>
        <span style="font-size:10.5px;font-weight:700;letter-spacing:0.4px;">OBJEK:</span>
        <select id="selectActiveObject" class="hud-object-select" onchange="onObjectSelectChange(this.value)" title="Pilih objek target untuk melihat hasil scan di TiDB Cloud">
          <option value="ALL">📦 Semua Objek (Terbaru)</option>
        </select>
        <button type="button" class="btn-refresh-objects" id="btnRefreshObjects" onclick="refreshAvailableObjects(true)" title="Sinkronkan daftar objek dari TiDB Cloud">
          <svg viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2.5">
            <polyline points="23 4 23 10 17 10"></polyline>
            <polyline points="1 20 1 14 7 14"></polyline>
            <path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"></path>
          </svg>
        </button>
      </div>
      <!-- Timestamp & Last Sync Chip -->
      <div class="hud-chip sync-chip" id="hudSyncChip" title="Waktu rekaman data terakhir dari TiDB Cloud / Firebase">
        <svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.2" style="color:#F59E0B;flex-shrink:0;">
          <circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>
        </svg>
        <span>LAST DATA:</span>
        <strong id="headerLastSyncTime" style="color:#60A5FA;font-family:var(--font-mono);letter-spacing:0.3px;">Memuat...</strong>
        <span class="badge-db-live" id="headerDbSourceBadge" style="background:rgba(16,185,129,0.15);color:#34D399;font-size:9.5px;padding:2px 6px;border-radius:4px;font-weight:700;letter-spacing:0.3px;border:1px solid rgba(16,185,129,0.3);">TiDB CLOUD</span>
      </div>

      <div class="hud-chip live-chip" id="liveStatusBadge">
        <span class="live-indicator-dot" id="livePulseDot"></span>
        <span id="liveStatusText">1.0 Hz</span>
      </div>

      <div class="module-status-cluster">
        <div class="module-chip xs1 online" id="pillXS1" title="Xiao Seeed 1 (D01–D24)">
          <span class="mod-dot"></span>
          <span class="mod-name">XS1</span>
        </div>
        <div class="module-chip xs2 online" id="pillXS2" title="Xiao Seeed 2 (D25–D48)">
          <span class="mod-dot"></span>
          <span class="mod-name">XS2</span>
        </div>
        <div class="module-chip xs3 online" id="pillXS3" title="Xiao Seeed 3 (D49–D72)">
          <span class="mod-dot"></span>
          <span class="mod-name">XS3</span>
        </div>
      </div>

      <div class="hud-chip">
        <span>PORT:</span> <strong id="headerComPort">COM5</strong>
      </div>
      <div class="hud-chip progress-chip">
        <span>Z-STEP:</span> <strong id="headerHeightProgress">0 / 10</strong>
      </div>
      <div class="hud-chip loop-chip" id="hudLoopChip" title="Iterasi loop pemindaian per baris">
        <span>LOOP:</span> <strong id="headerLoopProgress" style="color:#7DD3FC;">1 / 1</strong>
      </div>
      <!-- Transition Status Chip -->
      <div class="hud-chip transition-chip" id="hudTransitionChip" style="display:none;background:rgba(245,158,11,0.15);border:1px solid rgba(245,158,11,0.35);color:#FBBF24;" title="Proses delay transisi perpindahan baris">
        <span class="transition-spinner"></span>
        <strong id="headerTransitionText">Transisi Lift (1.5s)...</strong>
      </div>
    </div>
  </header>

  <!-- ========================================================================
       2. CONTROL DECK & COMMAND RIBBON
       ======================================================================== -->
  <div class="control-deck">
    <div class="command-btn-group">
      <button class="btn-action btn-start" id="btnStartScan" onclick="openStartScanModal()">
        <svg viewBox="0 0 24 24" fill="currentColor"><polygon points="5 3 19 12 5 21 5 3"/></svg>
        <span>START SCAN</span>
      </button>
      <button class="btn-action btn-pause" id="btnPauseScan" onclick="pauseScanning()" disabled>
        <svg viewBox="0 0 24 24" fill="currentColor"><rect x="6" y="4" width="4" height="16"/><rect x="14" y="4" width="4" height="16"/></svg>
        <span id="pauseBtnLabel">PAUSE</span>
      </button>
      <button class="btn-action btn-reset" id="btnStopScan" onclick="stopScanning()" disabled>
        <svg viewBox="0 0 24 24" fill="currentColor"><rect x="4" y="4" width="16" height="16" rx="2"/></svg>
        <span>RESET</span>
      </button>

      <!-- TOMBOL SIMPAN HASIL SCAN (KUNING SAAT SCAN -> HIJAU SAAT BERES/STOP) -->
      <button class="btn-action btn-save-session btn-save-idle" id="btnSaveSession" onclick="saveScanSession()" disabled title="Simpan seluruh hasil scan ke database TiDB Cloud">
        <svg id="saveBtnIcon" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2">
          <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/>
          <polyline points="17 21 17 13 7 13 7 21"/>
          <polyline points="7 3 7 8 15 8"/>
        </svg>
        <span id="saveBtnLabel">SIMPAN HASIL</span>
      </button>

      <button class="btn-action btn-outline" id="btnForceExport" onclick="exportMatrixCsv()" title="Export acquired scan matrix to CSV">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
        <span>CSV</span>
      </button>
      <button class="btn-action btn-outline" onclick="exportMatrixDat()" title="Export formatted matrix to .DAT">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
        <span>.DAT</span>
      </button>
    </div>

    <!-- Quick Parameter Controls -->
    <div class="params-bar">
      <div class="param-pill" title="Hardware Serial / COM Port selection">
        <label for="inputComPort">COM:</label>
        <select id="inputComPort" onchange="updateComPort(this.value)">
          <option value="COM1">COM1</option>
          <option value="COM2">COM2</option>
          <option value="COM3">COM3</option>
          <option value="COM4">COM4</option>
          <option value="COM5" selected>COM5</option>
          <option value="COM6">COM6</option>
          <option value="COM7">COM7</option>
          <option value="COM8">COM8</option>
          <option value="COM9">COM9</option>
          <option value="COM10">COM10</option>
          <option value="/dev/ttyUSB0">/dev/ttyUSB0</option>
          <option value="/dev/ttyACM0">/dev/ttyACM0</option>
        </select>
      </div>

      <div class="param-pill" title="Total scanning height levels">
        <label for="inputTotalHeight">Steps:</label>
        <input type="number" id="inputTotalHeight" value="24" min="1" max="50" onchange="updateTotalHeights(this.value)">
      </div>

      <div class="param-pill" title="Jumlah loop per baris scan">
        <label for="inputTotalLoops">Loops:</label>
        <input type="number" id="inputTotalLoops" value="1" min="1" max="20" onchange="updateTotalLoops(this.value)">
      </div>

      <div class="param-pill" title="Lama delay transisi lift antar baris (detik)">
        <label for="inputTransitionDelay">Delay:</label>
        <input type="number" id="inputTransitionDelay" value="1.5" min="0" max="15" step="0.5" onchange="updateTransitionDelay(this.value)">
        <span style="font-size:10px;color:var(--text-muted);">s</span>
      </div>

      <div class="param-pill" title="Sampling acquisition rate">
        <label for="inputSamplingRate">Rate:</label>
        <select id="inputSamplingRate" onchange="updateSamplingInterval(this.value)">
          <option value="1000" selected>1.0 Hz (1s)</option>
          <option value="500">2.0 Hz (500ms)</option>
          <option value="2000">0.5 Hz (2s)</option>
          <option value="100">10.0 Hz (Fast)</option>
        </select>
      </div>

      <div class="param-pill" title="Hotspot radiation threshold in CPS">
        <label for="inputHotspotThreshold">Hotspot &gt;:</label>
        <input type="number" id="inputHotspotThreshold" value="100" min="50" max="1000" onchange="updateThreshold(this.value)">
        <span style="font-size:10px;color:var(--text-muted);">CPS</span>
      </div>

      <!-- Firebase Cloud Sync Switch -->
      <div class="cloud-sync-pill active" id="fbSyncToggle" onclick="toggleFirebaseSync()" title="Realtime Firebase Database synchronization">
        <div class="toggle-switch-dot"></div>
        <span>Cloud Sync</span>
        <span class="latency-tag" id="syncLatencyText">12ms</span>
      </div>
    </div>
  </div>

  <!-- ========================================================================
       3. MAIN WORKSPACE (SIDEBAR + CENTER VIEWPORT)
       ======================================================================== -->
  <div class="matrix-workspace">

    <!-- LEFT SIDEBAR: SCAN LOOPS -->
    <aside class="matrix-sidebar">
      
      <!-- Loop Selection Panel -->
      <div class="panel-card">
        <div class="panel-head">
          <h3>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="23 4 23 10 17 10"/><polyline points="1 20 1 14 7 14"/><path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"/></svg>
            SCAN LOOPS
          </h3>
          <span class="panel-badge" id="sidebarLoopCountText">24 Loops</span>
        </div>

        <div class="loop-scroll-list" id="loopListContainer">
          <!-- Dynamic clean loop items (L01, L02... L24) -->
        </div>

        <div class="loop-btn-bar">
          <button class="btn-micro" onclick="selectAllLoops(true)">ALL</button>
          <button class="btn-micro" onclick="selectAllLoops(false)">NONE</button>
        </div>
      </div>
    </aside>

    <!-- CENTER MAIN VIEWPORT -->
    <main class="matrix-main-viewport">

      <!-- Viewport Navigation & Palette Toolbar -->
      <div class="viewport-topbar">
        <div class="segmented-tabs">
          <button class="tab-pill-btn active" id="tabBtnMatrix" onclick="switchViewMode('matrix')">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"/><line x1="3" y1="9" x2="21" y2="9"/><line x1="3" y1="15" x2="21" y2="15"/><line x1="9" y1="3" x2="9" y2="21"/><line x1="15" y1="3" x2="15" y2="21"/></svg>
            Matrix
          </button>
          <button class="tab-pill-btn" id="tabBtnContour" onclick="switchViewMode('contour')">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></svg>
            Contour
          </button>
          <button class="tab-pill-btn" id="tabBtnGrid" onclick="switchViewMode('grid')">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
            8&times;9 Grid
          </button>
          <button class="tab-pill-btn" id="tabBtnRawTable" onclick="switchViewMode('table')">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>
            Data Table
          </button>
        </div>

        <div class="palette-picker">
          <span>Palette:</span>
          <select id="paletteSelect" onchange="changeColorPalette(this.value)">
            <option value="turbo" selected>Turbo (Scientific)</option>
            <option value="jet">Jet / Rainbow</option>
            <option value="viridis">Viridis</option>
            <option value="magma">Magma</option>
            <option value="thermal">Thermal IR</option>
          </select>
        </div>
      </div>

      <!-- Centerpiece: 2D Dynamic Heatmap Card -->
      <div class="heatmap-stage-card" id="heatmapMainCard">
        <!-- Module Zone Markers -->
        <div class="module-marker-strip">
          <div class="mod-marker xs1">XS1: 01 &ndash; 24 (0 &ndash; 266 cm)</div>
          <div class="mod-marker xs2">XS2: 25 &ndash; 48 (267 &ndash; 533 cm)</div>
          <div class="mod-marker xs3">XS3: 49 &ndash; 72 (534 &ndash; 800 cm)</div>
        </div>

        <!-- Heatmap Canvas Stage -->
        <div class="canvas-viewport-wrapper" id="canvasContainer">
          <canvas id="matrixHeatmapCanvas" width="1100" height="400"></canvas>
          
          <!-- Floating Glass Tooltip -->
          <div class="matrix-tooltip" id="matrixTooltip">
            <div class="tt-head">
              <span class="tt-det-title" id="ttDetectorName">Detector D38</span>
              <span class="tt-mod-tag" id="ttModuleTag">XS2 (0x02)</span>
            </div>
            <div class="tt-grid-info">
              <div class="tt-stat-row">
                <span>Height:</span>
                <span class="tt-val" id="ttHeightLevel">Loop 5 (175 cm)</span>
              </div>
              <div class="tt-stat-row">
                <span>Pos (X, Z):</span>
                <span class="tt-val" id="ttCoordinates">422 cm, 175 cm</span>
              </div>
              <div class="tt-stat-row">
                <span>Counts:</span>
                <span class="tt-val" id="ttCpsValue" style="color:#fbbf24;font-weight:700;">285 CPS</span>
              </div>
              <div class="tt-stat-row">
                <span>Dose Rate:</span>
                <span class="tt-val" id="ttDoseRate">3.42 &micro;Sv/h</span>
              </div>
              <div class="tt-stat-row">
                <span>Status:</span>
                <span class="tt-val" id="ttStatus">HOTSPOT</span>
              </div>
            </div>
          </div>
        </div>

        <!-- Table Viewport (Alternative Mode) -->
        <div class="raw-table-wrapper" id="matrixTableView" style="display:none;padding:12px;">
          <table class="matrix-raw-table" id="rawMatrixTable">
            <thead>
              <tr id="tableHeaderRow"></tr>
            </thead>
            <tbody id="tableBodyRows"></tbody>
          </table>
        </div>

        <!-- Colorbar Gradient Legend Strip -->
        <div class="colorbar-container">
          <span>0 CPS</span>
          <div class="colorbar-track" id="colorbarGradient"></div>
          <span id="colorbarMaxLabel">350+ CPS (Hotspot)</span>
        </div>
      </div>

      <!-- 3D Source Localization & Top View Spatial Cards -->
      <div class="spatial-split-row">
        <!-- 3D Source Position Estimator -->
        <div class="hud-spatial-card">
          <div class="spatial-head">
            <span>3D Source Localization</span>
            <span class="badge-live">Isometric Model</span>
          </div>
          <div class="canvas-spatial-stage" id="container3D">
            <canvas id="canvas3D" width="500" height="220"></canvas>
          </div>
          <div class="spatial-hud-overlay">
            <div class="hud-stat-box">
              <div class="h-lbl">POS X</div>
              <div class="h-val highlight" id="statPosX">412.5 cm</div>
            </div>
            <div class="hud-stat-box">
              <div class="h-lbl">POS Y</div>
              <div class="h-val highlight" id="statPosY">-32.7 cm</div>
            </div>
            <div class="hud-stat-box">
              <div class="h-lbl">POS Z</div>
              <div class="h-val highlight" id="statPosZ">186.4 cm</div>
            </div>
          </div>
        </div>

        <!-- Top View X-Z Source Map & Info -->
        <div class="hud-spatial-card">
          <div class="spatial-head">
            <span>Top-View Plane (X-Z)</span>
            <span class="badge-live" id="statConfidence">96.2% Confidence</span>
          </div>
          <div class="canvas-spatial-stage">
            <canvas id="canvasTopViewXZ" width="500" height="220"></canvas>
          </div>
          <div class="spatial-hud-overlay">
            <div class="hud-stat-box">
              <div class="h-lbl">CONFIDENCE</div>
              <div class="h-val" id="statConfidenceVal" style="color:#34d399;">96.2%</div>
            </div>
            <div class="hud-stat-box">
              <div class="h-lbl">METHOD</div>
              <div class="h-val" style="color:#93c5fd;">Bayesian</div>
            </div>
            <div class="hud-stat-box">
              <div class="h-lbl">PEAK DETECTOR</div>
              <div class="h-val alert" id="statHotspotCount">XS2 (D38)</div>
            </div>
          </div>
        </div>
      </div>

      <!-- Real-Time Metric Telemetry KPI Deck -->
      <div class="telemetry-cards-row">
        <div class="kpi-tile kpi-channels">
          <div class="kpi-header">Total Channels</div>
          <div class="kpi-number" id="metricTotalChannels">72 <span class="kpi-unit">Ch</span></div>
          <div class="kpi-subtext">3x Xiao Modules</div>
        </div>

        <div class="kpi-tile kpi-loop">
          <div class="kpi-header">Active Step</div>
          <div class="kpi-number" id="metricActiveLoop">10 <span class="kpi-unit">/ 10</span></div>
          <div class="kpi-subtext" id="metricElapsedTimer">Elapsed: 00:01:24</div>
        </div>

        <div class="kpi-tile kpi-peak">
          <div class="kpi-header">Peak Intensity</div>
          <div class="kpi-number" id="metricMaxCps">3245 <span class="kpi-unit">cps</span></div>
          <div class="kpi-subtext" id="metricMaxChannel">Detector D43 (XS2)</div>
        </div>

        <div class="kpi-tile kpi-avg">
          <div class="kpi-header">Average CPS</div>
          <div class="kpi-number" id="metricAvgCps">174.2 <span class="kpi-unit">cps</span></div>
          <div class="kpi-subtext" id="metricStdDev">Std Dev: 61.2</div>
        </div>

        <div class="kpi-tile kpi-total">
          <div class="kpi-header">Total Counts</div>
          <div class="kpi-number" id="metricTotalCounts">12,540 <span class="kpi-unit">cts</span></div>
          <div class="kpi-subtext">Accumulated</div>
        </div>

        <div class="kpi-tile kpi-bg">
          <div class="kpi-header">Background</div>
          <div class="kpi-number" id="metricBgCps">31.4 <span class="kpi-unit">cps</span></div>
          <div class="kpi-subtext">Base Radiation</div>
        </div>

        <div class="kpi-tile kpi-time" title="Waktu rekaman data terakhir di TiDB Cloud">
          <div class="kpi-header">Last DB Record</div>
          <div class="kpi-number" id="metricLastTimestamp">
            <span id="metricLastTime">--:--:--</span>
            <span class="kpi-unit" id="metricLastTz">WIB</span>
          </div>
          <div class="kpi-subtext" id="metricLastTimestampSub">
            <span id="metricLastDate">-- --- ----</span> &bull; <strong id="kpiDbSourceBadge" style="color:var(--accent-emerald);">TiDB Cloud</strong>
          </div>
        </div>
      </div>

      <!-- Bottom Split: Real-time Line Chart + Modbus Terminal Console -->
      <div class="bottom-analytics-split">
        <!-- Module Running CPS Chart -->
        <div class="chart-panel-card">
          <div class="panel-head" style="background:transparent;padding:0 0 8px 0;margin-bottom:4px;">
            <h3>
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
              REAL-TIME TELEMETRY (MODULE CPS)
            </h3>
            <span class="panel-badge">XS1 &bull; XS2 &bull; XS3</span>
          </div>
          <div class="chart-stage">
            <canvas id="moduleChartCanvas"></canvas>
          </div>
        </div>

        <!-- Raw RS485 Terminal Stream -->
        <div class="terminal-window-card">
          <div class="terminal-title-bar">
            <div class="terminal-title">
              <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="4 17 10 11 4 5"/><line x1="12" y1="19" x2="20" y2="19"/></svg>
              MODBUS RS485 LOG
            </div>
            <div class="terminal-controls">
              <button class="btn-term-action" onclick="document.getElementById('terminalScreen').innerHTML=''">Clear</button>
            </div>
          </div>
          <div class="terminal-console-body" id="terminalScreen">
            <div class="log-entry"><span class="log-t">[08:00:00]</span> <span class="log-badge-ok">[INIT]</span> System Ready &bull; 72-Ch Matrix Active</div>
            <div class="log-entry"><span class="log-t">[08:00:01]</span> <span class="log-badge-modbus">[MODBUS]</span> Connected COM5 @ 115200 baud</div>
            <div class="log-entry"><span class="log-t">[08:00:02]</span> <span class="log-badge-ok">[POLL]</span> XS1 (0x01), XS2 (0x02), XS3 (0x03) verified</div>
            <div class="log-entry"><span class="log-t">[08:00:03]</span> <span class="log-badge-warn">[STANDBY]</span> Ready for scan acquisition</div>
          </div>
        </div>
      </div>

    </main>
  </div>
</div>

<!-- ========================================================================
     MODAL: KONFIGURASI START SCAN & IDENTITAS OBJEK
     ======================================================================== -->
<div class="scan-modal-backdrop" id="modalStartScanConfig" style="display:none;" onclick="if(event.target===this) closeStartScanModal()">
  <div class="scan-modal-card glassmorphism-card animate-scale-up">
    <div class="scan-modal-header">
      <div class="scan-modal-title-wrap">
        <div class="scan-modal-icon-badge">
          <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2">
            <circle cx="12" cy="12" r="10"/><polygon points="10 8 16 12 10 16 10 8"/>
          </svg>
        </div>
        <div>
          <h3>Konfigurasi Pemindaian Objek Baru</h3>
          <p class="scan-modal-sub">Atur identitas target objek, ketinggian scan, multi-loop, dan delay transisi lift.</p>
        </div>
      </div>
      <button class="scan-modal-close" onclick="closeStartScanModal()" title="Tutup">&times;</button>
    </div>

    <div class="scan-modal-body">
      <!-- 1. Identitas Objek Target (Sinkron TiDB Cloud) -->
      <div class="modal-form-group">
        <label class="modal-label">
          <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/></svg>
          Nama / Identitas Objek Target (Sesuai TiDB Cloud) <span class="req-star">*</span>
        </label>
        <input type="text" id="modalInputObjectName" class="modal-text-input" list="tidbObjectDatalist" value="Tong Baru" placeholder="Pilih dari daftar TiDB atau ketik nama baru..." autocomplete="off">
        <datalist id="tidbObjectDatalist">
          <option value="Tong Baru">
          <option value="Tong Dua">
          <option value="Gentong">
        </datalist>
        
        <!-- Preset Tag Suggestions from TiDB -->
        <div class="preset-tag-list" id="modalPresetTagList">
          <span class="preset-tag active" onclick="selectObjectPreset('Tong Baru')">Tong Baru</span>
          <span class="preset-tag" onclick="selectObjectPreset('Tong Dua')">Tong Dua</span>
          <span class="preset-tag" onclick="selectObjectPreset('Gentong')">Gentong</span>
        </div>
      </div>

      <!-- 2. Grid Parameters: Steps, Loops, Delay -->
      <div class="modal-grid-3">
        <div class="modal-form-group">
          <label class="modal-label" for="modalInputSteps">
            <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M3 9h18M3 15h18"/></svg>
            Jumlah Blok / Steps
          </label>
          <input type="number" id="modalInputSteps" class="modal-num-input" value="24" min="1" max="50">
          <span class="modal-help">Level elevasi (Z-axis)</span>
        </div>

        <div class="modal-form-group">
          <label class="modal-label" for="modalInputLoops">
            <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"><polyline points="23 4 23 10 17 10"/><polyline points="1 20 1 14 7 14"/><path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"/></svg>
            Loop per Baris
          </label>
          <input type="number" id="modalInputLoops" class="modal-num-input" value="1" min="1" max="20">
          <span class="modal-help">Pengulangan per baris</span>
        </div>

        <div class="modal-form-group">
          <label class="modal-label" for="modalInputTransitionDelay">
            <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
            Delay Transisi (Detik)
          </label>
          <input type="number" id="modalInputTransitionDelay" class="modal-num-input" value="1.5" min="0" max="15" step="0.5">
          <span class="modal-help">Jeda lift pindah baris</span>
        </div>
      </div>

      <!-- 3. Port & Sampling Rate in Modal -->
      <div class="modal-grid-2">
        <div class="modal-form-group">
          <label class="modal-label">Port Serial / COM</label>
          <select id="modalInputComPort" class="modal-select-input">
            <option value="COM1">COM1</option>
            <option value="COM2">COM2</option>
            <option value="COM3">COM3</option>
            <option value="COM4">COM4</option>
            <option value="COM5" selected>COM5</option>
            <option value="COM6">COM6</option>
            <option value="COM7">COM7</option>
            <option value="COM8">COM8</option>
            <option value="COM9">COM9</option>
            <option value="COM10">COM10</option>
            <option value="/dev/ttyUSB0">/dev/ttyUSB0</option>
            <option value="/dev/ttyACM0">/dev/ttyACM0</option>
          </select>
        </div>

        <div class="modal-form-group">
          <label class="modal-label">Sampling Interval</label>
          <select id="modalInputSamplingRate" class="modal-select-input">
            <option value="1000" selected>1.0 Hz (1.0s / sample)</option>
            <option value="500">2.0 Hz (500ms / sample)</option>
            <option value="2000">0.5 Hz (2.0s / sample)</option>
            <option value="100">10.0 Hz (100ms Fast)</option>
          </select>
        </div>
      </div>
    </div>

    <div class="scan-modal-footer">
      <button class="btn-modal-cancel" onclick="closeStartScanModal()">Batal</button>
      <button class="btn-modal-launch" onclick="launchConfiguredScan()">
        <svg viewBox="0 0 24 24" width="16" height="16" fill="currentColor"><polygon points="5 3 19 12 5 21 5 3"/></svg>
        <span>Mulai Pemindaian</span>
      </button>
    </div>
  </div>
</div>

<!-- ========================================================================
     FLOATING TOAST / NOTIFICATION CONTAINER
     ======================================================================== -->
<div class="toast-notification-container" id="toastNotificationContainer"></div>
@endsection

@section('scripts')
<!-- Firebase Compatibility SDKs -->
<script src="https://www.gstatic.com/firebasejs/10.8.0/firebase-app-compat.js"></script>
<script src="https://www.gstatic.com/firebasejs/10.8.0/firebase-database-compat.js"></script>

<script>
/* ==========================================================================
   RADIOSCAN MATRIX v2.0 JAVASCRIPT CORE ENGINE
   ========================================================================== */

// 1. GLOBAL STATE & CONFIGURATION
const APP_STATE = {
  selectedComPort: 'COM5',
  selectedBaudrate: 115200,
  totalHeights: 24,
  currentHeight: 0,
  objectName: 'Gentong',
  totalLoops: 1,
  currentLoop: 1,
  transitionDelay: 1.5,
  sessionId: '',
  sessionRecords: [], // Holds all individual loop/height records
  saveState: 'idle', // 'idle' | 'scanning' | 'ready' | 'saved'
  isTransitioning: false,
  samplingIntervalMs: 1000,
  hotspotThreshold: 100,
  isScanning: false,
  isPaused: false,
  timerIntervalId: null,
  scanIntervalId: null,
  elapsedSeconds: 0,
  useFirebase: true,
  currentPalette: 'turbo',
  viewMode: 'matrix', // 'matrix', 'contour', 'grid', 'table'
  matrixData: [], // Array of rows: each row is 72 values
  xs1History: [],
  xs2History: [],
  xs3History: [],
  timestamps: [],
  selectedLoops: new Set(Array.from({length: 24}, (_, i) => i + 1)),
  estimatedSource: { x: 412.5, y: -32.7, z: 186.4, confidence: 96.2 }
};

// 2. FIREBASE REALTIME DB INITIALIZATION
let firebaseDb = null;
try {
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
  if (typeof firebase !== 'undefined' && firebaseConfig.apiKey) {
    if (!firebase.apps.length) {
      firebase.initializeApp(firebaseConfig);
    }
    firebaseDb = firebase.database();
    console.log("[Firebase] Initialized successfully with URL:", firebaseConfig.databaseURL);
  }
} catch (err) {
  console.warn("[Firebase] Init error (running in local simulation mode):", err);
}

// 3. COLOR PALETTE DEFINITIONS & INTERPOLATORS
function getColorForValue(val, maxVal = 350, palette = APP_STATE.currentPalette) {
  const t = Math.max(0, Math.min(1, val / maxVal));
  
  if (palette === 'turbo') {
    if (t < 0.25) {
      const f = t / 0.25;
      return `rgb(${Math.round(30 + 10*f)}, ${Math.round(40 + 140*f)}, ${Math.round(180 + 75*f)})`;
    } else if (t < 0.5) {
      const f = (t - 0.25) / 0.25;
      return `rgb(${Math.round(40 + 160*f)}, ${Math.round(180 + 70*f)}, ${Math.round(255 - 200*f)})`;
    } else if (t < 0.75) {
      const f = (t - 0.5) / 0.25;
      return `rgb(${Math.round(200 + 55*f)}, ${Math.round(250 - 80*f)}, ${Math.round(55 - 45*f)})`;
    } else {
      const f = (t - 0.75) / 0.25;
      return `rgb(${Math.round(255 - 30*f)}, ${Math.round(170 - 140*f)}, ${Math.round(10 + 20*f)})`;
    }
  } else if (palette === 'jet') {
    let r = Math.min(Math.max(1.5 - Math.abs(t * 4 - 3), 0), 1) * 255;
    let g = Math.min(Math.max(1.5 - Math.abs(t * 4 - 2), 0), 1) * 255;
    let b = Math.min(Math.max(1.5 - Math.abs(t * 4 - 1), 0), 1) * 255;
    return `rgb(${Math.round(r)}, ${Math.round(g)}, ${Math.round(b)})`;
  } else if (palette === 'viridis') {
    const r = Math.round(68 + t * (253 - 68));
    const g = Math.round(1 + Math.sin(t * Math.PI) * 200 + t * 50);
    const b = Math.round(84 + (1 - t) * 140);
    return `rgb(${r}, ${g}, ${b})`;
  } else if (palette === 'magma') {
    const r = Math.round(t < 0.3 ? t * 400 : 120 + (t - 0.3) * 190);
    const g = Math.round(t < 0.5 ? t * 50 : 25 + (t - 0.5) * 400);
    const b = Math.round(t < 0.4 ? 40 + t * 300 : 160 - (t - 0.4) * 250);
    return `rgb(${Math.min(255, Math.max(0, r))}, ${Math.min(255, Math.max(0, g))}, ${Math.min(255, Math.max(0, b))})`;
  } else {
    // Thermal IR
    if (t < 0.33) {
      const f = t / 0.33;
      return `rgb(${Math.round(20*f)}, ${Math.round(30*f)}, ${Math.round(120 + 135*f)})`;
    } else if (t < 0.66) {
      const f = (t - 0.33) / 0.33;
      return `rgb(${Math.round(20 + 235*f)}, ${Math.round(30 + 100*f)}, ${Math.round(255 - 250*f)})`;
    } else {
      const f = (t - 0.66) / 0.34;
      return `rgb(255, ${Math.round(130 + 125*f)}, ${Math.round(5 + 240*f)})`;
    }
  }
}

function updateColorbarGradient() {
  const gradientEl = document.getElementById('colorbarGradient');
  if (!gradientEl) return;
  const stops = [];
  for (let i = 0; i <= 10; i++) {
    const pct = i * 10;
    const col = getColorForValue(pct * 3.5, 350, APP_STATE.currentPalette);
    stops.push(`${col} ${pct}%`);
  }
  gradientEl.style.background = `linear-gradient(to right, ${stops.join(', ')})`;
}

// Helper to detect current light mode
function isLightMode() {
  return document.documentElement.classList.contains('light-mode');
}

// 4. CHART.JS REAL-TIME TELEMETRY SETUP
let telemetryChart = null;

function initTelemetryChart() {
  const ctx = document.getElementById('moduleChartCanvas').getContext('2d');
  const isLight = isLightMode();
  
  telemetryChart = new Chart(ctx, {
    type: 'line',
    data: {
      labels: [],
      datasets: [
        {
          label: 'XS1 (D01-D24)',
          borderColor: isLight ? '#2563EB' : '#60A5FA',
          backgroundColor: isLight ? 'rgba(37, 99, 235, 0.1)' : 'rgba(96, 165, 250, 0.1)',
          borderWidth: 2,
          pointRadius: 2.5,
          data: [],
          tension: 0.35
        },
        {
          label: 'XS2 (D25-D48)',
          borderColor: isLight ? '#D97706' : '#FBBF24',
          backgroundColor: isLight ? 'rgba(217, 119, 6, 0.12)' : 'rgba(251, 191, 36, 0.15)',
          borderWidth: 2,
          pointRadius: 3,
          pointBackgroundColor: isLight ? '#D97706' : '#F59E0B',
          data: [],
          tension: 0.35
        },
        {
          label: 'XS3 (D49-D72)',
          borderColor: isLight ? '#059669' : '#34D399',
          backgroundColor: isLight ? 'rgba(5, 150, 105, 0.1)' : 'rgba(52, 211, 153, 0.1)',
          borderWidth: 2,
          pointRadius: 2.5,
          data: [],
          tension: 0.35
        }
      ]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      animation: { duration: 200 },
      scales: {
        x: {
          grid: { color: isLight ? 'rgba(0, 0, 0, 0.06)' : 'rgba(255, 255, 255, 0.05)' },
          ticks: { color: '#64748B', font: { family: 'IBM Plex Mono', size: 9.5 } }
        },
        y: {
          grid: { color: isLight ? 'rgba(0, 0, 0, 0.06)' : 'rgba(255, 255, 255, 0.05)' },
          ticks: { color: '#64748B', font: { family: 'IBM Plex Mono', size: 9.5 } }
        }
      },
      plugins: {
        legend: {
          labels: { color: isLight ? '#334155' : '#CBD5E1', font: { family: 'IBM Plex Mono', size: 10.5 }, boxWidth: 10 }
        }
      }
    }
  });
}

function updateChartTheme(isLight) {
  if (!telemetryChart) return;
  telemetryChart.options.scales.x.grid.color = isLight ? 'rgba(0, 0, 0, 0.06)' : 'rgba(255, 255, 255, 0.05)';
  telemetryChart.options.scales.y.grid.color = isLight ? 'rgba(0, 0, 0, 0.06)' : 'rgba(255, 255, 255, 0.05)';
  telemetryChart.options.plugins.legend.labels.color = isLight ? '#334155' : '#CBD5E1';
  telemetryChart.data.datasets[0].borderColor = isLight ? '#2563EB' : '#60A5FA';
  telemetryChart.data.datasets[0].backgroundColor = isLight ? 'rgba(37, 99, 235, 0.1)' : 'rgba(96, 165, 250, 0.1)';
  telemetryChart.data.datasets[1].borderColor = isLight ? '#D97706' : '#FBBF24';
  telemetryChart.data.datasets[1].backgroundColor = isLight ? 'rgba(217, 119, 6, 0.12)' : 'rgba(251, 191, 36, 0.15)';
  telemetryChart.data.datasets[1].pointBackgroundColor = isLight ? '#D97706' : '#F59E0B';
  telemetryChart.data.datasets[2].borderColor = isLight ? '#059669' : '#34D399';
  telemetryChart.data.datasets[2].backgroundColor = isLight ? 'rgba(5, 150, 105, 0.1)' : 'rgba(52, 211, 153, 0.1)';
  telemetryChart.update();
}

// 5. HEATMAP CANVAS RENDERING ENGINE
const canvas = document.getElementById('matrixHeatmapCanvas');
const ctx = canvas.getContext('2d');
let hoveredCell = null;

function renderMatrixHeatmap() {
  const width = canvas.width;
  const height = canvas.height;
  const isLight = isLightMode();
  ctx.clearRect(0, 0, width, height);

  const numCols = 72;
  const numRows = APP_STATE.totalHeights;
  const cellW = width / numCols;
  const cellH = height / numRows;

  ctx.fillStyle = isLight ? '#f8fafc' : '#040711';
  ctx.fillRect(0, 0, width, height);

  if (APP_STATE.viewMode === 'contour') {
    renderContourView(width, height);
    return;
  } else if (APP_STATE.viewMode === 'grid') {
    render8x9GridView(width, height);
    return;
  }

  // Draw Heatmap Cells
  for (let r = 0; r < numRows; r++) {
    const isCompleted = r < APP_STATE.matrixData.length;
    const rowData = isCompleted ? APP_STATE.matrixData[r] : null;
    const isLoopSelected = APP_STATE.selectedLoops.has(r + 1);

    for (let c = 0; c < numCols; c++) {
      const x = c * cellW;
      const y = r * cellH;

      if (isCompleted && isLoopSelected && rowData) {
        const cps = rowData[c];
        ctx.fillStyle = getColorForValue(cps, 350);
        ctx.fillRect(x, y, cellW, cellH);

        if (cps >= APP_STATE.hotspotThreshold) {
          ctx.strokeStyle = isLight ? '#E11D48' : '#F43F5E';
          ctx.lineWidth = 1.5;
          ctx.strokeRect(x + 0.5, y + 0.5, cellW - 1, cellH - 1);
        } else {
          ctx.strokeStyle = isLight ? 'rgba(0, 0, 0, 0.12)' : 'rgba(0, 0, 0, 0.25)';
          ctx.lineWidth = 0.5;
          ctx.strokeRect(x, y, cellW, cellH);
        }
      } else {
        if (isLight) {
          ctx.fillStyle = (r % 2 === c % 2) ? '#ffffff' : '#f1f5f9';
          ctx.fillRect(x, y, cellW, cellH);
          ctx.strokeStyle = 'rgba(0, 0, 0, 0.05)';
        } else {
          ctx.fillStyle = (r % 2 === c % 2) ? '#0A0F1D' : '#070B16';
          ctx.fillRect(x, y, cellW, cellH);
          ctx.strokeStyle = 'rgba(255, 255, 255, 0.025)';
        }
        ctx.lineWidth = 0.5;
        ctx.strokeRect(x, y, cellW, cellH);
      }
    }

    if (isLight) {
      ctx.fillStyle = isCompleted ? '#334155' : '#94a3b8';
    } else {
      ctx.fillStyle = isCompleted ? '#94A3B8' : '#334155';
    }
    ctx.font = '600 9px "IBM Plex Mono"';
    ctx.fillText(`L${r + 1 < 10 ? '0' + (r + 1) : r + 1}`, 6, r * cellH + cellH / 2 + 3);
  }

  // Vertical Module Boundaries
  ctx.setLineDash([4, 4]);
  ctx.strokeStyle = isLight ? 'rgba(0, 0, 0, 0.25)' : 'rgba(255, 255, 255, 0.25)';
  ctx.lineWidth = 1.5;
  
  const xDivider1 = 24 * cellW;
  ctx.beginPath();
  ctx.moveTo(xDivider1, 0);
  ctx.lineTo(xDivider1, height);
  ctx.stroke();

  const xDivider2 = 48 * cellW;
  ctx.beginPath();
  ctx.moveTo(xDivider2, 0);
  ctx.lineTo(xDivider2, height);
  ctx.stroke();
  ctx.setLineDash([]);

  // Hover Crosshairs
  if (hoveredCell && hoveredCell.row < numRows && hoveredCell.col < numCols) {
    const hx = hoveredCell.col * cellW;
    const hy = hoveredCell.row * cellH;

    ctx.strokeStyle = isLight ? '#0f172a' : '#FFFFFF';
    ctx.lineWidth = 2;
    ctx.strokeRect(hx - 0.5, hy - 0.5, cellW + 1, cellH + 1);

    ctx.strokeStyle = isLight ? 'rgba(0, 0, 0, 0.25)' : 'rgba(255, 255, 255, 0.2)';
    ctx.setLineDash([2, 2]);
    ctx.beginPath();
    ctx.moveTo(0, hy + cellH / 2);
    ctx.lineTo(width, hy + cellH / 2);
    ctx.moveTo(hx + cellW / 2, 0);
    ctx.lineTo(hx + cellW / 2, height);
    ctx.stroke();
    ctx.setLineDash([]);
  }
}

// 6. CONTINUOUS CONTOUR VIEW
function renderContourView(width, height) {
  const isLight = isLightMode();
  ctx.fillStyle = isLight ? '#f8fafc' : '#040711';
  ctx.fillRect(0, 0, width, height);

  if (APP_STATE.matrixData.length === 0) {
    ctx.fillStyle = isLight ? '#64748B' : '#64748B';
    ctx.font = '13px "IBM Plex Mono"';
    ctx.textAlign = 'center';
    ctx.fillText("No scan matrix data acquired yet. Press [START SCAN] to begin.", width / 2, height / 2);
    ctx.textAlign = 'left';
    return;
  }

  const imgData = ctx.createImageData(width, height);
  const data = imgData.data;
  const numRows = APP_STATE.matrixData.length;
  const numCols = 72;

  for (let py = 0; py < height; py += 2) {
    const normY = py / height;
    const rowFloat = normY * (numRows - 1);
    const r0 = Math.floor(rowFloat);
    const r1 = Math.min(numRows - 1, r0 + 1);
    const ryRatio = rowFloat - r0;

    for (let px = 0; px < width; px += 2) {
      const normX = px / width;
      const colFloat = normX * (numCols - 1);
      const c0 = Math.floor(colFloat);
      const c1 = Math.min(numCols - 1, c0 + 1);
      const rxRatio = colFloat - c0;

      const v00 = APP_STATE.matrixData[r0][c0] || 25;
      const v10 = APP_STATE.matrixData[r0][c1] || 25;
      const v01 = APP_STATE.matrixData[r1][c0] || 25;
      const v11 = APP_STATE.matrixData[r1][c1] || 25;

      const topVal = v00 * (1 - rxRatio) + v10 * rxRatio;
      const botVal = v01 * (1 - rxRatio) + v11 * rxRatio;
      const val = topVal * (1 - ryRatio) + botVal * ryRatio;

      const rgbStr = getColorForValue(val, 350);
      const match = rgbStr.match(/\d+/g);
      const r = parseInt(match[0]);
      const g = parseInt(match[1]);
      const b = parseInt(match[2]);

      for (let dy = 0; dy < 2 && (py + dy) < height; dy++) {
        for (let dx = 0; dx < 2 && (px + dx) < width; dx++) {
          const idx = ((py + dy) * width + (px + dx)) * 4;
          data[idx] = r;
          data[idx + 1] = g;
          data[idx + 2] = b;
          data[idx + 3] = 255;
        }
      }
    }
  }

  ctx.putImageData(imgData, 0, 0);

  const cx = (APP_STATE.estimatedSource.x / 800) * width;
  const cy = ((350 - APP_STATE.estimatedSource.z) / 350) * height;

  ctx.fillStyle = '#EF4444';
  ctx.beginPath();
  ctx.arc(cx, cy, 7, 0, Math.PI * 2);
  ctx.fill();

  ctx.strokeStyle = '#FFFFFF';
  ctx.lineWidth = 2;
  ctx.stroke();
}

// 7. 8x9 CELL MATRIX VIEW
function render8x9GridView(width, height) {
  const rows = 8;
  const cols = 9;
  const cellW = width / cols;
  const cellH = height / rows;
  const isLight = isLightMode();

  ctx.fillStyle = isLight ? '#f8fafc' : '#040711';
  ctx.fillRect(0, 0, width, height);

  const detectorAverages = new Array(72).fill(0);
  if (APP_STATE.matrixData.length > 0) {
    for (let r = 0; r < APP_STATE.matrixData.length; r++) {
      for (let c = 0; c < 72; c++) {
        detectorAverages[c] += APP_STATE.matrixData[r][c];
      }
    }
    for (let c = 0; c < 72; c++) {
      detectorAverages[c] = Math.round(detectorAverages[c] / APP_STATE.matrixData.length);
    }
  }

  for (let r = 0; r < rows; r++) {
    for (let c = 0; c < cols; c++) {
      const detIndex = r * cols + c;
      const x = c * cellW;
      const y = r * cellH;
      const avgVal = detectorAverages[detIndex] || 0;

      ctx.fillStyle = getColorForValue(avgVal, 350);
      ctx.fillRect(x + 1, y + 1, cellW - 2, cellH - 2);

      ctx.fillStyle = avgVal > 150 ? '#000000' : '#FFFFFF';
      ctx.font = 'bold 11px "IBM Plex Mono"';
      ctx.textAlign = 'center';
      ctx.fillText(`${avgVal.toFixed(0)}`, x + cellW / 2, y + cellH / 2 + 4);
      
      ctx.fillStyle = avgVal > 150 ? 'rgba(0,0,0,0.5)' : 'rgba(255,255,255,0.4)';
      ctx.font = '8px "IBM Plex Mono"';
      ctx.fillText(`D${detIndex + 1}`, x + 16, y + 12);
    }
  }
  ctx.textAlign = 'left';
}

// 8. 3D ISOMETRIC SOURCE LOCALIZATION
function render3DSourceViewport() {
  const canvas3D = document.getElementById('canvas3D');
  if (!canvas3D) return;
  const ctx3D = canvas3D.getContext('2d');
  const w = canvas3D.width;
  const h = canvas3D.height;
  const isLight = isLightMode();

  ctx3D.clearRect(0, 0, w, h);
  ctx3D.fillStyle = isLight ? '#f8fafc' : '#03060E';
  ctx3D.fillRect(0, 0, w, h);

  const originX = w * 0.28;
  const originY = h * 0.72;
  const scale = 0.26;

  function project3D(x, y, z) {
    const isoX = originX + (x * 0.7 - y * 0.7) * scale;
    const isoY = originY + (x * 0.2 + y * 0.2 - z * 0.8) * scale;
    return { px: isoX, py: isoY };
  }

  // 3D Bounding Cube Wireframe
  ctx3D.strokeStyle = isLight ? 'rgba(37, 99, 235, 0.35)' : 'rgba(59, 130, 246, 0.25)';
  ctx3D.lineWidth = 1;

  const corners = [
    project3D(0, -100, 0), project3D(800, -100, 0), project3D(800, 100, 0), project3D(0, 100, 0),
    project3D(0, -100, 400), project3D(800, -100, 400), project3D(800, 100, 400), project3D(0, 100, 400)
  ];

  ctx3D.beginPath();
  ctx3D.moveTo(corners[0].px, corners[0].py);
  ctx3D.lineTo(corners[1].px, corners[1].py);
  ctx3D.lineTo(corners[2].px, corners[2].py);
  ctx3D.lineTo(corners[3].px, corners[3].py);
  ctx3D.closePath();
  ctx3D.stroke();

  for (let i = 0; i < 4; i++) {
    ctx3D.beginPath();
    ctx3D.moveTo(corners[i].px, corners[i].py);
    ctx3D.lineTo(corners[i + 4].px, corners[i + 4].py);
    ctx3D.stroke();
  }

  ctx3D.beginPath();
  ctx3D.moveTo(corners[4].px, corners[4].py);
  ctx3D.lineTo(corners[5].px, corners[5].py);
  ctx3D.lineTo(corners[6].px, corners[6].py);
  ctx3D.lineTo(corners[7].px, corners[7].py);
  ctx3D.closePath();
  ctx3D.stroke();

  ctx3D.fillStyle = isLight ? '#475569' : '#64748B';
  ctx3D.font = '9px "IBM Plex Mono"';
  const pXLabel = project3D(820, 0, 0);
  ctx3D.fillText("X: 800cm", pXLabel.px, pXLabel.py);
  const pZLabel = project3D(0, 0, 420);
  ctx3D.fillText("Z: 400cm", pZLabel.px, pZLabel.py);

  // Active Linear Array Bar
  const dStart = project3D(0, 0, APP_STATE.currentHeight * 35);
  const dEnd = project3D(800, 0, APP_STATE.currentHeight * 35);
  ctx3D.strokeStyle = isLight ? '#059669' : '#34D399';
  ctx3D.lineWidth = 2.5;
  ctx3D.beginPath();
  ctx3D.moveTo(dStart.px, dStart.py);
  ctx3D.lineTo(dEnd.px, dEnd.py);
  ctx3D.stroke();

  // Estimated Source Sphere
  const src = APP_STATE.estimatedSource;
  const pSrc = project3D(src.x, src.y, src.z);
  const pBase = project3D(src.x, src.y, 0);

  ctx3D.strokeStyle = 'rgba(239, 68, 68, 0.4)';
  ctx3D.setLineDash([2, 2]);
  ctx3D.beginPath();
  ctx3D.moveTo(pSrc.px, pSrc.py);
  ctx3D.lineTo(pBase.px, pBase.py);
  ctx3D.stroke();
  ctx3D.setLineDash([]);

  ctx3D.fillStyle = 'rgba(239, 68, 68, 0.2)';
  ctx3D.beginPath();
  ctx3D.ellipse(pBase.px, pBase.py, 10, 4, 0, 0, Math.PI * 2);
  ctx3D.fill();

  const radialGlow = ctx3D.createRadialGradient(pSrc.px, pSrc.py, 2, pSrc.px, pSrc.py, 16);
  radialGlow.addColorStop(0, '#EF4444');
  radialGlow.addColorStop(0.5, 'rgba(239, 68, 68, 0.4)');
  radialGlow.addColorStop(1, 'rgba(239, 68, 68, 0)');
  ctx3D.fillStyle = radialGlow;
  ctx3D.beginPath();
  ctx3D.arc(pSrc.px, pSrc.py, 16, 0, Math.PI * 2);
  ctx3D.fill();

  ctx3D.fillStyle = '#EF4444';
  ctx3D.beginPath();
  ctx3D.arc(pSrc.px, pSrc.py, 5.5, 0, Math.PI * 2);
  ctx3D.fill();
  ctx3D.strokeStyle = '#FFFFFF';
  ctx3D.lineWidth = 1.5;
  ctx3D.stroke();
}

// 9. TOP VIEW X-Z CROSSHAIR RENDERER
function renderTopViewXZ() {
  const canvasTop = document.getElementById('canvasTopViewXZ');
  if (!canvasTop) return;
  const ctxTop = canvasTop.getContext('2d');
  const w = canvasTop.width;
  const h = canvasTop.height;
  const isLight = isLightMode();

  ctxTop.clearRect(0, 0, w, h);
  ctxTop.fillStyle = isLight ? '#f8fafc' : '#03060E';
  ctxTop.fillRect(0, 0, w, h);

  ctxTop.strokeStyle = isLight ? 'rgba(0, 0, 0, 0.05)' : 'rgba(255, 255, 255, 0.04)';
  ctxTop.lineWidth = 1;
  for (let x = 0; x < w; x += 40) {
    ctxTop.beginPath();
    ctxTop.moveTo(x, 0);
    ctxTop.lineTo(x, h);
    ctxTop.stroke();
  }
  for (let y = 0; y < h; y += 30) {
    ctxTop.beginPath();
    ctxTop.moveTo(0, y);
    ctxTop.lineTo(w, y);
    ctxTop.stroke();
  }

  const padding = 18;
  const plotW = w - padding * 2;
  const plotH = h - padding * 2;

  ctxTop.strokeStyle = isLight ? 'rgba(37, 99, 235, 0.4)' : 'rgba(59, 130, 246, 0.4)';
  ctxTop.strokeRect(padding, padding, plotW, plotH);

  const srcX = padding + (APP_STATE.estimatedSource.x / 800) * plotW;
  const srcZ = padding + ((350 - APP_STATE.estimatedSource.z) / 350) * plotH;

  ctxTop.strokeStyle = 'rgba(239, 68, 68, 0.6)';
  ctxTop.setLineDash([3, 3]);
  ctxTop.beginPath();
  ctxTop.moveTo(padding, srcZ);
  ctxTop.lineTo(w - padding, srcZ);
  ctxTop.moveTo(srcX, padding);
  ctxTop.lineTo(srcX, h - padding);
  ctxTop.stroke();
  ctxTop.setLineDash([]);

  ctxTop.fillStyle = '#EF4444';
  ctxTop.beginPath();
  ctxTop.arc(srcX, srcZ, 5.5, 0, Math.PI * 2);
  ctxTop.fill();
  ctxTop.strokeStyle = '#FFF';
  ctxTop.lineWidth = 1.5;
  ctxTop.stroke();
}

// 10. TOOLTIP & MOUSE INTERACTION
canvas.addEventListener('mousemove', (e) => {
  if (APP_STATE.viewMode !== 'matrix') return;

  const rect = canvas.getBoundingClientRect();
  const scaleX = canvas.width / rect.width;
  const scaleY = canvas.height / rect.height;

  const mouseX = (e.clientX - rect.left) * scaleX;
  const mouseY = (e.clientY - rect.top) * scaleY;

  const col = Math.floor(mouseX / (canvas.width / 72));
  const row = Math.floor(mouseY / (canvas.height / APP_STATE.totalHeights));

  if (col >= 0 && col < 72 && row >= 0 && row < APP_STATE.totalHeights) {
    hoveredCell = { row, col };
    renderMatrixHeatmap();

    const tooltip = document.getElementById('matrixTooltip');
    const detNumber = col + 1;
    let modName = "XS1 (0x01)";
    if (col >= 24 && col < 48) modName = "XS2 (0x02)";
    if (col >= 48) modName = "XS3 (0x03)";

    const isAvailable = row < APP_STATE.matrixData.length;
    const cpsVal = isAvailable ? APP_STATE.matrixData[row][col] : '--';
    const doseRate = isAvailable ? (cpsVal * 0.012).toFixed(2) : '--';
    const isHotspot = isAvailable && cpsVal >= APP_STATE.hotspotThreshold;

    document.getElementById('ttDetectorName').textContent = `Detector D${detNumber}`;
    document.getElementById('ttModuleTag').textContent = modName;
    document.getElementById('ttHeightLevel').textContent = `Loop ${row + 1} (${(row + 1) * 35} cm)`;
    document.getElementById('ttCoordinates').textContent = `${Math.round((col / 72) * 800)}cm, ${(row + 1) * 35}cm`;
    document.getElementById('ttCpsValue').textContent = isAvailable ? `${cpsVal} CPS` : 'No Data';
    document.getElementById('ttDoseRate').textContent = isAvailable ? `${doseRate} µSv/h` : '--';
    
    const ttStatus = document.getElementById('ttStatus');
    if (isHotspot) {
      ttStatus.textContent = 'HOTSPOT ALERT';
      ttStatus.className = 'tt-val hotspot';
    } else {
      ttStatus.textContent = 'NORMAL BG';
      ttStatus.className = 'tt-val';
      ttStatus.style.color = '#34D399';
    }

    tooltip.style.display = 'block';
    tooltip.style.left = `${e.clientX - rect.left}px`;
    tooltip.style.top = `${e.clientY - rect.top}px`;
  }
});

canvas.addEventListener('mouseleave', () => {
  hoveredCell = null;
  document.getElementById('matrixTooltip').style.display = 'none';
  renderMatrixHeatmap();
});

// 11. INDIVIDUAL 72-CHANNEL READOUTS & MICRO STRIP
function buildIndividualChannelsGrid() {
  const container = document.getElementById('individualChannelsContainer');
  if (!container) return;
  container.innerHTML = '';

  for (let i = 1; i <= 72; i++) {
    const chip = document.createElement('div');
    let modClass = 'xs1';
    if (i > 24 && i <= 48) modClass = 'xs2';
    if (i > 48) modClass = 'xs3';

    chip.className = `channel-readout-chip ${modClass}`;
    chip.id = `channelChip-${i}`;
    chip.innerHTML = `
      <span class="c-name">D${i < 10 ? '0' + i : i}</span>
      <span class="c-val" id="chipVal-${i}">--</span>
    `;
    container.appendChild(chip);
  }
}

function buildDetectorDotsGrid() {
  const grid = document.getElementById('detectorDotsGrid');
  if (!grid) return;
  grid.innerHTML = '';

  for (let i = 1; i <= 72; i++) {
    const dot = document.createElement('div');
    dot.className = 'det-dot';
    dot.id = `detDot-${i}`;
    dot.title = `D${i} (XS${i <= 24 ? 1 : (i <= 48 ? 2 : 3)})`;
    grid.appendChild(dot);
  }
}

function updateIndividualChannelsVisual(full72) {
  if (!full72 || full72.length < 72) return;

  let xs1Sum = 0, xs2Sum = 0, xs3Sum = 0;

  for (let i = 0; i < 72; i++) {
    const cps = full72[i];
    const detNum = i + 1;
    
    // Update numerical value chip
    const valEl = document.getElementById(`chipVal-${detNum}`);
    const chipEl = document.getElementById(`channelChip-${detNum}`);
    if (valEl) valEl.textContent = `${cps}`;
    if (chipEl) {
      if (cps >= APP_STATE.hotspotThreshold) chipEl.classList.add('hotspot');
      else chipEl.classList.remove('hotspot');
    }

    // Update micro activity dot
    const dot = document.getElementById(`detDot-${detNum}`);
    if (dot) {
      dot.style.backgroundColor = getColorForValue(cps, 350);
      if (cps >= APP_STATE.hotspotThreshold) {
        dot.style.boxShadow = '0 0 6px #F43F5E';
      } else {
        dot.style.boxShadow = 'none';
      }
    }

    if (i < 24) xs1Sum += cps;
    else if (i < 48) xs2Sum += cps;
    else xs3Sum += cps;
  }

  document.getElementById('modAvgXS1').textContent = `${Math.round(xs1Sum / 24)} CPS`;
  document.getElementById('modAvgXS2').textContent = `${Math.round(xs2Sum / 24)} CPS`;
  document.getElementById('modAvgXS3').textContent = `${Math.round(xs3Sum / 24)} CPS`;
}

// 12. SCANNING ENGINE & LIVE DATA GENERATION
function generateDummyModuleData(moduleId, isHotspot) {
  const data = [];
  for (let ch = 0; ch < 24; ch++) {
    let base = Math.floor(Math.random() * 25) + 18;
    if (isHotspot && moduleId === 2) {
      const dist = Math.abs(ch - 14); // peak at XS2 ch 14 (D38)
      if (dist <= 5) {
        const boost = Math.floor((Math.random() * 140 + 160) * Math.max(0.2, (1 - dist / 6)));
        base += boost;
      } else {
        base += Math.floor(Math.random() * 35) + 20;
      }
    } else if (isHotspot && (moduleId === 1 && ch > 18 || moduleId === 3 && ch < 6)) {
      base += Math.floor(Math.random() * 30) + 10;
    }
    data.push(base);
  }
  return data;
}

// ==========================================
// 12. MODAL DIALOG & CONFIGURATION HANDLERS
// ==========================================
function openStartScanModal() {
  if (APP_STATE.isScanning && !APP_STATE.isPaused) return;

  if (APP_STATE.isPaused) {
    pauseScanning();
    return;
  }

  // Pre-fill modal inputs with currently selected TiDB object
  const selectObj = document.getElementById('selectActiveObject');
  let activeName = (selectObj && selectObj.value && selectObj.value !== 'ALL') ? selectObj.value : APP_STATE.objectName;
  if (!activeName || activeName === 'ALL') activeName = 'Tong Baru';

  const nameInput = document.getElementById('modalInputObjectName');
  const stepsInput = document.getElementById('modalInputSteps');
  const loopsInput = document.getElementById('modalInputLoops');
  const delayInput = document.getElementById('modalInputTransitionDelay');
  const portInput = document.getElementById('modalInputComPort');
  const rateInput = document.getElementById('modalInputSamplingRate');

  if (nameInput) nameInput.value = activeName;
  if (stepsInput) stepsInput.value = APP_STATE.totalHeights || 10;
  if (loopsInput) loopsInput.value = APP_STATE.totalLoops || 1;
  if (delayInput) delayInput.value = APP_STATE.transitionDelay || 1.5;
  if (portInput) portInput.value = APP_STATE.selectedComPort || 'COM5';
  if (rateInput) rateInput.value = APP_STATE.samplingIntervalMs || 1000;

  // Highlight matching preset tag if any
  document.querySelectorAll('#modalPresetTagList .preset-tag').forEach(tag => {
    if (tag.textContent.startsWith(activeName)) tag.classList.add('active');
    else tag.classList.remove('active');
  });

  const modal = document.getElementById('modalStartScanConfig');
  if (modal) modal.style.display = 'flex';
}

function closeStartScanModal() {
  const modal = document.getElementById('modalStartScanConfig');
  if (modal) modal.style.display = 'none';
}

function selectObjectPreset(name) {
  const input = document.getElementById('modalInputObjectName');
  if (input) input.value = name;

  document.querySelectorAll('#modalPresetTagList .preset-tag').forEach(tag => {
    if (tag.textContent.startsWith(name)) tag.classList.add('active');
    else tag.classList.remove('active');
  });
}

function launchConfiguredScan() {
  const nameInput = document.getElementById('modalInputObjectName');
  const stepsInput = document.getElementById('modalInputSteps');
  const loopsInput = document.getElementById('modalInputLoops');
  const delayInput = document.getElementById('modalInputTransitionDelay');
  const portInput = document.getElementById('modalInputComPort');
  const rateInput = document.getElementById('modalInputSamplingRate');

  const objName = (nameInput && nameInput.value.trim()) ? nameInput.value.trim() : 'Gentong';
  const steps = (stepsInput && parseInt(stepsInput.value)) ? Math.max(1, parseInt(stepsInput.value)) : 10;
  const loops = (loopsInput && parseInt(loopsInput.value)) ? Math.max(1, parseInt(loopsInput.value)) : 1;
  const transDelay = (delayInput && !isNaN(parseFloat(delayInput.value))) ? Math.max(0, parseFloat(delayInput.value)) : 1.5;
  const port = (portInput && portInput.value) ? portInput.value : 'COM5';
  const rate = (rateInput && parseInt(rateInput.value)) ? parseInt(rateInput.value) : 1000;

  APP_STATE.objectName = objName;
  APP_STATE.totalHeights = steps;
  APP_STATE.totalLoops = loops;
  APP_STATE.transitionDelay = transDelay;
  APP_STATE.selectedComPort = port;
  APP_STATE.samplingIntervalMs = rate;

  closeStartScanModal();

  // Update HUD displays
  const selectObj = document.getElementById('selectActiveObject');
  if (selectObj) {
    let exists = false;
    for (let i = 0; i < selectObj.options.length; i++) {
      if (selectObj.options[i].value === objName) {
        exists = true;
        break;
      }
    }
    if (!exists) {
      const opt = new Option(objName + ' (Sesi Baru)', objName, true, true);
      selectObj.add(opt);
    }
    selectObj.value = objName;
  }
  const elHeight = document.getElementById('headerHeightProgress');
  if (elHeight) elHeight.textContent = `0 / ${steps}`;
  const elLoop = document.getElementById('headerLoopProgress');
  if (elLoop) elLoop.textContent = `1 / ${loops}`;
  const elPort = document.getElementById('headerComPort');
  if (elPort) elPort.textContent = port;

  // Sync Quick Inputs
  const qSteps = document.getElementById('inputTotalHeight');
  if (qSteps) qSteps.value = steps;
  const qLoops = document.getElementById('inputTotalLoops');
  if (qLoops) qLoops.value = loops;
  const qDelay = document.getElementById('inputTransitionDelay');
  if (qDelay) qDelay.value = transDelay;
  const qPort = document.getElementById('inputComPort');
  if (qPort) qPort.value = port;
  const qRate = document.getElementById('inputSamplingRate');
  if (qRate) qRate.value = rate;

  buildSidebarLoopList();

  // Start actual acquisition
  startScanning(true);
}

// ==========================================
// 13. DYNAMIC SAVE BUTTON & TOAST NOTIFICATIONS
// ==========================================
function setSaveButtonState(state) {
  APP_STATE.saveState = state;
  const btn = document.getElementById('btnSaveSession');
  const label = document.getElementById('saveBtnLabel');
  const icon = document.getElementById('saveBtnIcon');
  if (!btn || !label) return;

  btn.classList.remove('btn-save-idle', 'btn-save-scanning', 'btn-save-ready', 'btn-save-saved');

  if (state === 'scanning') {
    btn.classList.add('btn-save-scanning');
    btn.disabled = true;
    label.textContent = 'SCANNING... (TERKUNCI)';
    if (icon) {
      icon.innerHTML = '<rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path>';
    }
  } else if (state === 'ready') {
    btn.classList.add('btn-save-ready');
    btn.disabled = false;
    label.textContent = 'SIMPAN HASIL SCAN';
    if (icon) {
      icon.innerHTML = '<path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path><polyline points="17 21 17 13 7 13 7 21"></polyline><polyline points="7 3 7 8 15 8"></polyline>';
    }
  } else if (state === 'saved') {
    btn.classList.add('btn-save-saved');
    btn.disabled = true;
    label.textContent = 'HASIL TERSIMPAN';
    if (icon) {
      icon.innerHTML = '<polyline points="20 6 9 17 4 12"></polyline>';
    }
  } else {
    // idle
    btn.classList.add('btn-save-idle');
    btn.disabled = true;
    label.textContent = 'SIMPAN HASIL';
    if (icon) {
      icon.innerHTML = '<path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path><polyline points="17 21 17 13 7 13 7 21"></polyline><polyline points="7 3 7 8 15 8"></polyline>';
    }
  }
}

function showToast(type, title, message, showSaveAction = false) {
  const container = document.getElementById('toastNotificationContainer');
  if (!container) return;

  const toastId = 'toast_' + Date.now() + '_' + Math.floor(Math.random() * 1000);
  const toast = document.createElement('div');
  toast.className = `toast-card toast-${type}`;
  toast.id = toastId;

  let iconSvg = '';
  if (type === 'success') {
    iconSvg = '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>';
  } else if (type === 'warning') {
    iconSvg = '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>';
  } else {
    iconSvg = '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>';
  }

  let actionsHtml = '';
  if (showSaveAction && APP_STATE.saveState === 'ready') {
    actionsHtml = `
      <div class="toast-actions">
        <button class="btn-toast-primary" onclick="saveScanSession(); removeToast('${toastId}')">💾 Simpan Sekarang</button>
        <button class="btn-toast-secondary" onclick="removeToast('${toastId}')">Nanti</button>
      </div>
    `;
  }

  toast.innerHTML = `
    <div class="toast-icon">${iconSvg}</div>
    <div class="toast-content">
      <div class="toast-title">${title}</div>
      <div class="toast-desc">${message}</div>
      ${actionsHtml}
    </div>
    <button class="toast-close" onclick="removeToast('${toastId}')">&times;</button>
  `;

  container.appendChild(toast);

  setTimeout(() => {
    removeToast(toastId);
  }, 9000);
}

function removeToast(toastId) {
  const el = document.getElementById(toastId);
  if (el) {
    el.style.opacity = '0';
    el.style.transform = 'translateX(40px) scale(0.9)';
    setTimeout(() => {
      if (el && el.parentNode) el.parentNode.removeChild(el);
    }, 250);
  }
}

function saveScanSession() {
  if (APP_STATE.sessionRecords.length === 0) {
    showToast('warning', 'Data Kosong', 'Tidak ada rekaman data pemindaian untuk disimpan.');
    return;
  }

  const btn = document.getElementById('btnSaveSession');
  const label = document.getElementById('saveBtnLabel');
  if (btn) btn.disabled = true;
  if (label) label.textContent = 'MENYIMPAN...';

  const payload = {
    object_name: APP_STATE.objectName,
    session_id: APP_STATE.sessionId,
    total_loops: APP_STATE.totalLoops,
    transition_delay: APP_STATE.transitionDelay,
    records: APP_STATE.sessionRecords
  };

  fetch('/matrix-data/save-session', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'X-CSRF-TOKEN': '{{ csrf_token() }}'
    },
    body: JSON.stringify(payload)
  })
  .then(res => res.json())
  .then(data => {
    if (data.status === 'success') {
      setSaveButtonState('saved');
      logTerminal(`<span class="log-badge-ok">[DB SAVE]</span> Sesi objek <strong>'${APP_STATE.objectName}'</strong> (${data.count} baris) berhasil disimpan ke <strong>${data.db_target === 'tidb_cloud' ? 'TiDB Cloud' : 'SQLite'}</strong>!`);
      showToast('success', 'Penyimpanan Berhasil!', `Data pemindaian <b>${APP_STATE.objectName}</b> (${data.count} record) berhasil disimpan ke <b>${data.db_target === 'tidb_cloud' ? 'TiDB Cloud' : 'Database'}</b>!`);
      updateTimestampDisplay(new Date().toLocaleTimeString(), data.db_target === 'tidb_cloud' ? 'TiDB CLOUD' : 'LOCAL DB');
      fetchInitialHistory();
    } else {
      setSaveButtonState('ready');
      showToast('warning', 'Gagal Menyimpan', data.message || 'Terjadi kesalahan saat menyimpan ke database.');
    }
  })
  .catch(err => {
    console.error('[Save Session Error]', err);
    setSaveButtonState('ready');
    showToast('warning', 'Koneksi Terputus', 'Gagal mengirim data ke server. Silakan coba lagi.');
  });
}

// ==========================================
// 14. SCANNING ENGINE WITH MULTI-LOOP & TRANSITION DELAY
// ==========================================
function startScanning(fromModal = false) {
  if (APP_STATE.isScanning && !APP_STATE.isPaused) return;

  if (APP_STATE.isPaused) {
    APP_STATE.isPaused = false;
    document.getElementById('btnPauseScan').classList.remove('btn-start');
    document.getElementById('pauseBtnLabel').textContent = 'PAUSE';
    logTerminal('<span class="log-badge-ok">[RESUMED]</span> Scanning sequence continued.');
  } else {
    if (!fromModal) {
      openStartScanModal();
      return;
    }

    APP_STATE.isScanning = true;
    APP_STATE.currentHeight = 1;
    APP_STATE.currentLoop = 1;
    APP_STATE.matrixData = [];
    APP_STATE.sessionRecords = [];
    APP_STATE.xs1History = [];
    APP_STATE.xs2History = [];
    APP_STATE.xs3History = [];
    APP_STATE.timestamps = [];
    APP_STATE.elapsedSeconds = 0;
    APP_STATE.isTransitioning = false;
    APP_STATE.sessionId = 'SES_' + new Date().toISOString().replace(/\D/g, '').substring(0, 14) + '_' + Math.floor(Math.random() * 900 + 100);

    // Save button turns YELLOW & LOCKED
    setSaveButtonState('scanning');

    document.getElementById('btnStartScan').disabled = true;
    document.getElementById('btnPauseScan').disabled = false;
    document.getElementById('btnStopScan').disabled = false;
    document.getElementById('headerHeightProgress').textContent = `1 / ${APP_STATE.totalHeights}`;
    document.getElementById('headerLoopProgress').textContent = `1 / ${APP_STATE.totalLoops}`;

    logTerminal(`<span class="log-badge-ok">[SCAN START]</span> Target: <strong>${APP_STATE.objectName}</strong> | ${APP_STATE.totalHeights} Steps &times; ${APP_STATE.totalLoops} Loops | Delay Transisi: ${APP_STATE.transitionDelay}s`);
  }

  // Start Elapsed Timer
  clearInterval(APP_STATE.timerIntervalId);
  APP_STATE.timerIntervalId = setInterval(() => {
    if (!APP_STATE.isPaused) {
      APP_STATE.elapsedSeconds++;
      const mins = String(Math.floor(APP_STATE.elapsedSeconds / 60)).padStart(2, '0');
      const secs = String(APP_STATE.elapsedSeconds % 60).padStart(2, '0');
      document.getElementById('metricElapsedTimer').textContent = `Elapsed: ${mins}:${secs}`;
    }
  }, 1000);

  // Trigger first cycle immediately then set interval
  clearInterval(APP_STATE.scanIntervalId);
  executeScanCycle();
}

function executeScanCycle() {
  if (!APP_STATE.isScanning || APP_STATE.isPaused || APP_STATE.isTransitioning) return;

  const h = APP_STATE.currentHeight;
  const l = APP_STATE.currentLoop;

  if (h > APP_STATE.totalHeights) {
    stopScanning(true);
    return;
  }

  const hasAnomaly = (h === 4 || h === 5 || h === 6);
  const d1 = generateDummyModuleData(1, hasAnomaly);
  const d2 = generateDummyModuleData(2, hasAnomaly);
  const d3 = generateDummyModuleData(3, hasAnomaly);
  const full72 = [...d1, ...d2, ...d3];

  // Update heatmap matrix representation
  if (APP_STATE.matrixData.length < h) {
    APP_STATE.matrixData.push(full72);
  } else {
    APP_STATE.matrixData[h - 1] = full72;
  }

  // Compute Averages
  const avg1 = d1.reduce((a, b) => a + b, 0) / 24;
  const avg2 = d2.reduce((a, b) => a + b, 0) / 24;
  const avg3 = d3.reduce((a, b) => a + b, 0) / 24;
  const maxCps = Math.max(...full72);
  const maxChannelIndex = full72.indexOf(maxCps) + 1;
  const totalCps = full72.reduce((a, b) => a + b, 0);
  const globalAvg = (totalCps / 72).toFixed(1);
  const nowTs = new Date().toISOString().replace('T', ' ').substring(0, 19);

  // Store in sessionRecords for batch/single save
  APP_STATE.sessionRecords.push({
    timestamp: nowTs,
    height: h,
    loop_index: l,
    detector_data: full72,
    xs1_data: d1,
    xs2_data: d2,
    xs3_data: d3,
    max_cps: maxCps,
    avg_cps: parseFloat(globalAvg)
  });

  // Update UI Stats & HUD
  document.getElementById('headerHeightProgress').textContent = `${h} / ${APP_STATE.totalHeights}`;
  document.getElementById('headerLoopProgress').textContent = `${l} / ${APP_STATE.totalLoops}`;
  document.getElementById('metricActiveLoop').innerHTML = `${h} <span class="kpi-unit">/ ${APP_STATE.totalHeights}</span>`;
  document.getElementById('metricMaxCps').innerHTML = `${maxCps} <span class="kpi-unit">cps</span>`;
  document.getElementById('metricMaxChannel').textContent = `Detector D${maxChannelIndex} (XS${maxChannelIndex <= 24 ? 1 : (maxChannelIndex <= 48 ? 2 : 3)})`;
  document.getElementById('metricAvgCps').innerHTML = `${globalAvg} <span class="kpi-unit">cps</span>`;
  document.getElementById('metricTotalCounts').innerHTML = `${totalCps.toLocaleString()} <span class="kpi-unit">cts</span>`;

  // Update Estimated Source Coordinates
  if (hasAnomaly || maxCps > 100) {
    APP_STATE.estimatedSource = {
      x: 416.4 + (Math.random() * 4 - 2),
      y: -32.4 + (Math.random() * 2 - 1),
      z: (h * 35) + (Math.random() * 4 - 2),
      confidence: Math.min(98.8, 85.0 + (maxCps / 350) * 13).toFixed(1)
    };
    document.getElementById('statPosX').textContent = `${APP_STATE.estimatedSource.x.toFixed(1)} cm`;
    document.getElementById('statPosY').textContent = `${APP_STATE.estimatedSource.y.toFixed(1)} cm`;
    document.getElementById('statPosZ').textContent = `${APP_STATE.estimatedSource.z.toFixed(1)} cm`;
    document.getElementById('statConfidence').textContent = `${APP_STATE.estimatedSource.confidence}% Confidence`;
    document.getElementById('statConfidenceVal').textContent = `${APP_STATE.estimatedSource.confidence}%`;
    document.getElementById('statHotspotCount').textContent = `XS2 (D${maxChannelIndex})`;
  }

  // Update Real-time Chart
  const timeLabel = new Date().toLocaleTimeString();
  telemetryChart.data.labels.push(timeLabel);
  telemetryChart.data.datasets[0].data.push(avg1);
  telemetryChart.data.datasets[1].data.push(avg2);
  telemetryChart.data.datasets[2].data.push(avg3);

  if (telemetryChart.data.labels.length > 20) {
    telemetryChart.data.labels.shift();
    telemetryChart.data.datasets.forEach(ds => ds.data.shift());
  }
  telemetryChart.update();

  // Update individual 72 channels & micro strip
  updateIndividualChannelsVisual(full72);

  // Log to Terminal Screen
  const statusTag = maxCps >= APP_STATE.hotspotThreshold ? '<span class="log-badge-alert">[HOTSPOT]</span>' : '<span class="log-badge-ok">[NORMAL]</span>';
  logTerminal(`[Tinggi:${h}/${APP_STATE.totalHeights} | Loop:${l}/${APP_STATE.totalLoops}] ${statusTag} Peak ${maxCps} CPS (D${maxChannelIndex})`);

  // Push to Firebase RTDB if active
  if (APP_STATE.useFirebase && firebaseDb) {
    pushToFirebase(h, l, full72, d1, d2, d3, maxCps, maxChannelIndex, globalAvg);
  }

  // Highlight Sidebar Loop item
  const loopItem = document.getElementById(`loopItem-${h}`);
  if (loopItem) {
    loopItem.classList.add('active');
    if (hasAnomaly) loopItem.classList.add('has-hotspot');
  }

  // Re-render Views
  renderMatrixHeatmap();
  render3DSourceViewport();
  renderTopViewXZ();
  updateRawTable();

  // Schedule Next Step (Multi-loop or Transition Delay)
  if (l < APP_STATE.totalLoops) {
    // Next loop on the same height
    APP_STATE.currentLoop++;
    APP_STATE.scanIntervalId = setTimeout(executeScanCycle, APP_STATE.samplingIntervalMs);
  } else {
    // Current height finished all loops
    if (h < APP_STATE.totalHeights) {
      // Transition to next height step
      if (APP_STATE.transitionDelay > 0) {
        APP_STATE.isTransitioning = true;
        const transChip = document.getElementById('hudTransitionChip');
        const transText = document.getElementById('headerTransitionText');
        if (transChip) transChip.style.display = 'inline-flex';
        if (transText) transText.textContent = `Transisi Lift Baris ${h} ➔ ${h+1} (${APP_STATE.transitionDelay}s)...`;

        logTerminal(`<span class="log-badge-warn">[TRANSISI LIFT]</span> Selesai baris ${h} (${APP_STATE.totalLoops} loop). Menunggu jeda transisi lift ${APP_STATE.transitionDelay}s sebelum baris ${h+1}...`);

        APP_STATE.scanIntervalId = setTimeout(() => {
          APP_STATE.isTransitioning = false;
          if (transChip) transChip.style.display = 'none';
          APP_STATE.currentHeight = h + 1;
          APP_STATE.currentLoop = 1;
          executeScanCycle();
        }, APP_STATE.transitionDelay * 1000);
      } else {
        APP_STATE.currentHeight = h + 1;
        APP_STATE.currentLoop = 1;
        APP_STATE.scanIntervalId = setTimeout(executeScanCycle, APP_STATE.samplingIntervalMs);
      }
    } else {
      // All heights and all loops completed!
      stopScanning(true);
    }
  }
}

function pauseScanning() {
  if (!APP_STATE.isScanning) return;
  APP_STATE.isPaused = !APP_STATE.isPaused;

  const btn = document.getElementById('btnPauseScan');
  const label = document.getElementById('pauseBtnLabel');

  if (APP_STATE.isPaused) {
    label.textContent = 'RESUME';
    btn.classList.add('btn-start');
    logTerminal('<span class="log-badge-warn">[PAUSED]</span> Acquisition suspended.');
  } else {
    label.textContent = 'PAUSE';
    btn.classList.remove('btn-start');
    logTerminal('<span class="log-badge-ok">[RESUMED]</span> Acquisition active.');
    executeScanCycle();
  }
}

function stopScanning(isCompleted = false) {
  clearTimeout(APP_STATE.scanIntervalId);
  clearInterval(APP_STATE.scanIntervalId);
  clearInterval(APP_STATE.timerIntervalId);
  APP_STATE.isScanning = false;
  APP_STATE.isPaused = false;
  APP_STATE.isTransitioning = false;

  const transChip = document.getElementById('hudTransitionChip');
  if (transChip) transChip.style.display = 'none';

  document.getElementById('btnStartScan').disabled = false;
  document.getElementById('btnPauseScan').disabled = true;
  document.getElementById('btnStopScan').disabled = true;
  document.getElementById('pauseBtnLabel').textContent = 'PAUSE';
  document.getElementById('btnPauseScan').classList.remove('btn-start');

  if (APP_STATE.sessionRecords.length > 0) {
    // Tombol Simpan berubah menjadi HIJAU & UNLOCKED
    setSaveButtonState('ready');

    if (isCompleted) {
      logTerminal(`<span class="log-badge-ok">[SELESAI]</span> Pemindaian target '<strong>${APP_STATE.objectName}</strong>' selesai (${APP_STATE.sessionRecords.length} record). Tombol simpan aktif (HIJAU).`);
      showToast('success', 'Pemindaian Selesai!', `Pemindaian objek <b>${APP_STATE.objectName}</b> telah selesai (${APP_STATE.sessionRecords.length} record). Silakan klik <b>SIMPAN HASIL SCAN</b> untuk menyimpan ke database.`, true);
    } else {
      logTerminal(`<span class="log-badge-warn">[BERHENTI MANUAL]</span> Pemindaian dihentikan. (${APP_STATE.sessionRecords.length} record terkumpul). Tombol simpan aktif (HIJAU).`);
      showToast('warning', 'Pemindaian Dihentikan', `Pemindaian objek <b>${APP_STATE.objectName}</b> dihentikan (${APP_STATE.sessionRecords.length} record). Anda dapat menyimpan data ini sekarang.`, true);
    }
  } else {
    setSaveButtonState('idle');
    logTerminal('<span class="log-badge-warn">[RESET]</span> Scanner state cleared.');
  }
}

// 15. FIREBASE REALTIME PUSH & SYNC
function pushToFirebase(height, loopIdx, fullArray, d1, d2, d3, maxCps, maxCh, avgCps) {
  const timestamp = new Date().toISOString();
  const startTime = Date.now();

  try {
    firebaseDb.ref('radiation_scans/latest').set({
      timestamp: timestamp,
      object_name: APP_STATE.objectName,
      session_id: APP_STATE.sessionId,
      current_height: height,
      total_height: APP_STATE.totalHeights,
      current_loop: loopIdx,
      total_loops: APP_STATE.totalLoops,
      transition_delay: APP_STATE.transitionDelay,
      xs1_data: d1,
      xs2_data: d2,
      xs3_data: d3,
      full_72_array: fullArray,
      max_cps: maxCps,
      max_channel: `D${maxCh}`,
      avg_cps: parseFloat(avgCps),
      estimated_source_3d: APP_STATE.estimatedSource,
      status: maxCps >= APP_STATE.hotspotThreshold ? 'alert' : 'safe'
    }).then(() => {
      const latency = Date.now() - startTime;
      const elLatency = document.getElementById('syncLatencyText');
      if (elLatency) elLatency.textContent = `${latency}ms`;
    });
  } catch (e) {
    console.warn("[Firebase Push Error]", e);
  }
}

function updateTotalLoops(val) {
  APP_STATE.totalLoops = Math.max(1, parseInt(val) || 1);
  const el = document.getElementById('headerLoopProgress');
  if (el) el.textContent = `1 / ${APP_STATE.totalLoops}`;
}

function updateTransitionDelay(val) {
  APP_STATE.transitionDelay = Math.max(0, parseFloat(val) || 1.5);
}

function toggleFirebaseSync() {
  APP_STATE.useFirebase = !APP_STATE.useFirebase;
  const toggleEl = document.getElementById('fbSyncToggle');
  if (APP_STATE.useFirebase) {
    toggleEl.classList.add('active');
    logTerminal('<span class="log-badge-ok">[FIREBASE]</span> Realtime Cloud Sync On.');
  } else {
    toggleEl.classList.remove('active');
    logTerminal('<span class="log-badge-warn">[FIREBASE]</span> Offline Mode (Local).');
  }
}

// 14. SIDEBAR LOOPS & PARAMETER CONTROLS
function buildSidebarLoopList() {
  const container = document.getElementById('loopListContainer');
  container.innerHTML = '';
  APP_STATE.selectedLoops.clear();

  for (let i = 1; i <= APP_STATE.totalHeights; i++) {
    APP_STATE.selectedLoops.add(i);
    const item = document.createElement('div');
    item.className = 'loop-row';
    item.id = `loopItem-${i}`;
    item.innerHTML = `
      <div class="loop-left">
        <input type="checkbox" class="loop-checkbox" checked onchange="toggleLoopSelection(${i}, this.checked)">
        <span class="loop-name">Loop ${i < 10 ? '0' + i : i}</span>
      </div>
      <span class="loop-height-tag">${i * 35} cm</span>
    `;
    container.appendChild(item);
  }
  document.getElementById('sidebarLoopCountText').textContent = `${APP_STATE.totalHeights} Loops`;
}

function toggleLoopSelection(loopNum, isChecked) {
  if (isChecked) {
    APP_STATE.selectedLoops.add(loopNum);
  } else {
    APP_STATE.selectedLoops.delete(loopNum);
  }
  renderMatrixHeatmap();
}

function selectAllLoops(shouldSelect) {
  const checkboxes = document.querySelectorAll('#loopListContainer input[type="checkbox"]');
  checkboxes.forEach((cb, idx) => {
    cb.checked = shouldSelect;
    if (shouldSelect) APP_STATE.selectedLoops.add(idx + 1);
    else APP_STATE.selectedLoops.delete(idx + 1);
  });
  renderMatrixHeatmap();
}

function updateTotalHeights(val) {
  APP_STATE.totalHeights = parseInt(val) || 10;
  buildSidebarLoopList();
  document.getElementById('headerHeightProgress').textContent = `0 / ${APP_STATE.totalHeights}`;
  renderMatrixHeatmap();
}

function updateComPort(val) {
  if (!val) return;
  APP_STATE.selectedComPort = val;
  const headerPort = document.getElementById('headerComPort');
  if (headerPort) headerPort.textContent = val;
  const sel = document.getElementById('inputComPort');
  if (sel && sel.value !== val) sel.value = val;

  try { localStorage.setItem('radioscan_com_port', val); } catch(e) {}
  logTerminal(`<span class="log-badge-ok">[PORT]</span> Active Hardware Port set to <strong>${val}</strong>`);

  if (APP_STATE.useFirebase && firebaseDb) {
    try {
      firebaseDb.ref('radiation_scans/hardware_config/port').set(val);
      firebaseDb.ref('radiation_scans/hardware_config/last_updated').set(new Date().toISOString());
    } catch(err) {
      console.warn("[Firebase] Could not sync COM port:", err);
    }
  }
}

function updateBaudrate(val) {
  const baud = parseInt(val) || 115200;
  APP_STATE.selectedBaudrate = baud;
  const headerBaud = document.getElementById('headerBaudrate');
  if (headerBaud) headerBaud.textContent = baud;
  const selBaud = document.getElementById('inputBaudrate');
  if (selBaud && parseInt(selBaud.value) !== baud) selBaud.value = baud;

  try { localStorage.setItem('radioscan_baud_rate', baud); } catch(e) {}
  logTerminal(`<span class="log-badge-ok">[BAUD]</span> Baudrate configured: <strong>${baud}</strong> bps`);

  if (APP_STATE.useFirebase && firebaseDb) {
    try {
      firebaseDb.ref('radiation_scans/hardware_config/baudrate').set(baud);
      firebaseDb.ref('radiation_scans/hardware_config/last_updated').set(new Date().toISOString());
    } catch(err) {
      console.warn("[Firebase] Could not sync Baudrate:", err);
    }
  }
}

function updateSamplingInterval(val) {
  APP_STATE.samplingIntervalMs = parseInt(val) || 1000;
  const hz = (1000 / APP_STATE.samplingIntervalMs).toFixed(1);
  document.getElementById('liveStatusText').textContent = `${hz} Hz`;
  logTerminal(`<span class="log-badge-ok">[CONFIG]</span> Sampling rate set to <strong>${hz} Hz</strong> (${val}ms)`);

  if (APP_STATE.useFirebase && firebaseDb) {
    try {
      firebaseDb.ref('radiation_scans/hardware_config/sampling_interval_ms').set(APP_STATE.samplingIntervalMs);
    } catch(e) {}
  }

  if (APP_STATE.isScanning && !APP_STATE.isPaused) {
    clearInterval(APP_STATE.scanIntervalId);
    APP_STATE.scanIntervalId = setInterval(executeScanCycle, APP_STATE.samplingIntervalMs);
  }
}

function updateThreshold(val) {
  APP_STATE.hotspotThreshold = parseInt(val) || 100;
  renderMatrixHeatmap();
}

function changeColorPalette(palette) {
  APP_STATE.currentPalette = palette;
  updateColorbarGradient();
  renderMatrixHeatmap();
}

function switchViewMode(mode) {
  APP_STATE.viewMode = mode;
  document.querySelectorAll('.tab-pill-btn').forEach(btn => btn.classList.remove('active'));

  if (mode === 'matrix') document.getElementById('tabBtnMatrix').classList.add('active');
  if (mode === 'contour') document.getElementById('tabBtnContour').classList.add('active');
  if (mode === 'grid') document.getElementById('tabBtnGrid').classList.add('active');
  if (mode === 'table') document.getElementById('tabBtnRawTable').classList.add('active');

  const canvasStage = document.getElementById('canvasContainer');
  const tableStage = document.getElementById('matrixTableView');

  if (mode === 'table') {
    canvasStage.style.display = 'none';
    tableStage.style.display = 'block';
    updateRawTable();
  } else {
    canvasStage.style.display = 'block';
    tableStage.style.display = 'none';
    renderMatrixHeatmap();
  }
}

// 15. RAW MATRIX TABLE & EXPORTS
function updateRawTable() {
  const headerRow = document.getElementById('tableHeaderRow');
  const tbody = document.getElementById('tableBodyRows');

  if (headerRow.children.length === 0) {
    let thHtml = '<th>Height</th>';
    for (let c = 1; c <= 72; c++) {
      thHtml += `<th>D${c < 10 ? '0' + c : c}</th>`;
    }
    headerRow.innerHTML = thHtml;
  }

  tbody.innerHTML = '';
  APP_STATE.matrixData.forEach((row, hIdx) => {
    let tr = `<tr><td><strong style="color:#60A5FA;">L${hIdx + 1}</strong></td>`;
    row.forEach(cps => {
      const cls = cps >= APP_STATE.hotspotThreshold ? 'class="cell-hotspot"' : '';
      tr += `<td ${cls}>${cps}</td>`;
    });
    tr += '</tr>';
    tbody.innerHTML += tr;
  });
}

function exportMatrixCsv() {
  if (APP_STATE.matrixData.length === 0) {
    alert("No matrix scan data available yet. Please start a scan first!");
    return;
  }

  let csvContent = "data:text/csv;charset=utf-8,Height";
  for (let i = 1; i <= 72; i++) {
    csvContent += `,Det_${i < 10 ? '0' + i : i}`;
  }
  csvContent += "\n";

  APP_STATE.matrixData.forEach((row, idx) => {
    csvContent += `Tinggi_${idx + 1},` + row.join(",") + "\n";
  });

  const encodedUri = encodeURI(csvContent);
  const link = document.createElement("a");
  link.setAttribute("href", encodedUri);
  link.setAttribute("download", `heatmap_radiation_matrix_${Date.now()}.csv`);
  document.body.appendChild(link);
  link.click();
  document.body.removeChild(link);

  logTerminal('<span class="log-badge-ok">[EXPORT]</span> CSV file saved.');
}

function exportMatrixDat() {
  if (APP_STATE.matrixData.length === 0) {
    alert("No matrix scan data available yet. Please start a scan first!");
    return;
  }

  let datContent = "Height " + Array.from({length: 72}, (_, i) => `Det_${i+1}`).join(" ") + "\n";
  APP_STATE.matrixData.forEach((row, idx) => {
    datContent += `Tinggi_${idx + 1} ` + row.join(" ") + "\n";
  });

  const blob = new Blob([datContent], { type: 'text/plain' });
  const link = document.createElement("a");
  link.href = URL.createObjectURL(blob);
  link.download = `heatmap_radiation_matrix_${Date.now()}.dat`;
  document.body.appendChild(link);
  link.click();
  document.body.removeChild(link);

  logTerminal('<span class="log-badge-ok">[EXPORT]</span> .DAT file exported.');
}

// 16. TERMINAL SCREEN LOGGER
function logTerminal(htmlMessage) {
  const terminal = document.getElementById('terminalScreen');
  if (!terminal) return;
  const timeStr = new Date().toLocaleTimeString();
  const line = document.createElement('div');
  line.className = 'log-entry';
  line.innerHTML = `<span class="log-t">[${timeStr}]</span> ${htmlMessage}`;
  terminal.appendChild(line);
  terminal.scrollTop = terminal.scrollHeight;
}

// 17. INITIALIZATION ON PAGE LOAD
document.addEventListener('DOMContentLoaded', () => {
  buildSidebarLoopList();
  updateColorbarGradient();
  initTelemetryChart();
  renderMatrixHeatmap();
  render3DSourceViewport();
  renderTopViewXZ();

  // Load saved COM port and Baudrate preferences
  try {
    const savedPort = localStorage.getItem('radioscan_com_port');
    if (savedPort) {
      APP_STATE.selectedComPort = savedPort;
      const selPort = document.getElementById('inputComPort');
      if (selPort) selPort.value = savedPort;
      const headerPort = document.getElementById('headerComPort');
      if (headerPort) headerPort.textContent = savedPort;
    }
    const savedBaud = localStorage.getItem('radioscan_baud_rate');
    if (savedBaud) {
      APP_STATE.selectedBaudrate = parseInt(savedBaud);
      const selBaud = document.getElementById('inputBaudrate');
      if (selBaud) selBaud.value = savedBaud;
      const headerBaud = document.getElementById('headerBaudrate');
      if (headerBaud) headerBaud.textContent = savedBaud;
    }
  } catch(e) {}

  // Listen to global theme change events
  window.addEventListener('themeChanged', (e) => {
    updateChartTheme(e.detail.isLight);
    renderMatrixHeatmap();
    render3DSourceViewport();
    renderTopViewXZ();
  });

  // Listen to remote Firebase updates
  if (firebaseDb) {
    // 1. Hardware Config Sync (COM Port, Baudrate, Sampling Rate)
    firebaseDb.ref('radiation_scans/hardware_config').on('value', (snapshot) => {
      const cfg = snapshot.val();
      if (cfg) {
        if (cfg.port && cfg.port !== APP_STATE.selectedComPort) {
          APP_STATE.selectedComPort = cfg.port;
          const selPort = document.getElementById('inputComPort');
          if (selPort) {
            let exists = false;
            for (let i = 0; i < selPort.options.length; i++) {
              if (selPort.options[i].value === cfg.port) { exists = true; break; }
            }
            if (!exists) {
              const opt = new Option(cfg.port, cfg.port, true, true);
              selPort.add(opt);
            }
            selPort.value = cfg.port;
          }
          const headerPort = document.getElementById('headerComPort');
          if (headerPort) headerPort.textContent = cfg.port;
          logTerminal(`<span class="log-badge-ok">[SYNC]</span> Hardware port synced with device: <strong>${cfg.port}</strong>`);
        }
        if (cfg.baudrate && cfg.baudrate !== APP_STATE.selectedBaudrate) {
          APP_STATE.selectedBaudrate = parseInt(cfg.baudrate);
          const selBaud = document.getElementById('inputBaudrate');
          if (selBaud) selBaud.value = cfg.baudrate;
          const headerBaud = document.getElementById('headerBaudrate');
          if (headerBaud) headerBaud.textContent = cfg.baudrate;
        }
      }
    });

    // 2. Latest Scan Readings Sync
    firebaseDb.ref('radiation_scans/latest').on('value', (snapshot) => {
      const val = snapshot.val();
      if (val && val.full_72_array) {
        const ts = val.timestamp || new Date().toLocaleTimeString('id-ID');
        updateTimestampDisplay(ts, 'LIVE FIREBASE');
        if (!APP_STATE.isScanning) {
          logTerminal(`<span class="log-badge-ok">[FIREBASE]</span> Remote Height ${val.current_height} (Peak: ${val.max_cps} CPS)`);
          APP_STATE.currentHeight = val.current_height;
          if (val.estimated_source_3d) APP_STATE.estimatedSource = val.estimated_source_3d;
          updateIndividualChannelsVisual(val.full_72_array);
        }
      }
    });

    // 3. Matrix Data Sync
    firebaseDb.ref('radiation_scans/matrix_data').on('value', (snapshot) => {
      const val = snapshot.val();
      if (val && val.matrix && !APP_STATE.isScanning) {
        if (val.last_updated) updateTimestampDisplay(val.last_updated, 'FIREBASE RTDB');
        APP_STATE.matrixData = val.matrix;
        renderMatrixHeatmap();
        render3DSourceViewport();
        renderTopViewXZ();
        updateRawTable();
      }
    });
  }

  // Fetch latest data & exact timestamp from TiDB Cloud on page load
  fetchInitialHistory();
});

function parseTimestampComponents(rawTime) {
  if (!rawTime || rawTime === 'Memuat...' || rawTime === 'Belum ada data') {
    return {
      full: rawTime || '--:--:--',
      time: '--:--:--',
      date: 'No Record',
      tz: 'WIB'
    };
  }

  let d = null;
  if (typeof rawTime === 'string') {
    if (rawTime.includes('T')) {
      d = new Date(rawTime);
    } else if (rawTime.includes('-') && rawTime.includes(':')) {
      const parts = rawTime.trim().split(' ');
      if (parts.length === 2) {
        const dateParts = parts[0].split('-');
        const timePart = parts[1].split('.')[0];
        if (dateParts.length === 3) {
          const months = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
          const mIdx = parseInt(dateParts[1], 10) - 1;
          const mName = months[mIdx] || dateParts[1];
          return {
            full: `${parts[0]} ${timePart}`,
            time: timePart,
            date: `${dateParts[2]} ${mName} ${dateParts[0]}`,
            tz: 'WIB'
          };
        }
      }
      d = new Date(rawTime.replace(' ', 'T'));
    }
  }

  if (d && !isNaN(d.getTime())) {
    const pad = (n) => String(n).padStart(2, '0');
    const YYYY = d.getFullYear();
    const MM = pad(d.getMonth() + 1);
    const DD = pad(d.getDate());
    const hh = pad(d.getHours());
    const mm = pad(d.getMinutes());
    const ss = pad(d.getSeconds());
    const months = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
    const mName = months[d.getMonth()] || MM;
    return {
      full: `${YYYY}-${MM}-${DD} ${hh}:${mm}:${ss}`,
      time: `${hh}:${mm}:${ss}`,
      date: `${DD} ${mName} ${YYYY}`,
      tz: 'WIB'
    };
  }

  return {
    full: String(rawTime).substring(0, 19),
    time: String(rawTime).substring(0, 8),
    date: 'Recent',
    tz: 'WIB'
  };
}

function updateTimestampDisplay(timeStr, sourceLabel = 'TiDB CLOUD') {
  const parts = parseTimestampComponents(timeStr);
  const elHeader = document.getElementById('headerLastSyncTime');
  const elBadge = document.getElementById('headerDbSourceBadge');
  const elTime = document.getElementById('metricLastTime');
  const elDate = document.getElementById('metricLastDate');
  const elKpiBadge = document.getElementById('kpiDbSourceBadge');
  
  if (elHeader) elHeader.textContent = parts.full;
  if (elBadge && sourceLabel) elBadge.textContent = sourceLabel;
  if (elTime) elTime.textContent = parts.time;
  if (elDate) elDate.textContent = parts.date;
  if (elKpiBadge && sourceLabel) elKpiBadge.textContent = sourceLabel;
}

function populateObjectDropdown(availableObjects, currentSelected) {
  const selectEl = document.getElementById('selectActiveObject');
  const presetContainer = document.getElementById('modalPresetTagList');
  const datalist = document.getElementById('tidbObjectDatalist');
  if (!selectEl) return;

  const prevVal = selectEl.value;
  selectEl.innerHTML = '';

  // Default Option: Semua Objek (Terbaru)
  const allOpt = document.createElement('option');
  allOpt.value = 'ALL';
  allOpt.textContent = '📦 Semua Objek (Terbaru)';
  selectEl.appendChild(allOpt);

  let matchFound = false;

  if (Array.isArray(availableObjects) && availableObjects.length > 0) {
    if (datalist) datalist.innerHTML = '';
    if (presetContainer) presetContainer.innerHTML = '';

    availableObjects.forEach(obj => {
      const opt = document.createElement('option');
      opt.value = obj.object_name;
      const count = obj.total_records || 0;
      const peak = obj.peak_cps || 0;
      opt.textContent = `${obj.object_name} (${count} data | Max ${peak} CPS)`;
      
      if (currentSelected && (currentSelected === obj.object_name || currentSelected === obj.object_name.trim())) {
        opt.selected = true;
        matchFound = true;
      }
      selectEl.appendChild(opt);

      // Add to Datalist for Modal Auto-complete
      if (datalist) {
        const dlOpt = document.createElement('option');
        dlOpt.value = obj.object_name;
        datalist.appendChild(dlOpt);
      }

      // Add to Modal Preset Tags
      if (presetContainer) {
        const tag = document.createElement('span');
        tag.className = `preset-tag ${currentSelected === obj.object_name ? 'active' : ''}`;
        tag.textContent = `${obj.object_name} (${count} scan)`;
        tag.title = `Peak: ${peak} CPS | Terakhir: ${obj.last_ts || '-'}`;
        tag.onclick = () => selectObjectPreset(obj.object_name);
        presetContainer.appendChild(tag);
      }
    });
  }

  if (currentSelected === 'ALL') {
    allOpt.selected = true;
  } else if (!matchFound && currentSelected && currentSelected !== 'ALL') {
    const customOpt = document.createElement('option');
    customOpt.value = currentSelected;
    customOpt.textContent = `${currentSelected} (Aktif)`;
    customOpt.selected = true;
    selectEl.appendChild(customOpt);
  } else if (!matchFound && prevVal) {
    selectEl.value = prevVal;
  }
}

function onObjectSelectChange(selectedName) {
  if (APP_STATE.isScanning) {
    showToast('warning', 'Sedang Memindai', 'Harap tunggu atau hentikan pemindaian sebelum mengganti objek.');
    return;
  }
  APP_STATE.objectName = (selectedName === 'ALL') ? 'Gentong' : selectedName;
  loadObjectDataFromTiDB(selectedName, true);
}

function refreshAvailableObjects(isManual = false) {
  const btn = document.getElementById('btnRefreshObjects');
  if (btn) btn.classList.add('spinning');
  
  const selectEl = document.getElementById('selectActiveObject');
  const currentObj = selectEl ? selectEl.value : 'ALL';

  loadObjectDataFromTiDB(currentObj, isManual);
}

function loadObjectDataFromTiDB(objectName = 'ALL', showNotification = false) {
  const btn = document.getElementById('btnRefreshObjects');
  if (btn) btn.classList.add('spinning');

  let url = '/matrix-data/history';
  if (objectName && objectName !== 'ALL') {
    url += '?object_name=' + encodeURIComponent(objectName);
  } else {
    url += '?limit=24';
  }

  fetch(url)
    .then(res => {
      if (!res.ok) throw new Error(`HTTP error ${res.status}`);
      return res.json();
    })
    .then(data => {
      if (btn) btn.classList.remove('spinning');

      // 1. Update dropdown and modal inputs from TiDB Cloud
      if (data.available_objects) {
        populateObjectDropdown(data.available_objects, objectName);
      }

      // 2. Process records
      if (data && data.records && data.records.length > 0) {
        const latest = data.records[0];
        const timeStr = latest.timestamp || latest.created_at || 'Baru Saja';
        updateTimestampDisplay(timeStr, data.source === 'tidb_cloud' ? 'TiDB CLOUD' : 'LOCAL DB');

        // Sort ascending by height_level or ID so earlier scans are Loop 1 and latest is Loop N
        const sorted = [...data.records].sort((a, b) => {
          const hA = parseInt(a.height_level) || 0;
          const hB = parseInt(b.height_level) || 0;
          if (hA !== hB) return hA - hB;
          const idA = parseInt(a.id) || 0;
          const idB = parseInt(b.id) || 0;
          return idA - idB;
        });

        const matrixFromDb = [];
        let globalPeakCps = 0;
        let peakChannelIdx = 1;
        let totalSumCps = 0;
        let totalCellCount = 0;

        sorted.forEach(rec => {
          if (rec.detector_data && Array.isArray(rec.detector_data)) {
            matrixFromDb.push(rec.detector_data);
            rec.detector_data.forEach((cps, idx) => {
              totalSumCps += cps;
              totalCellCount++;
              if (cps > globalPeakCps) {
                globalPeakCps = cps;
                peakChannelIdx = idx + 1;
              }
            });
          }
        });

        if (matrixFromDb.length > 0) {
          APP_STATE.matrixData = matrixFromDb;
          APP_STATE.totalHeights = matrixFromDb.length;
          APP_STATE.currentHeight = matrixFromDb.length;
          APP_STATE.totalLoops = latest.total_loops || 1;

          if (objectName !== 'ALL') {
            APP_STATE.objectName = objectName;
          } else if (latest.object_name) {
            APP_STATE.objectName = latest.object_name;
          }

          // Update HUD indicators
          document.getElementById('headerHeightProgress').textContent = `${matrixFromDb.length} / ${matrixFromDb.length}`;
          document.getElementById('headerLoopProgress').textContent = `1 / ${APP_STATE.totalLoops}`;

          // Update Quick Inputs
          const qSteps = document.getElementById('inputTotalHeight');
          if (qSteps) qSteps.value = matrixFromDb.length;
          const qLoops = document.getElementById('inputTotalLoops');
          if (qLoops) qLoops.value = APP_STATE.totalLoops;

          // Update KPI telemetry
          const avgCpsVal = totalCellCount > 0 ? (totalSumCps / totalCellCount).toFixed(1) : '0.0';
          document.getElementById('metricActiveLoop').innerHTML = `${matrixFromDb.length} <span class="kpi-unit">/ ${matrixFromDb.length}</span>`;
          document.getElementById('metricMaxCps').innerHTML = `${globalPeakCps} <span class="kpi-unit">cps</span>`;
          document.getElementById('metricMaxChannel').textContent = `Detector D${peakChannelIdx} (XS${peakChannelIdx <= 24 ? 1 : (peakChannelIdx <= 48 ? 2 : 3)})`;
          document.getElementById('metricAvgCps').innerHTML = `${avgCpsVal} <span class="kpi-unit">cps</span>`;
          document.getElementById('metricTotalCounts').innerHTML = `${totalSumCps.toLocaleString()} <span class="kpi-unit">cts</span>`;

          // Update 3D Estimated Localization
          if (globalPeakCps >= APP_STATE.hotspotThreshold) {
            const peakHeightRow = matrixFromDb.findIndex(row => row.includes(globalPeakCps));
            const estZ = peakHeightRow >= 0 ? (peakHeightRow + 1) * 35 : 175;
            const estX = Math.round((peakChannelIdx / 72) * 800);
            APP_STATE.estimatedSource = {
              x: estX,
              y: -30.0,
              z: estZ,
              confidence: Math.min(99.0, 88.0 + (globalPeakCps / 800) * 11).toFixed(1)
            };
            document.getElementById('statPosX').textContent = `${APP_STATE.estimatedSource.x} cm`;
            document.getElementById('statPosY').textContent = `${APP_STATE.estimatedSource.y} cm`;
            document.getElementById('statPosZ').textContent = `${APP_STATE.estimatedSource.z} cm`;
            document.getElementById('statConfidence').textContent = `${APP_STATE.estimatedSource.confidence}% Confidence`;
            document.getElementById('statConfidenceVal').textContent = `${APP_STATE.estimatedSource.confidence}%`;
            document.getElementById('statHotspotCount').textContent = `XS${peakChannelIdx <= 24 ? 1 : (peakChannelIdx <= 48 ? 2 : 3)} (D${peakChannelIdx})`;
          }

          // Update 72 individual detector readouts if visual elements present
          updateIndividualChannelsVisual(matrixFromDb[matrixFromDb.length - 1]);

          // Rebuild sidebar loops & views with dynamic loop count from TiDB
          buildSidebarLoopList();
          renderMatrixHeatmap();
          render3DSourceViewport();
          renderTopViewXZ();
          updateRawTable();

          if (showNotification) {
            const displayTitle = objectName === 'ALL' ? 'Semua Objek (Terbaru)' : objectName;
            showToast('success', 'Objek Dimuat', `Menampilkan data scan <b>${displayTitle}</b> (${matrixFromDb.length} baris loop) dari <b>${data.source === 'tidb_cloud' ? 'TiDB Cloud' : 'Database'}</b>.`);
            logTerminal(`<span class="log-badge-ok">[TiDB CLOUD]</span> Menampilkan data objek <strong>${displayTitle}</strong> (${matrixFromDb.length} baris level | Peak: ${globalPeakCps} CPS)`);
          }
        }
      } else {
        updateTimestampDisplay('Belum ada data', 'READY');
        if (showNotification) {
          showToast('info', 'Data Kosong', `Belum ada data rekaman pemindaian untuk objek <b>${objectName}</b> di database.`);
        }
      }
    })
    .catch(err => {
      console.warn("[History Fetch Error]", err);
      if (btn) btn.classList.remove('spinning');
      if (showNotification) {
        showToast('warning', 'Gagal Sinkronisasi', 'Tidak dapat mengambil data dari database server.');
      }
    });
}

function fetchInitialHistory() {
  loadObjectDataFromTiDB('ALL', false);

  // Background Auto-sync from TiDB Cloud every 8 seconds
  setInterval(() => {
    if (!APP_STATE.isScanning && !APP_STATE.isPaused) {
      const selectEl = document.getElementById('selectActiveObject');
      const activeObj = selectEl ? selectEl.value : 'ALL';
      loadObjectDataFromTiDB(activeObj, false);
    }
  }, 8000);
}

</script>
@endsection
