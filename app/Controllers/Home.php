<?php

namespace App\Controllers;

use Throwable;
use Whis\Http\Controller;
use Whis\Http\Request;
use Whis\Http\Response;
use Whis\Storage\File;

class Home extends Controller
{
    public function create(): Response
    {
        $userName = isGuest()
            ? 'Guest'
            : (auth()->name ?? 'Usuario');

        /*
         * OJO:
         * Tu helper view() tiene esta firma:
         *
         * view(string $view, ?string $pageName = null, array|string $parameters = [], ?string $layout = null)
         *
         * Por eso NO uses:
         * view('home', ['user' => $userName])
         *
         * Usa:
         * view('home', 'Inicio', ['user' => $userName])
         */
        return view('home', 'Inicio', [
            'user' => $userName,
        ]);
    }

    public function store(Request $request): Response
    {
        /*
         * CSRF:
         * No se valida aquí. Debe validarlo tu middleware CSRF.
         *
         * Tu ajax-form.js manda:
         * - Accept: application/json
         * - X-Requested-With: XMLHttpRequest
         * - X-CSRF-Token
         * - X-CSRF-Key si existe
         * - _token en el body
         * - _csrf_key si existe
         */

        $errors = $this->validateTestForm($request);

        if (!empty($errors)) {
            return $this->validationError(
                $request,
                $errors,
                'Revisa los campos marcados en rojo.',
                422
            );
        }

        try {
            $files = $this->uploadedFiles($request, 'files');

            foreach ($files as $file) {
                /*
                 * Si tu File::store() acepta ruta, puedes usar:
                 *
                 * $file->store('uploads/test');
                 *
                 * Si no acepta ruta, déjalo así:
                 */
                $file->store();
            }

            return $this->jsonSuccess('Formulario enviado correctamente.');
        } catch (Throwable $exception) {
            return $this->jsonError(
                'No se pudieron guardar los archivos.',
                [
                    'files' => [
                        'Ocurrió un error al procesar los archivos.',
                    ],
                ],
                500
            );
        }
    }

    private function validateTestForm(Request $request): array
    {
        $errors = [];

        $email = trim((string) ($request->data('email') ?? ''));
        $name  = trim((string) ($request->data('name') ?? ''));

        if ($email === '') {
            $errors['email'][] = 'El correo electrónico es obligatorio.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'][] = 'Escribe un correo electrónico válido.';
        } elseif (mb_strlen($email) > 150) {
            $errors['email'][] = 'El correo electrónico no debe exceder 150 caracteres.';
        }

        if ($name === '') {
            $errors['name'][] = 'El nombre es obligatorio.';
        } elseif (mb_strlen($name) < 3) {
            $errors['name'][] = 'El nombre debe tener al menos 3 caracteres.';
        } elseif (mb_strlen($name) > 120) {
            $errors['name'][] = 'El nombre no debe exceder 120 caracteres.';
        }

        $files = $this->uploadedFiles($request, 'files');

        if (count($files) < 1) {
            $errors['files'][] = 'Debes subir al menos un archivo.';
        }

        if (count($files) > 5) {
            $errors['files'][] = 'Solo puedes subir máximo 5 archivos.';
        }

        $allowedExtensions = ['png', 'jpg', 'jpeg', 'pdf', 'webp'];
        $maxFileSize      = 5 * 1024 * 1024;
        $maxTotalSize     = 20 * 1024 * 1024;
        $totalSize        = 0;

        foreach ($files as $file) {
            $fileName = $this->fileName($file);
            $fileSize = $this->fileSize($file);
            $extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

            $totalSize += $fileSize;

            if ($fileName === '') {
                $errors['files'][] = 'Uno de los archivos no es válido.';
                continue;
            }

            if (!in_array($extension, $allowedExtensions, true)) {
                $errors['files'][] = "{$fileName} tiene un tipo de archivo no permitido.";
            }

            if ($fileSize > $maxFileSize) {
                $errors['files'][] = "{$fileName} excede el tamaño máximo permitido de 5 MB.";
            }
        }

        if ($totalSize > $maxTotalSize) {
            $errors['files'][] = 'El peso total de los archivos no debe exceder 20 MB.';
        }

        return $errors;
    }

    private function uploadedFiles(Request $request, string $name): array
    {
        /*
         * Compatible con:
         * - name="files"
         * - name="files[]"
         * - un solo File
         * - array de File
         */
        $files = $request->files($name);

        if ($files === null) {
            $files = $request->files($name . '[]');
        }

        if ($files === null) {
            $files = $request->file($name);
        }

        if ($files === null) {
            $files = $request->file($name . '[]');
        }

        if ($files === null) {
            return [];
        }

        if ($files instanceof File) {
            return [$files];
        }

        if (is_array($files)) {
            return array_values(array_filter($files, function ($file) {
                return $file instanceof File;
            }));
        }

        return [];
    }

    private function fileName(File $file): string
    {
        foreach (['name', 'filename', 'originalName', 'original_name'] as $property) {
            if (isset($file->{$property}) && is_string($file->{$property})) {
                return $file->{$property};
            }
        }

        foreach (['name', 'filename', 'originalName', 'original_name', 'getClientOriginalName'] as $method) {
            if (method_exists($file, $method)) {
                $value = $file->{$method}();

                if (is_string($value)) {
                    return $value;
                }
            }
        }

        return '';
    }

    private function fileSize(File $file): int
    {
        foreach (['size', 'filesize'] as $property) {
            if (isset($file->{$property}) && is_numeric($file->{$property})) {
                return (int) $file->{$property};
            }
        }

        foreach (['size', 'filesize', 'getSize'] as $method) {
            if (method_exists($file, $method)) {
                $value = $file->{$method}();

                if (is_numeric($value)) {
                    return (int) $value;
                }
            }
        }

        return 0;
    }
}