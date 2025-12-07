<?php
namespace App\Controllers;

use App\Framework\Authorization\MemberAuth;
use App\Framework\Http\Request;
use App\Framework\Support\SiteContext;
use App\Models\Member;
use App\Models\MemberRole;
use App\Models\Menu;
use App\Requests\ChangePasswordRequest;
use App\Requests\LoginRequest;
use App\Requests\MemberRegistrationRequest;
use App\Requests\ResetPasswordRequest;
use App\Services\EmailVerificationService;
use App\Services\MenuRenderer;
use App\Services\PasswordResetService;

class MemberAuthController extends Controller
{
    public function __construct(
        private EmailVerificationService $emailVerificationService,
        private PasswordResetService $passwordResetService
    ) {
        parent::__construct();
    }

    public function showRegisterForm()
    {
        if (MemberAuth::check()) {
            return $this->redirect('/member/dashboard');
        }

        return $this->view('member/register', [
            'site' => SiteContext::get()
        ]);
    }

    public function register(MemberRegistrationRequest $request)
    {
        $siteId = SiteContext::getId();
        $validated = $request->validated();

        // Check if email already exists
        if (Member::findByEmail($validated['email'], $siteId)) {

            if ($request->getHeader('X-Requested-With') === 'XMLHttpRequest' || $request->getHeader('Content-Type') === 'application/json') {
                return $this->resourceResponse([
                    'success' => false,
                    'message' => 'Email already registered'
                ]);
            }

            return $this->back()->withErrors(['email' => 'Email already registered']);
        }

        // Create member
        $member = Member::create([
            'site_id' => $siteId,
            'email' => $validated['email'],
            'password' => password_hash($validated['password'], PASSWORD_DEFAULT),
            'first_name' => $validated['first_name'],
            'last_name' => $validated['last_name'],
            'is_active' => $request->getHeader('X-Requested-With') === 'XMLHttpRequest' || $request->getHeader('Content-Type') === 'application/json' // Requires email verification
        ]);

        // Assign default role
        $defaultRole = MemberRole::findBySlug('basic', $siteId);
        if ($defaultRole) {
            $member->roles(true)->attach($defaultRole->id);
        }

        // Send verification email
        $token = $this->emailVerificationService->generateVerificationToken($member);
        $this->emailVerificationService->sendVerificationEmail($member, $token);

        try {
            MemberAuth::login($member);
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }

        if ($request->getHeader('X-Requested-With') === 'XMLHttpRequest' || $request->getHeader('Content-Type') === 'application/json') {
            return $this->resourceResponse([
                'success' => true,
                'message' => 'Verification email sent successfully'
            ]);
        }

        return $this->redirect('/member/verify-email-sent')
            ->with('email', $member->email);
    }

    public function showLoginForm()
    {
        if (MemberAuth::check()) {
            return $this->redirect('/member/dashboard');
        }

        $siteId = SiteContext::getId();

        $menu = Menu::where('is_active', true)
            ->where('site_id', $siteId)
            ->where('menu_type', 'header')
            ->with(['items'])
            ->first();

        return $this->view('member/login', [
            'site' => SiteContext::get(),
            'menu' => $menu,
            'menuRenderer' => new Menurenderer()
        ]);
    }

    public function login(LoginRequest $request)
    {
        $credentials = $request->validated();

        if (MemberAuth::attempt($credentials)) {
            $intendedUrl = $request->session()->get('intended_url', '/member/dashboard');
            $request->session()->forget('intended_url');

            return $this->redirect($intendedUrl);
        }

        return $this->back()->withErrors([
            'email' => 'Invalid credentials or account not activated'
        ]);
    }

    public function logout()
    {
        MemberAuth::logout();
        return $this->redirect('/')->with('message', 'Logged out successfully');
    }

    public function showVerifyEmailSent()
    {
        return $this->view('member/verify-email-sent');
    }

    public function verifyEmail(Request $request)
    {
        $token = $request->query('token');

        if (!$token) {
            return $this->redirect('/')->withErrors(['message' => 'Invalid verification link']);
        }

        if ($this->emailVerificationService->verify($token)) {
            return $this->redirect('/member/login')
                ->with('message', 'Email verified! You can now login.');
        }

        return $this->redirect('/')
            ->withErrors(['message' => 'Invalid or expired verification link']);
    }

    public function showForgotPasswordForm()
    {
        return $this->view('member/forgot-password');
    }

    public function sendPasswordResetEmail(Request $request)
    {
        $validated = $request->all();

        $member = Member::findByEmail($validated['email'], SiteContext::getId());

        // Always show success message to prevent email enumeration
        $successMessage = 'If that email exists, you will receive a password reset link.';

        if ($member) {
            $token = $this->passwordResetService->generateResetToken($member);
            $this->passwordResetService->sendResetEmail($member, $token);
        }

        return $this->back()->with('message', $successMessage);
    }

    public function showResetPasswordForm(Request $request)
    {
        $token = $request->query('token');

        if (!$token) {
            return $this->redirect('/')->withErrors(['message' => 'Invalid reset link']);
        }

        // Validate token
        if (!$this->passwordResetService->validateToken($token)) {
            return $this->redirect('/')
                ->withErrors(['message' => 'Invalid or expired reset link']);
        }

        return $this->view('member/reset-password', ['token' => $token]);
    }

    public function showChangePasswordForm()
    {
        if (!MemberAuth::check()) {
            return $this->redirect('/member/login');
        }

        return $this->view('member/change-password', [
            'member' => MemberAuth::member()
        ]);
    }

    public function changePassword(ChangePasswordRequest $request)
    {
        if (!MemberAuth::check()) {
            return $this->redirect('/member/login');
        }

        $member = MemberAuth::getMember();

        $validated = $request->validated();

        // Verify current password
        if (!password_verify($validated['current_password'], $member->password)) {
            return $this->back()->withErrors([
                'current_password' => 'Current password is incorrect'
            ]);
        }

        // Update to new password
        $member->update([
            'password' => password_hash($validated['new_password'], PASSWORD_DEFAULT)
        ]);

        return $this->redirect('/member/dashboard')
            ->with('message', 'Password changed successfully');
    }

    public function resetPassword(ResetPasswordRequest $request)
    {
        $validated = $request->validated();

        if ($this->passwordResetService->resetPassword($validated['token'], $validated['password'])) {
            return $this->redirect('/member/login')
                ->with('message', 'Password reset successfully. You can now login.');
        }

        return $this->back()
            ->withErrors(['message' => 'Invalid or expired reset link']);
    }

    public function dashboard()
    {
        if (!MemberAuth::check()) {
            return $this->redirect('/member/login');
        }

        $member = MemberAuth::member();

        return $this->view('member/dashboard', [
            'member' => $member,
            'site' => SiteContext::get()
        ]);
    }
}