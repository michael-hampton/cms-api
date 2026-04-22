<?php

declare(strict_types=1);

namespace App\Controllers\MerchantPortal;

use App\Controllers\Controller;
use App\Framework\Http\Request;
use App\Framework\Session\Session;
use App\Services\MerchantPortal\MerchantPortalRegistrationService;
use RuntimeException;

/**
 * Handles merchant portal self-registration (show form + handle submission).
 *
 * Routes:
 *   GET  /merchant/register          → showRegistrationForm()
 *   POST /merchant/register          → register()
 */
final class MerchantPortalRegistrationController extends Controller
{
    public function __construct(
        private readonly MerchantPortalRegistrationService $registrationService,
    )
    {
        parent::__construct();
    }

    // ──────────────────────────────────────────────────────────────────────────

    /**
     * GET /merchant/register
     */
    public function showRegistrationForm(): mixed
    {
        return $this->view('merchant-portal/register', [
            'error' => Session::getFlash('register_error'),
            'success' => Session::getFlash('register_success'),
            'old' => Session::getFlash('register_old') ?? [],
        ]);
    }

    /**
     * POST /merchant/register
     */
    public function register(Request $request): mixed
    {
        $email = trim($request->input('email', ''));
        $name = trim($request->input('name', ''));
        $companyName = trim($request->input('company_name', ''));
        $phone = trim($request->input('phone', ''));
        $password = $request->input('password', '');
        $confirmation = $request->input('password_confirmation', '');

        // ── Basic validation ─────────────────────────────────────────────────
        $errors = $this->validate($email, $name, $password, $confirmation);

        if ($errors) {
            Session::flash('register_error', implode(' ', $errors));
            Session::flash('register_old', [
                'email' => $email,
                'name' => $name,
                'company_name' => $companyName,
                'phone' => $phone,
            ]);
            return $this->redirect('/merchant/register');
        }

        // ── Delegate to service ──────────────────────────────────────────────
        try {
            $this->registrationService->register([
                'email' => $email,
                'name' => $name,
                'company_name' => $companyName ?: $name,
                'phone' => $phone ?: null,
                'password' => $password,
            ]);
        } catch (RuntimeException $e) {
            Session::flash('register_error', $e->getMessage());
            Session::flash('register_old', [
                'email' => $email,
                'name' => $name,
                'company_name' => $companyName,
                'phone' => $phone,
            ]);
            return $this->redirect('/merchant/register');
        }

        Session::flash(
            'register_success',
            'Your account has been created. Please log in — access will be activated once your account is reviewed.'
        );

        return $this->redirect('/merchant/login');
    }

    // ──────────────────────────────────────────────────────────────────────────

    /** @return string[] */
    private function validate(
        string $email,
        string $name,
        string $password,
        string $confirmation,
    ): array
    {
        $errors = [];

        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'A valid email address is required.';
        }

        if (empty($name)) {
            $errors[] = 'Full name is required.';
        }

        if (strlen($password) < 8) {
            $errors[] = 'Password must be at least 8 characters.';
        }

        if ($password !== $confirmation) {
            $errors[] = 'Passwords do not match.';
        }

        return $errors;
    }
}