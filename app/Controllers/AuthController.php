<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\UserRepository;
use App\Support\Config;
use App\Support\Flash;
use App\Support\HttpException;
use App\Support\Logger;
use App\Support\Mailer;
use App\Support\RateLimiter;
use App\Support\Request;
use App\Support\Response;
use App\Support\Str;
use App\Support\Url;
use App\Support\Validator;

final class AuthController extends Controller
{
    private UserRepository $users;

    public function __construct()
    {
        parent::__construct();

        $this->users = new UserRepository();
    }

    // ------------------------------------------------------------------
    // Sign in
    // ------------------------------------------------------------------

    public function loginForm(Request $request): Response
    {
        $this->view->setLayout('layouts/narrow');
        $this->view->title('Sign in');
        $this->view->meta('robots', 'noindex');

        return $this->render('auth/login', [
            'registration_open' => $this->settings->bool('registration_enabled', true),
        ]);
    }

    public function login(Request $request): Response
    {
        $identifier = (string) $request->input('identifier', '');
        $password = (string) ($request->all()['password'] ?? '');

        $validator = Validator::make(['identifier' => $identifier, 'password' => $password])
            ->label('identifier', 'Username or e-mail')
            ->required('identifier')
            ->required('password');

        if ($validator->fails()) {
            return $this->withErrors($validator->errors(), ['identifier' => $identifier], Url::route('auth.login.show'));
        }

        $result = $this->auth->attempt($identifier, $password, $request->ip(), $request->userAgent());

        if (!$result['ok']) {
            Flash::error((string) $result['message']);

            return $this->withErrors(['identifier' => (string) $result['message']], ['identifier' => $identifier], Url::route('auth.login.show'));
        }

        // A member serving a suspension is told on the way in, not left to
        // discover it when a button stops working.
        $restriction = $this->auth->activeRestriction();

        if ($restriction !== null) {
            Flash::warning(sprintf(
                'Signed in. Your account is %s: %s',
                (string) $restriction['type'] === 'ban' ? 'banned' : 'suspended',
                (string) $restriction['reason'],
            ));

            return $this->redirect(Url::route('settings.record'));
        }

        Flash::success('Signed in. Welcome back, ' . (string) $result['user']['username'] . '.');

        return $this->redirect($this->intendedUrl(Url::route('home')));
    }

    public function logout(Request $request): Response
    {
        $this->auth->logout();
        Flash::info('You have been signed out.');

        return $this->redirect(Url::route('home'));
    }

    // ------------------------------------------------------------------
    // Registration
    // ------------------------------------------------------------------

    public function registerForm(Request $request): Response
    {
        if (!$this->settings->bool('registration_enabled', true)) {
            $this->view->setLayout('layouts/narrow');
            $this->view->title('Registration closed');

            return $this->render('auth/registration-closed', [
                'message' => $this->settings->string('registration_closed_message', 'Registration is closed at the moment.'),
            ], 403);
        }

        $this->view->setLayout('layouts/narrow');
        $this->view->title('Create an account');
        $this->view->meta('robots', 'noindex');

        return $this->render('auth/register', [
            'rules' => $this->settings->string('board_rules', ''),
            'min_password_length' => (int) Config::get('security.password.min_length', 10),
        ]);
    }

    public function register(Request $request): Response
    {
        if (!$this->settings->bool('registration_enabled', true)) {
            throw HttpException::forbidden('Registration is closed at the moment.');
        }

        $input = $request->all();
        $username = (string) $request->input('username', '');
        $email = (string) $request->input('email', '');
        $password = (string) ($input['password'] ?? '');
        $confirmation = (string) ($input['password_confirmation'] ?? '');
        $formUrl = Url::route('auth.register.show');

        $validator = Validator::make([
            'username' => $username,
            'email' => $email,
            'password' => $password,
            'password_confirmation' => $confirmation,
        ])
            ->required('username')->username('username')
            ->required('email')->email('email')->maxLength('email', 190)
            ->required('password')->password('password')
            ->label('password_confirmation', 'Password confirmation')
            ->matches('password_confirmation', 'password');

        if (!$request->bool('accept_rules')) {
            $validator->fail('accept_rules', 'You must accept the board rules to register.');
        }

        if ($validator->passes()) {
            if ($this->users->usernameTaken($username)) {
                $validator->fail('username', 'That username is already registered.');
            }

            if ($this->users->emailTaken($email)) {
                $validator->fail('email', 'That e-mail address is already registered.');
            }
        }

        if ($validator->fails()) {
            return $this->withErrors($validator->errors(), ['username' => $username, 'email' => $email], $formUrl);
        }

        $result = $this->auth->register(
            ['username' => $username, 'email' => $email, 'password' => $password],
            $request->ip(),
        );

        if (!$result['ok']) {
            Flash::error((string) $result['message']);

            return $this->redirect($formUrl);
        }

        $user = $this->users->find((int) $result['user_id']);

        if ($user !== null) {
            $this->auth->login($user, $request->ip());
        }

        Flash::success('Account created. Welcome to ' . $this->settings->string('site_name', 'the board') . '.');

        return $this->redirect(Url::route('home'));
    }

    // ------------------------------------------------------------------
    // Password reset
    // ------------------------------------------------------------------

    public function forgotForm(Request $request): Response
    {
        $this->view->setLayout('layouts/narrow');
        $this->view->title('Forgotten password');
        $this->view->meta('robots', 'noindex');

        return $this->render('auth/forgot-password');
    }

    public function sendResetLink(Request $request): Response
    {
        $email = (string) $request->input('email', '');
        $formUrl = Url::route('auth.forgot.show');

        $validator = Validator::make(['email' => $email])->required('email')->email('email');

        if ($validator->fails()) {
            return $this->withErrors($validator->errors(), ['email' => $email], $formUrl);
        }

        $throttleKey = 'reset:' . mb_strtolower($email) . '|' . $request->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, 'password-reset')) {
            Flash::error('Too many reset requests. Try again later.');

            return $this->redirect($formUrl);
        }

        RateLimiter::hit($throttleKey, 'password-reset');

        $user = $this->users->findByEmail($email);

        if ($user !== null) {
            $token = $this->auth->createPasswordReset((int) $user['id'], $request->ip());
            $link = Url::absolute(Url::route('auth.reset.show', [], ['token' => $token]));

            Mailer::send(
                (string) $user['email'],
                'Password reset for ' . $this->settings->string('site_name', 'the board'),
                implode("\n", [
                    'Hello ' . (string) $user['username'] . ',',
                    '',
                    'A password reset was requested for your account. Open the link below to choose a new password:',
                    $link,
                    '',
                    'The link is valid for ' . intdiv((int) Config::get('security.reset_token_lifetime', 3600), 60) . ' minutes.',
                    'If this was not you, ignore this message — nothing has changed.',
                ]),
            );

            Logger::security('Password reset requested', ['user_id' => $user['id'], 'ip' => $request->ip()]);
        }

        // The same answer either way: account existence is not disclosed.
        Flash::success('If that address belongs to an account, a reset link is on its way.');

        return $this->redirect(Url::route('auth.login.show'));
    }

    public function resetForm(Request $request): Response
    {
        $token = (string) $request->input('token', '');
        $reset = $token === '' ? null : $this->auth->findValidReset($token);

        $this->view->setLayout('layouts/narrow');
        $this->view->title('Choose a new password');
        $this->view->meta('robots', 'noindex');

        if ($reset === null) {
            return $this->render('auth/reset-invalid', [], 400);
        }

        return $this->render('auth/reset-password', [
            'token' => $token,
            'username' => (string) $reset['username'],
            'min_password_length' => (int) Config::get('security.password.min_length', 10),
        ]);
    }

    public function resetPassword(Request $request): Response
    {
        $input = $request->all();
        $token = (string) $request->input('token', '');
        $password = (string) ($input['password'] ?? '');
        $confirmation = (string) ($input['password_confirmation'] ?? '');

        $reset = $token === '' ? null : $this->auth->findValidReset($token);

        if ($reset === null) {
            Flash::error('That reset link is invalid or has expired.');

            return $this->redirect(Url::route('auth.forgot.show'));
        }

        $validator = Validator::make([
            'password' => $password,
            'password_confirmation' => $confirmation,
        ])
            ->required('password')->password('password')
            ->label('password_confirmation', 'Password confirmation')
            ->matches('password_confirmation', 'password');

        if ($validator->fails()) {
            return $this->withErrors($validator->errors(), [], Url::route('auth.reset.show', [], ['token' => $token]));
        }

        $this->auth->changePassword((int) $reset['user_id'], $password);
        $this->auth->consumeReset((int) $reset['id']);

        Logger::security('Password reset completed', ['user_id' => $reset['user_id'], 'ip' => $request->ip()]);
        Flash::success('Your password was changed. You can sign in now.');

        return $this->redirect(Url::route('auth.login.show'));
    }
}
