<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>@yield('title', 'RadiaTrack — Pantauan Radiasi')</title>
  <script>
    // Apply theme immediately to prevent flashing
    (function () {
      const theme = localStorage.getItem('theme') || 'dark';
      if (theme === 'light') {
        document.documentElement.classList.add('light-mode');
      } else {
        document.documentElement.classList.remove('light-mode');
      }
    })();
  </script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=IBM+Plex+Mono:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="{{ asset('css/app.css') }}">
  @yield('styles')
</head>
<body>
  @php
    try {
        $user = Auth::user();
    } catch (\Throwable $e) {
        $user = null;
    }
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
        <a href="{{ route('matrix') }}" class="navitem {{ request()->routeIs('matrix') ? 'active' : '' }}" id="navMatrix" style="position:relative;">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="3"/><path d="M12 2a10 10 0 0 0-4.9 1.3l2.5 4.3A5 5 0 0 1 12 7V2zM16.9 3.3A10 10 0 0 0 12 2v5a5 5 0 0 1 2.5.7l2.4-4.4zM2.3 14.1a10 10 0 0 0 2.6 4.1l3.7-3.3A5 5 0 0 1 7 12H2c0 .7.1 1.4.3 2.1zM2.3 9.9H7a5 5 0 0 1 1.6-2.9L4.9 3.7A10 10 0 0 0 2.3 9.9zM21.7 9.9A10 10 0 0 0 19.1 3.7l-3.7 3.3A5 5 0 0 1 17 12h5c0-.7-.1-1.4-.3-2.1zM15.4 14.9l3.7 3.3a10 10 0 0 0 2.6-4.1H17a5 5 0 0 1-1.6 2.9zM7.1 20.7A10 10 0 0 0 12 22v-5a5 5 0 0 1-2.5-.7l-2.4 4.4zM16.9 20.7l-2.5-4.4A5 5 0 0 1 12 17v5a10 10 0 0 0 4.9-1.3z"/></svg>
          Radioscan Matrix
          <span style="display:inline-block;width:7px;height:7px;border-radius:50%;background:#10B981;box-shadow:0 0 6px #10B981;margin-left:auto;"></span>
        <a href="{{ route('profile') }}" class="navitem {{ request()->routeIs('profile') ? 'active' : '' }}" id="navProfile">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
          Profile
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
          <button type="button" class="theme-toggle-btn" id="themeToggleBtn" title="Ganti Tema">
            <svg viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" fill="none" class="theme-icon-dark"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>
          </button>
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
  
  <script>
    document.getElementById('themeToggleBtn').addEventListener('click', function () {
      const isLight = document.documentElement.classList.toggle('light-mode');
      localStorage.setItem('theme', isLight ? 'light' : 'dark');
      updateThemeIcon(isLight);
      window.dispatchEvent(new CustomEvent('themeChanged', { detail: { isLight } }));
    });

    function updateThemeIcon(isLight) {
      const btn = document.getElementById('themeToggleBtn');
      if (isLight) {
        // Sun icon
        btn.innerHTML = `<svg viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" fill="none" class="theme-icon-light"><circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/></svg>`;
      } else {
        // Moon icon
        btn.innerHTML = `<svg viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" fill="none" class="theme-icon-dark"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>`;
      }
    }

    // Set initial icon on load
    (function () {
      const isLight = document.documentElement.classList.contains('light-mode');
      updateThemeIcon(isLight);
    })();
  </script>
</body>
</html>
