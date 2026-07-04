<?php

namespace App\Controllers\Auth;

use App\Models\User;
use Whis\Cryptic\Hasher;
use Whis\Http\Controller;
use Whis\Http\Request;
use Whis\Http\Response;

class LoginController extends Controller
{
    public function create(): Response
    {
        if (! isGuest()) {
            return redirect('/');
        }

        /*
         * El token CSRF lo genera la vista con:
         *
         * @csrf('login')
         *
         * Y lo valida el middleware CSRF antes de llegar a store().
         */
        return view('auth/login', 'Login');
    }

    public function store(Request $request, Hasher $hasher): Response
    {
        /*
         * No uso $request->validate() aquí porque, dependiendo del Validator,
         * puede intentar regresar con back()->withErrors() antes de que podamos
         * responder en JSON.
         *
         * Para AJAX-JSON conviene controlar la respuesta con:
         * $this->validationError($request, ...)
         */
        $errors = $this->validateLoginInput($request);

        if (! empty($errors)) {
            return $this->validationError(
                $request,
                $errors,
                'Revisa tu correo y contraseña.',
                422
            );
        }

        $email = trim((string) $request->data('email'));
        $password = (string) $request->data('password');

        $user = User::firstWhere('email', $email);

        if (
            is_null($user)
            || ! $hasher->verify($password, (string) $user->password)
        ) {
            return $this->validationError(
                $request,
                [
                    'email' => [
                        'El correo o la contraseña no son correctos.',
                    ],
                ],
                'No se pudo iniciar sesión.',
                422
            );
        }

        $user->login();

        if ($this->expectsJson($request)) {
            return $this->jsonSuccess('Sesión iniciada correctamente.', [
                'redirect' => '/',
            ]);
        }

        return redirect('/');
    }

    public function destroy(): Response
    {
        if (isGuest()) {
            return redirect('/');
        }

        auth()->logout();

        return redirect('/');
    }

    private function validateLoginInput(Request $request): array
    {
        $errors = [];

        $email = trim((string) ($request->data('email') ?? ''));
        $password = (string) ($request->data('password') ?? '');

        if ($email === '') {
            $errors['email'][] = 'El correo electrónico es obligatorio.';
        } elseif (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'][] = 'Escribe un correo electrónico válido.';
        } elseif (mb_strlen($email) > 150) {
            $errors['email'][] = 'El correo electrónico no debe exceder 150 caracteres.';
        }

        if ($password === '') {
            $errors['password'][] = 'La contraseña es obligatoria.';
        }

        return $errors;
    }
}