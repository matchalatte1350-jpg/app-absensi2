<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;

class AuthController extends Controller
{
    public function index()
    {
        return $this->showLogin();
    }

    public function showLogin()
    {
        if (Auth::check()) {
            return redirect()->route('dashboard');
        }

        return view('auth-login');
    }

    public function login(Request $request)
    {
        $request->validate([
            'username' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        $loginInput = trim($request->input('username'));
        $password = $request->input('password');
        $authenticated = false;

        if (Schema::hasColumn('users', 'username')) {
            $authenticated = Auth::attempt([
                'username' => $loginInput,
                'password' => $password,
            ]);
        }

        if (!$authenticated && filter_var($loginInput, FILTER_VALIDATE_EMAIL)) {
            $authenticated = Auth::attempt([
                'email' => $loginInput,
                'password' => $password,
            ]);
        }

        if ($authenticated) {
            $request->session()->regenerate();
            return redirect()->intended(route('dashboard'));
        }

        return back()->withErrors([
            'username' => 'Username atau password yang dimasukkan salah.',
        ])->onlyInput('username');
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}