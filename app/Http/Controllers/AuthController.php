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
            return redirect()->route('dashboard');
        }
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $request->validate([
            'id_token' => ['required', 'string'],
        ]);

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

            return redirect()->route('dashboard');

        } catch (\Exception $e) {
            return back()->withErrors([
                'email' => 'Terjadi kesalahan sistem: ' . $e->getMessage(),
            ]);
        }
    }

    public function showRegister()
    {
        if (Auth::check()) {
            return redirect()->route('dashboard');
        }
        return view('auth.register');
    }

    public function register(Request $request)
    {
        $request->validate([
            'id_token' => ['required', 'string'],
            'name' => ['required', 'string', 'max:255'],
        ]);

        try {
            $firebaseUser = $this->verifyFirebaseToken($request->id_token);
            
            if (!$firebaseUser) {
                return back()->withErrors([
                    'email' => 'Firebase authentication token tidak valid.',
                ]);
            }

            $email = $firebaseUser['email'];

            $user = User::updateOrCreate(
                ['email' => $email],
                [
                    'name' => $request->name,
                    'password' => Hash::make(Str::random(16)),
                ]
            );

            Auth::login($user);
            $request->session()->regenerate();

            return redirect()->route('dashboard');

        } catch (\Exception $e) {
            return back()->withErrors([
                'email' => 'Terjadi kesalahan sistem: ' . $e->getMessage(),
            ]);
        }
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
