@extends('layouts.app')

@section('title', 'Profil Pengguna — RadiaTrack')

@section('content')
  <div class="topbar">
    <svg class="subicon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
      <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
      <circle cx="12" cy="7" r="4"/>
    </svg>
    <h1>Profil Pengguna</h1>
  </div>
  <p class="page-sub">Kelola informasi data profil operator Anda di sini.</p>

  <div class="panel" style="max-width: 600px; margin: 24px auto 20px 32px; padding: 28px 24px;">
    @if(session('success'))
      <div class="form-success">
        {{ session('success') }}
      </div>
    @endif

    @if($errors->any())
      <div class="form-err">
        {{ $errors->first() }}
      </div>
    @endif

    <form action="{{ route('profile.update') }}" method="POST" class="modal-form" style="display: flex; flex-direction: column; gap: 16px;">
      @csrf

      <div class="field">
        <label for="name">NAMA OPERATOR</label>
        <input type="text" name="name" id="name" value="{{ old('name', $user->name) }}" required placeholder="Masukkan nama Anda...">
      </div>

      <div class="field">
        <label for="email">EMAIL OPERATOR (TERHUBUNG FIREBASE)</label>
        <input type="email" id="email" value="{{ $user->email }}" disabled style="opacity: 0.65; cursor: not-allowed; background: var(--panel-2);">
        <small style="color: var(--muted); font-size: 11px; margin-top: 4px; display: block;">Email disinkronkan langsung dari akun Firebase Auth Anda dan tidak dapat diubah.</small>
      </div>

      <hr style="border: 0; border-top: 1px solid var(--border); margin: 8px 0;">

      <div class="field">
        <label for="password">PASSWORD BARU (KOSONGKAN JIKA TIDAK DIUBAH)</label>
        <input type="password" name="password" id="password" placeholder="Masukkan password baru minimal 8 karakter...">
      </div>

      <div class="field">
        <label for="password_confirmation">KONFIRMASI PASSWORD BARU</label>
        <input type="password" name="password_confirmation" id="password_confirmation" placeholder="Ulangi password baru Anda...">
      </div>

      <div style="display: flex; justify-content: flex-end; margin-top: 10px;">
        <button type="submit" class="btn-submit" style="padding: 12px 24px;">Simpan Perubahan</button>
      </div>
    </form>
  </div>

  <footer class="hint">Data disimpan secara aman ke database lokal 'magang' MySQL Anda.</footer>
@endsection
