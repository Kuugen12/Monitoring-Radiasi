<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>@yield('title', 'RadiaTrack — Pantauan Radiasi')</title>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=IBM+Plex+Mono:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="{{ asset('css/app.css') }}">
  @yield('styles')
</head>
<body>
  @php
    $user = Auth::user();
    $displayName = $user ? $user->name : 'Operator';
    $email = $user ? $user->email : 'operator@gmail.com';
    $initials = '';
    if ($user && $user->name) {
        $parts = preg_split('/[.\s]+/', trim($user->name));
        $parts = array_filter($parts);
        $parts = array_slice($parts, 0, 2);
        foreach ($parts as $part) {
            if (!empty($part)) {
                $initials .= strtoupper($part[0]);
            }
        }
    }
    if (empty($initials)) {
        $initials = 'OP';
    }
  @endphp

  <div class="app">
    <aside class="sidebar">
      <div class="brand">
        <div class="mark">☢</div>
        <div class="name">radia<span>track</span></div>
        <div class="tag">IOT</div>
      </div>
      <nav>
        <div class="navgroup-label">MENU</div>
        <a href="{{ route('dashboard') }}" class="navitem {{ request()->routeIs('dashboard') ? 'active' : '' }}" id="navDashboard">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/></svg>
          Dashboard
        </a>
      </nav>
      <div class="sidebar-bottom">
        <div class="profile-block">
          <div class="avatar">{{ $initials }}</div>
          <div class="info">
            <div class="pname">{{ $displayName }}</div>
            <div class="prow"><span class="prole">{{ $email }}</span></div>
          </div>
          <form id="logoutForm" action="{{ route('logout') }}" method="POST" style="display: none;">
            @csrf
          </form>
          <button class="logout-btn" onclick="event.preventDefault(); document.getElementById('logoutForm').submit();" title="Keluar">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="M16 17l5-5-5-5"/><path d="M21 12H9"/></svg>
          </button>
        </div>
      </div>
    </aside>

    <main>
      <div id="mainContent">
        @yield('content')
      </div>
    </main>
  </div>

  @yield('scripts')
</body>
</html>
