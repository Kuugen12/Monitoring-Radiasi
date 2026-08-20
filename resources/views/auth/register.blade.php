@extends('layouts.auth')

@section('title', 'Daftar — RadiaTrack')

@section('content')
  <h1>Buat akun baru</h1>
  <p class="sub">Daftar untuk mulai memantau sensor radiasi Anda.</p>

  <div class="form-err" id="clientErr" style="display: none;"></div>

  @if($errors->any())
    <div class="form-err" id="serverErr">
      {{ $errors->first() }}
    </div>
  @endif

  <form method="POST" action="{{ route('register') }}" id="registerForm">
    @csrf
    <input type="hidden" name="id_token" id="fidToken">
    <div class="field">
      <label for="fname">Nama lengkap</label>
      <input id="fname" name="name" type="text" placeholder="Nama Anda" value="{{ old('name') }}" required>
    </div>
    <div class="field">
      <label for="femail">Email</label>
      <input id="femail" name="email" type="email" placeholder="nama@perusahaan.com" value="{{ old('email') }}" required>
    </div>
    <div class="field">
      <label for="fpass">Kata sandi</label>
      <input id="fpass" name="password" type="password" placeholder="••••••••" required>
    </div>
    <div class="field">
      <label for="fconfirm">Konfirmasi kata sandi</label>
      <input id="fconfirm" name="password_confirmation" type="password" placeholder="••••••••" required>
    </div>
    <button type="submit" class="login-submit" id="submitBtn">Daftar Akun</button>
  </form>

  <div class="switch-mode">
    Sudah punya akun? <a href="{{ route('login') }}">Masuk</a>
  </div>

  <script>
    const form = document.getElementById('registerForm');
    const clientErr = document.getElementById('clientErr');
    const serverErr = document.getElementById('serverErr');
    const submitBtn = document.getElementById('submitBtn');

    form.addEventListener('submit', async (e) => {
      e.preventDefault();
      
      clientErr.style.display = 'none';
      if (serverErr) serverErr.style.display = 'none';

      const name = document.getElementById('fname').value.trim();
      const email = document.getElementById('femail').value.trim();
      const password = document.getElementById('fpass').value;
      const confirm = document.getElementById('fconfirm').value;

      if (!name) {
        showErr('Nama lengkap wajib diisi.');
        return;
      }
      if (password !== confirm) {
        showErr('Konfirmasi kata sandi tidak cocok.');
        return;
      }
      if (password.length < 6) {
        showErr('Kata sandi minimal 6 karakter.');
        return;
      }

      submitBtn.disabled = true;
      submitBtn.textContent = 'Mendaftarkan...';

      try {
        const userCredential = await firebaseAuth.createUserWithEmailAndPassword(email, password);
        const user = userCredential.user;
        
        await user.updateProfile({
          displayName: name
        });
        
        const idToken = await user.getIdToken(true);
        
        document.getElementById('fidToken').value = idToken;
        form.submit();
      } catch (error) {
        submitBtn.disabled = false;
        submitBtn.textContent = 'Daftar Akun';
        
        let errMsg = 'Terjadi kesalahan saat mendaftar.';
        if (error.code === 'auth/email-already-in-use') {
          errMsg = 'Email sudah terdaftar.';
        } else if (error.code === 'auth/invalid-email') {
          errMsg = 'Format email tidak valid.';
        } else if (error.code === 'auth/weak-password') {
          errMsg = 'Kata sandi terlalu lemah.';
        } else {
          errMsg = error.message;
        }
        showErr(errMsg);
      }
    });

    function showErr(msg) {
      clientErr.textContent = msg;
      clientErr.style.display = 'block';
    }
  </script>
@endsection
