<?php

namespace App\Controllers\Auth;

use App\Controllers\Controller;
use App\Framework\Authorization\Auth;
use App\Framework\Authorization\AuthenticationService;
use App\Framework\Authorization\LoginRequest;
use App\Framework\Http\Request;
use App\Framework\Session\Session;

class PortalLoginController extends Controller
{
    public function __construct(private readonly AuthenticationService $authenticationService,
    )
    {
        parent::__construct();
    }

    /**
     * GET /crm/login
     * GET /merchant/login
     */
    public function showLoginForm(string $portal): mixed
    {
        // Already authenticated — bounce to the portal
        if (Auth::check()) {
            return $this->redirect($this->portalHome($portal));
        }

        return $this->view('auth/login', [
            'portal' => $portal,
            'formAction' => "/{$portal}/login/{$portal}",
            'error' => Session::getFlash('login_error'),
        ]);
    }

    private function portalHome(string $portal): string
    {
        return match ($portal) {
            'crm' => '/crm/members',
            'merchant' => '/merchant/dashboard',
            default => '/',
        };
    }

    /**
     * POST /crm/login
     * POST /merchant/login
     */
    public function login(string $portal, Request $request): mixed
    {
        $email = trim($request->input('email', ''));
        $password = $request->input('password', '');

        if (empty($email) || empty($password)) {
            Session::flash('login_error', 'Email and password are required.');
            return $this->redirect("/{$portal}/login");
        }

        $loginRequest = new LoginRequest(
            $email,
            $password,
            8 //todo
        );

        $response = $this->authenticationService->login($loginRequest);

        Auth::login([
            'id' => $response->userId,
            'name' => $response->userName,
            'email' => $response->userEmail,
            'role' => $response->role,
        ]);

        if (!$response) {
            Session::flash('login_error', 'Invalid email or password.');
            return $this->redirect("/{$portal}/login");
        }

        $user = Auth::user();

        // Role check — CRM requires admin|agent, merchant portal requires admin|merchant
        $allowed = match ($portal) {
            'crm' => in_array($user->role ?? '', ['admin', 'agent']),
            'merchant' => in_array($user->role ?? '', ['admin', 'merchant', 'user']),
            default => false,
        };

        if (!$allowed) {
            die('no');
            Auth::logout();
            Session::flash('login_error', 'You do not have access to this portal.');
            return $this->redirect("/{$portal}/login");
        }

        // Redirect to originally-intended URL, or portal home
        $intended = Session::get('intended_url');
        Session::forget('intended_url');

        Session::put('auth_token', $response->accessToken);

        return $this->redirect($intended ?: $this->portalHome($portal));
    }

    /**
     * POST /crm/logout
     * POST /merchant/logout
     */
    public function logout(string $portal): mixed
    {
        Auth::logout();
        return $this->redirect("/{$portal}/login");
    }
}