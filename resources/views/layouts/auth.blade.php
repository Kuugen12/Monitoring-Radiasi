<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>@yield('title', 'RadiaTrack — Pantauan Radiasi')</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=IBM+Plex+Mono:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="{{ asset('css/app.css') }}">
  <script src="https://www.gstatic.com/firebasejs/10.8.0/firebase-app-compat.js"></script>
  <script src="https://www.gstatic.com/firebasejs/10.8.0/firebase-auth-compat.js"></script>
  <script>
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
    const firebaseAuth = firebase.auth();
  </script>
</head>
<body>
  <div class="login-page">
    <div class="login-box">
      <div class="login-brand">
        <div class="mark">☢</div>
        <div class="name">radia<span>track</span></div>
      </div>
      @yield('content')
    </div>
  </div>
</body>
</html>
