@extends('layouts.auth')

@section('title', 'Masuk — RadiaTrack')

@section('content')
  <h1>Masuk ke akun</h1>
  <p class="sub">Pantau tingkat radiasi di seluruh zona secara real-time.</p>

  <div class="form-err" id="clientErr" style="display: none;"></div>

  @if($errors->any())
    <div class="form-err" id="serverErr">
      {{ $errors->first() }}
    </div>
  @endif

  @if(session('success'))
    <div class="form-success">
      {{ session('success') }}
    </div>
  @endif

  <button class="btn-google" id="googleBtn" type="button">
    <svg viewBox="0 0 24 24"><path fill="#4285F4" d="M23.52 12.27c0-.85-.08-1.67-.22-2.45H12v4.63h6.47c-.28 1.5-1.13 2.77-2.4 3.63v3h3.89c2.28-2.1 3.56-5.19 3.56-8.81z"/><path fill="#34A853" d="M12 24c3.24 0 5.96-1.07 7.95-2.92l-3.89-3c-1.08.73-2.46 1.16-4.06 1.16-3.12 0-5.77-2.11-6.72-4.94H1.26v3.1C3.24 21.3 7.28 24 12 24z"/><path fill="#FBBC05" d="M5.28 14.3c-.24-.73-.38-1.5-.38-2.3s.14-1.57.38-2.3v-3.1H1.26A11.97 11.97 0 0 0 0 12c0 1.94.46 3.77 1.26 5.4l4.02-3.1z"/><path fill="#EA4335" d="M12 4.76c1.77 0 3.35.61 4.6 1.8l3.45-3.45C17.95 1.19 15.24 0 12 0 7.28 0 3.24 2.7 1.26 6.6l4.02 3.1C6.23 6.87 8.88 4.76 12 4.76z"/></svg>
    Lanjutkan dengan Google
  </button>

  <div class="divider"><span>ATAU DENGAN EMAIL</span></div>

  <form method="POST" action="{{ route('login') }}" id="loginForm">
    @csrf
    <input type="hidden" name="id_token" id="fidToken">
    <div class="field">
      <label for="femail">Email</label>
      <input id="femail" name="email" type="email" placeholder="nama@perusahaan.com" value="{{ old('email') }}" required>
    </div>
    <div class="field">
      <label for="fpass">Kata sandi</label>
      <input id="fpass" name="password" type="password" placeholder="••••••••" required>
    </div>
    <button type="submit" class="login-submit" id="submitBtn">Masuk</button>
  </form>

  <div class="switch-mode">
    Belum punya akun? <a href="{{ route('register') }}">Daftar</a>
  </div>

  <script>
    const form = document.getElementById('loginForm');
    const clientErr = document.getElementById('clientErr');
    const serverErr = document.getElementById('serverErr');
    const submitBtn = document.getElementById('submitBtn');

    form.addEventListener('submit', async (e) => {
      e.preventDefault();
      
      clientErr.style.display = 'none';
      if (serverErr) serverErr.style.display = 'none';
      submitBtn.disabled = true;
      submitBtn.textContent = 'Memverifikasi...';

      const email = document.getElementById('femail').value.trim();
      const password = document.getElementById('fpass').value;

      try {
        const userCredential = await firebaseAuth.signInWithEmailAndPassword(email, password);
        const user = userCredential.user;
        
        const idToken = await user.getIdToken();
        
        document.getElementById('fidToken').value = idToken;
        form.submit();
      } catch (error) {
        submitBtn.disabled = false;
        submitBtn.textContent = 'Masuk';
        
        let errMsg = 'Terjadi kesalahan saat masuk.';
        if (error.code === 'auth/user-not-found' || error.code === 'auth/wrong-password' || error.code === 'auth/invalid-credential') {
          errMsg = 'Email atau kata sandi tidak cocok.';
        } else if (error.code === 'auth/invalid-email') {
          errMsg = 'Format email tidak valid.';
        } else {
          errMsg = error.message;
        }
        clientErr.textContent = errMsg;
        clientErr.style.display = 'block';
      }
    });

    document.getElementById('googleBtn').addEventListener('click', async () => {
      clientErr.style.display = 'none';
      if (serverErr) serverErr.style.display = 'none';
      submitBtn.disabled = true;
      submitBtn.textContent = 'Menghubungkan Google...';

      try {
        const provider = new firebase.auth.GoogleAuthProvider();
        const result = await firebaseAuth.signInWithPopup(provider);
        const user = result.user;
        
        const idToken = await user.getIdToken();
        
        document.getElementById('fidToken').value = idToken;
        form.submit();
      } catch (error) {
        submitBtn.disabled = false;
        submitBtn.textContent = 'Masuk';
        
        let errMsg = 'Gagal masuk dengan Google.';
        if (error.code === 'auth/popup-closed-by-user') {
          errMsg = 'Proses masuk dibatalkan (popup ditutup).';
        } else if (error.code === 'auth/cancelled-popup-request') {
          errMsg = 'Permintaan popup dibatalkan.';
        } else {
          errMsg = error.message;
        }
        clientErr.textContent = errMsg;
        clientErr.style.display = 'block';
      }
    });
  </script>
@endsection
