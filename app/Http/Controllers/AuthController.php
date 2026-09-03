<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    public function showLogin()
    {
        if (Auth::check()) {
            return redirect()->route('matrix');
        }
        return view('auth.login');
    }

    public function login(Request $request)
    {
        // 1. Firebase Token Authentication
        if ($request->filled('id_token')) {
            try {
                $firebaseUser = $this->verifyFirebaseToken($request->id_token);
                
                if (!$firebaseUser) {
                    return back()->withErrors([
                        'email' => 'Firebase authentication token tidak valid.',
                    ]);
                }

                $email = $firebaseUser['email'];
                $name = $firebaseUser['displayName'] ?? explode('@', $email)[0];

                $user = User::firstOrCreate(
                    ['email' => $email],
                    [
                        'name' => $name,
                        'password' => Hash::make(Str::random(16)),
                    ]
                );

                Auth::login($user);
                $request->session()->regenerate();

                return redirect()->intended(route('matrix'));

            } catch (\Exception $e) {
                return back()->withErrors([
                    'email' => 'Terjadi kesalahan sistem: ' . $e->getMessage(),
                ]);
            }
        }

        // 2. Standard Local Credentials Fallback
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (Auth::attempt($credentials)) {
            $request->session()->regenerate();
            return redirect()->intended(route('matrix'));
        }

        return back()->withErrors([
            'email' => 'Email atau kata sandi tidak cocok.',
        ])->onlyInput('email');
    }

    public function showRegister()
    {
        if (Auth::check()) {
            return redirect()->route('matrix');
        }
        return view('auth.register');
    }

    public function register(Request $request)
    {
        // 1. Firebase Token Registration
        if ($request->filled('id_token')) {
            $request->validate([
                'name' => ['nullable', 'string', 'max:255'],
            ]);

            try {
                $firebaseUser = $this->verifyFirebaseToken($request->id_token);
                
                if (!$firebaseUser) {
                    return back()->withErrors([
                        'email' => 'Firebase authentication token tidak valid.',
                    ]);
                }

                $email = $firebaseUser['email'];
                $name = $request->filled('name') ? $request->name : ($firebaseUser['displayName'] ?? explode('@', $email)[0]);

                $user = User::updateOrCreate(
                    ['email' => $email],
                    [
                        'name' => $name,
                        'password' => Hash::make(Str::random(16)),
                    ]
                );

                Auth::login($user);
                $request->session()->regenerate();

                return redirect()->route('matrix');

            } catch (\Exception $e) {
                return back()->withErrors([
                    'email' => 'Terjadi kesalahan sistem: ' . $e->getMessage(),
                ]);
            }
        }

        // 2. Standard Local Registration Fallback
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'string', 'min:6'],
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('matrix');
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('login');
    }

    private function verifyFirebaseToken($idToken)
    {
        $apiKey = env('FIREBASE_API_KEY');
        if (empty($apiKey)) {
            throw new \Exception('Firebase API Key is not configured in .env');
        }

        $response = Http::post("https://identitytoolkit.googleapis.com/v1/accounts:lookup?key={$apiKey}", [
            'idToken' => $idToken,
        ]);

        if ($response->successful()) {
            $data = $response->json();
            if (isset($data['users'][0])) {
                return $data['users'][0];
            }
        }

        return null;
    }
}
