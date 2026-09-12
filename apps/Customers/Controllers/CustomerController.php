<?php

namespace App\Apps\Customers\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Utilities\AuthenticationManager;
use App\Utilities\BillingManager;
use App\Utilities\CsrfManager;
use App\Utilities\CustomerManager;
use App\Utilities\EnvironmentManager;
use App\Utilities\FlashManager;
use App\Utilities\ValidationManager;

class CustomerController extends Controller
{
    private const ERROR_SESSION_EXPIRED = 'Session has expired.';

    public function showSignup(Request $request): Response
    {
        return $this->signupView([], []);
    }

    public function signup(Request $request): Response
    {
        if (!CsrfManager::verify($request->body['csrf_token'] ?? null)) {
            FlashManager::add(self::ERROR_SESSION_EXPIRED, 'error');
            return $this->redirect('/signup');
        }

        $result = CustomerManager::startRegistration($request->body);

        if (!$result['success']) {
            return $this->signupView($request->body, $result['errors']);
        }

        if (!EnvironmentManager::isProduction()) {
            FlashManager::add("Your verification code is {$result['otp']}.", 'info');
        }

        return $this->redirect('/signup/verify');
    }

    public function showVerify(Request $request): Response
    {
        if (!CustomerManager::hasPendingRegistration()) {
            return $this->redirect('/signup');
        }

        return $this->verifyView(null);
    }

    public function verify(Request $request): Response
    {
        if (!CustomerManager::hasPendingRegistration()) {
            return $this->redirect('/signup');
        }

        if (!CsrfManager::verify($request->body['csrf_token'] ?? null)) {
            FlashManager::add(self::ERROR_SESSION_EXPIRED, 'error');
            return $this->redirect('/signup/verify');
        }

        $result = CustomerManager::confirmRegistration($request->body['otp'] ?? '');

        if (!$result['success']) {
            return $this->verifyView($result['error']);
        }

        return $this->redirect('/signup/plan');
    }

    public function showPlan(Request $request): Response
    {
        if (!CustomerManager::hasVerifiedPendingRegistration()) {
            return $this->redirect('/signup');
        }

        return $this->planView(null);
    }

    public function choosePlan(Request $request): Response
    {
        if (!CustomerManager::hasVerifiedPendingRegistration()) {
            return $this->redirect('/signup');
        }

        if (!CsrfManager::verify($request->body['csrf_token'] ?? null)) {
            FlashManager::add(self::ERROR_SESSION_EXPIRED, 'error');
            return $this->redirect('/signup/plan');
        }

        $planId = (int) ($request->body['plan_id'] ?? 0);
        $result = CustomerManager::finalizeRegistration($planId);

        if (!$result['success']) {
            return $this->planView($result['error']);
        }

        FlashManager::add('Welcome to Tanzeem!', 'success');

        return $this->redirect('/employees');
    }

    public function showLogin(Request $request): Response
    {
        $fromSignup = ($request->query['ref'] ?? null) === 'signup';

        return $this->loginView(null, !$fromSignup);
    }

    public function login(Request $request): Response
    {
        if (!CsrfManager::verify($request->body['csrf_token'] ?? null)) {
            return $this->loginView(self::ERROR_SESSION_EXPIRED, false);
        }

        $loggedIn = AuthenticationManager::attemptLogin(
            $request->body['email'] ?? '',
            $request->body['password'] ?? '',
        );

        if (!$loggedIn) {
            return $this->loginView('Credentials are invalid.', false);
        }

        return $this->redirect('/employees');
    }

    public function logout(Request $request): Response
    {
        AuthenticationManager::logout();

        return $this->redirect('/login');
    }

    /**
     * Async pwned-password check called on blur while signing up (see
     * data-check-pwned in components/input.twig and validation.js).
     * Deliberately unauthenticated — there's no account yet at signup time.
     */
    public function checkPassword(Request $request): Response
    {
        $pwned = ValidationManager::isPasswordPwned($request->body['password'] ?? '');

        return $this->json(['pwned' => $pwned]);
    }

    /**
     * Async email-availability check called on blur while signing up (see
     * data-check-email in components/input.twig and validation.js).
     * Deliberately unauthenticated — there's no account yet at signup time.
     */
    public function checkEmail(Request $request): Response
    {
        $taken = CustomerManager::findUserByEmail($request->body['email'] ?? '') !== null;

        return $this->json(['taken' => $taken]);
    }

    /**
     * Async CNIC-availability check called on blur while signing up (see
     * data-check-cnic in components/input.twig and validation.js).
     * Deliberately unauthenticated — there's no account yet at signup time.
     */
    public function checkCnic(Request $request): Response
    {
        $taken = CustomerManager::findUserByCnic($request->body['cnic'] ?? '') !== null;

        return $this->json(['taken' => $taken]);
    }

    /** @param array<string, mixed> $old @param array<string, string> $errors */
    private function signupView(array $old, array $errors): Response
    {
        return $this->view('customers/signup.twig', [
            'provinces' => CustomerManager::PROVINCES,
            'employee_count_ranges' => CustomerManager::EMPLOYEE_COUNT_RANGES,
            'old' => $old,
            'errors' => $errors,
            'form_token' => CustomerManager::generateFormToken(),
            'csrf_token' => CsrfManager::token(),
        ]);
    }

    private function verifyView(?string $error): Response
    {
        return $this->view('customers/verify.twig', [
            'error' => $error,
            'csrf_token' => CsrfManager::token(),
        ]);
    }

    private function planView(?string $error): Response
    {
        $pendingCustomer = CustomerManager::pendingSignupCustomerData();

        return $this->view('customers/plan.twig', [
            'plans' => BillingManager::listPlansForSelection($pendingCustomer['employee_count_range'] ?? ''),
            'error' => $error,
            'csrf_token' => CsrfManager::token(),
        ]);
    }

    private function loginView(?string $error, bool $showSplash): Response
    {
        return $this->view('customers/login.twig', [
            'error' => $error,
            'show_splash' => $showSplash,
            'csrf_token' => CsrfManager::token(),
        ]);
    }
}
