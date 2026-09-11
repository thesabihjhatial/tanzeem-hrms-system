<?php

namespace App\Apps\Customers\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Utilities\AuthenticationManager;
use App\Utilities\CustomerManager;
use App\Utilities\ValidationManager;

class CustomerController extends Controller
{
    public function showSignup(Request $request): Response
    {
        return $this->signupView([], []);
    }

    public function signup(Request $request): Response
    {
        $result = CustomerManager::register($request->body);

        if (!$result['success']) {
            return $this->signupView($request->body, $result['errors']);
        }

        return $this->redirect('/employees');
    }

    public function showLogin(Request $request): Response
    {
        $fromSignup = ($request->query['ref'] ?? null) === 'signup';

        return $this->view('customers/login.twig', ['error' => null, 'show_splash' => !$fromSignup]);
    }

    public function login(Request $request): Response
    {
        $loggedIn = AuthenticationManager::attemptLogin(
            $request->body['email'] ?? '',
            $request->body['password'] ?? '',
        );

        if (!$loggedIn) {
            return $this->view('customers/login.twig', ['error' => 'Invalid email or password.', 'show_splash' => false]);
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

    /** @param array<string, mixed> $old @param array<string, string> $errors */
    private function signupView(array $old, array $errors): Response
    {
        return $this->view('customers/signup.twig', [
            'provinces' => CustomerManager::PROVINCES,
            'employee_count_ranges' => CustomerManager::EMPLOYEE_COUNT_RANGES,
            'old' => $old,
            'errors' => $errors,
        ]);
    }
}
