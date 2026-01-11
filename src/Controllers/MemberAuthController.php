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
use App\Services\Cms\MenuRenderer;
use App\Services\EmailVerificationService;
use App\Services\PasswordResetService;

class MemberAuthController extends Controller
{
    public function __construct(
        private EmailVerificationService $emailVerificationService,
        private PasswordResetService $passwordResetService
    )
    {
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

        $existingMember = Member::findByEmail($validated['email'], $siteId);

        // Check if email already exists
        if ($existingMember) {
            if ($request->getHeader('X-Requested-With') === 'XMLHttpRequest' ||
                $request->getHeader('Content-Type') === 'application/json') {
                return $this->resourceResponse([
                    'success' => false,
                    'error' => 'email_exists',
                    'message' => 'Email already registered',
                    'is_verified' => $existingMember->isEmailVerified()
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
            'is_active' => true
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

        $requiresVerification = true;

        if ($request->getHeader('X-Requested-With') === 'XMLHttpRequest' ||
            $request->getHeader('Content-Type') === 'application/json') {
            return $this->resourceResponse([
                'success' => true,
                'message' => $requiresVerification ? 'Verification email sent successfully' : 'Registration successful',
                'requires_verification' => $requiresVerification,
                'member' => [
                    'id' => $member->id,
                    'email' => $member->email,
                    'first_name' => $member->first_name,
                    'last_name' => $member->last_name,
                    'is_verified' => $member->isEmailVerified()
                ]
            ]);
        }

        return $this->redirect('/member/verify-email-sent')
            ->with('email', $member->email);
    }

    public function resendVerification(Request $request)
    {
        if (!MemberAuth::check()) {
            return $this->errorResponse('Unauthorized', 401);
        }

        $member = MemberAuth::getMember();

        // Check if already verified
        if ($member->isEmailVerified()) {
            return $this->jsonResponse([
                'success' => false,
                'message' => 'Email is already verified'
            ]);
        }

        // Check rate limiting (e.g., once per 60 seconds)
        $lastSent = $request->session()->get('verification_email_sent_at');
        if ($lastSent && (time() - $lastSent) < 60) {
            $waitTime = 60 - (time() - $lastSent);
            return $this->jsonResponse([
                'success' => false,
                'message' => "Please wait {$waitTime} seconds before requesting another verification email"
            ]);
        }

        try {
            // Generate new verification token
            $token = $this->emailVerificationService->generateVerificationToken($member);

            // Send verification email
            $this->emailVerificationService->sendVerificationEmail($member, $token);

            // Update session timestamp
            $request->session()->put('verification_email_sent_at', time());

            return $this->jsonResponse([
                'success' => true,
                'message' => 'Verification email sent successfully'
            ]);
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to send verification email: ' . $e->getMessage(), 500);
        }
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
        $site = SiteContext::slug();

        if (MemberAuth::attempt($credentials)) {

            $intendedUrl = $request->session()->get('intended_url', '/' . $site . '/member/dashboard');
            $request->session()->forget('intended_url');

            if ($request->getHeader('X-Requested-With') === 'XMLHttpRequest' ||
                $request->getHeader('Content-Type') === 'application/json') {
                return $this->resourceResponse(['success' => true]);
            }

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

        $member = MemberAuth::getMember();

        return $this->view('member/dashboard', [
            'member' => $member,
            'site' => SiteContext::get()
        ]);
    }
}