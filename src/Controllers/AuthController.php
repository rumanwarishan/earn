<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Session;
use App\Services\AuthService;
use App\Support\ValidationException;

final class AuthController
{
    public function showRegister(Request $request): void
    {
        echo view('layouts.auth', [
            'pageTitle' => 'Create your account',
            'content' => view('auth.register', ['prefillCode' => $request->query('code', '')]),
        ]);
    }

    public function register(Request $request): void
    {
        try {
            $user = AuthService::register($request->all(), $request->ip());
            Session::flash('_success', 'Account created! Please check your email to verify your address before logging in.');
            redirect('/verify-email/pending?uid=' . $user['id']);
        } catch (ValidationException $e) {
            flash_errors($e->errors());
            flash_old($request->all());
            redirect('/register');
        }
    }

    public function showVerifyPending(Request $request): void
    {
        echo view('layouts.auth', [
            'pageTitle' => 'Verify your email',
            'content' => view('auth.verify-pending', ['uid' => (int) $request->query('uid', 0)]),
        ]);
    }

    public function verifyEmail(Request $request): void
    {
        $uid = (int) $request->query('uid', 0);
        $token = (string) $request->query('token', '');

        $ok = $uid && $token && AuthService::verifyEmail($uid, $token);

        echo view('layouts.auth', [
            'pageTitle' => 'Email verification',
            'content' => view('auth.verify-result', ['ok' => $ok]),
        ]);
    }

    public function resendVerification(Request $request): void
    {
        $uid = (int) $request->input('uid', 0);
        if ($uid > 0) {
            $pdo = \App\Core\Database::connection();
            $stmt = $pdo->prepare('SELECT id, email, full_name, email_verified_at FROM users WHERE id = ?');
            $stmt->execute([$uid]);
            $user = $stmt->fetch();
            if ($user && !$user['email_verified_at']) {
                AuthService::issueEmailVerification((int) $user['id'], $user['email'], $user['full_name'], $request->ip());
            }
        }
        Session::flash('_success', 'If that account exists and is unverified, a new verification email has been sent.');
        redirect('/verify-email/pending?uid=' . $uid);
    }

    public function showLogin(Request $request): void
    {
        echo view('layouts.auth', [
            'pageTitle' => 'Log in',
            'content' => view('auth.login'),
        ]);
    }

    public function login(Request $request): void
    {
        try {
            AuthService::login(
                (string) $request->input('email', ''),
                (string) $request->input('password', ''),
                $request->ip(),
                $request->userAgent()
            );
            redirect('/dashboard');
        } catch (ValidationException $e) {
            $errors = $e->errors();
            if (isset($errors['unverified_user_id'])) {
                redirect('/verify-email/pending?uid=' . $errors['unverified_user_id']);
            }
            flash_errors($errors);
            flash_old(['email' => $request->input('email', '')]);
            redirect('/login');
        }
    }

    public function logout(Request $request): void
    {
        AuthService::logout();
        redirect('/login');
    }

    public function showForgotPassword(Request $request): void
    {
        echo view('layouts.auth', [
            'pageTitle' => 'Reset your password',
            'content' => view('auth.forgot-password'),
        ]);
    }

    public function forgotPassword(Request $request): void
    {
        AuthService::requestPasswordReset((string) $request->input('email', ''), $request->ip());
        Session::flash('_success', 'If an account with that email exists, a password reset link has been sent.');
        redirect('/forgot-password');
    }

    public function showResetPassword(Request $request): void
    {
        echo view('layouts.auth', [
            'pageTitle' => 'Set a new password',
            'content' => view('auth.reset-password', [
                'uid' => (int) $request->query('uid', 0),
                'token' => (string) $request->query('token', ''),
            ]),
        ]);
    }

    public function resetPassword(Request $request): void
    {
        $uid = (int) $request->input('uid', 0);
        $token = (string) $request->input('token', '');

        try {
            $ok = AuthService::resetPassword($uid, $token, (string) $request->input('password', ''), $request->ip());
            if (!$ok) {
                flash_errors(['token' => 'This reset link is invalid or has expired.']);
                redirect('/forgot-password');
            }
            Session::flash('_success', 'Your password has been reset. You can now log in.');
            redirect('/login');
        } catch (ValidationException $e) {
            flash_errors($e->errors());
            redirect('/reset-password?uid=' . $uid . '&token=' . urlencode($token));
        }
    }
}
