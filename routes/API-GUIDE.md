# Guía completa de Whis API Core

**Versión sugerida:** Whis / Whis-Core API Core 1.0  
**Fecha:** 2026-07-04  
**Objetivo:** agregar a Whis una capa API sólida, segura y fácil de implementar, compatible con el sistema web actual de sesión, CSRF, controladores, rutas, middlewares y respuestas JSON.

---

## Índice

1. [Qué es Whis API Core](#1-qué-es-whis-api-core)
2. [Idea general de la arquitectura](#2-idea-general-de-la-arquitectura)
3. [Formas de autenticación disponibles](#3-formas-de-autenticación-disponibles)
4. [Estructura de archivos](#4-estructura-de-archivos)
5. [Instalación paso a paso](#5-instalación-paso-a-paso)
6. [Variables de entorno y configuración](#6-variables-de-entorno-y-configuración)
7. [Base de datos: tabla `api_tokens`](#7-base-de-datos-tabla-api_tokens)
8. [Explicación archivo por archivo](#8-explicación-archivo-por-archivo)
9. [Cómo crear rutas API](#9-cómo-crear-rutas-api)
10. [Cómo crear controladores API](#10-cómo-crear-controladores-api)
11. [Cómo proteger una API con Bearer Token o JWT](#11-cómo-proteger-una-api-con-bearer-token-o-jwt)
12. [Cómo crear tokens persistentes](#12-cómo-crear-tokens-persistentes)
13. [Cómo usar JWT](#13-cómo-usar-jwt)
14. [Abilities / permisos de API](#14-abilities--permisos-de-api)
15. [Formato recomendado de respuestas JSON](#15-formato-recomendado-de-respuestas-json)
16. [CSRF vs Bearer Token](#16-csrf-vs-bearer-token)
17. [CORS y preflight `OPTIONS`](#17-cors-y-preflight-options)
18. [Ejemplo completo: API de proyectos](#18-ejemplo-completo-api-de-proyectos)
19. [Consumir la API desde curl, Postman y JavaScript](#19-consumir-la-api-desde-curl-postman-y-javascript)
20. [Gestión administrativa de tokens](#20-gestión-administrativa-de-tokens)
21. [Seguridad recomendada](#21-seguridad-recomendada)
22. [Errores comunes y solución](#22-errores-comunes-y-solución)
23. [Checklist de implementación](#23-checklist-de-implementación)
24. [Resumen rápido para usuarios nuevos](#24-resumen-rápido-para-usuarios-nuevos)

---

# 1. Qué es Whis API Core

Whis API Core es una capa encima de Whis / Whis-Core para crear APIs modernas sin romper el funcionamiento actual del framework.

Permite que el mismo proyecto tenga:

- Rutas web tradicionales con sesión, cookies, formularios y CSRF.
- Rutas API con JSON.
- Autenticación por `Authorization: Bearer`.
- Tokens persistentes en base de datos.
- JSON Web Tokens, también llamados JWT.
- Permisos por token usando abilities, por ejemplo `projects:read` o `projects:write`.
- Middlewares reutilizables.
- Contexto API integrado con `auth()`.

La intención es que un usuario pueda empezar con algo tan simple como esto:

```php
Route::group('/api', function () {
    Route::get('/ping', function () {
        return Response::json([
            'ok' => true,
            'message' => 'API funcionando.',
        ]);
    });
});
```

Y después escalar a algo más robusto:

```php
Route::group('/api', function () {
    Route::group('', function () {
        Route::get('/projects', [ProjectApiController::class, 'index'])
            ->middleware(new ApiAbilityMiddleware('projects:read'));

        Route::post('/projects', [ProjectApiController::class, 'store'])
            ->middleware(new ApiAbilityMiddleware('projects:write'));
    }, [ApiAuthMiddleware::class]);
}, [ApiCorsMiddleware::class]);
```

---

# 2. Idea general de la arquitectura

Whis ya tiene una arquitectura sólida para web:

```txt
Request → Router → Route → Middleware → Controller → Response
```

Whis API Core no cambia esa idea. La extiende:

```txt
Request API
    ↓
Route::group('/api')
    ↓
ApiCorsMiddleware
    ↓
ApiAuthMiddleware
    ↓
ApiTokenGuard o JwtGuard
    ↓
Auth::setApiContext(...)
    ↓
Controller API
    ↓
Response::json(...)
```

El punto clave es el **contexto API**.

Cuando llega una petición con:

```http
Authorization: Bearer whis_xxxxxxxxx
```

o con:

```http
Authorization: Bearer eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...
```

Whis intenta autenticarla. Si la autenticación es correcta, se guarda temporalmente un contexto API y entonces:

```php
auth()
```

puede regresar el usuario autenticado por API, no solo el usuario de sesión.

Esto permite que tus controladores usen la misma idea mental:

```php
$user = auth();
```

sin importar si viene de sesión web o de API.

---

# 3. Formas de autenticación disponibles

Whis API Core soporta dos formas principales:

## 3.1 Token persistente en base de datos

Es un token largo generado una sola vez, por ejemplo:

```txt
whis_f0d7f14f5c8a9c0a7d5f0e3e2b...
```

El token completo **nunca se guarda en la base de datos**.

Se guarda únicamente:

```php
hash('sha256', $plainTextToken)
```

Esto significa que si alguien ve la tabla `api_tokens`, no puede usar directamente los tokens.

Uso recomendado:

- Integraciones externas.
- Postman.
- Servicios internos.
- Automatizaciones.
- APIs server-to-server.
- Tokens que quieres poder revocar manualmente.

## 3.2 JWT

Un JWT es un token firmado que contiene información dentro del propio token.

Ejemplo visual:

```txt
eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJzdWIiOiIxIiwiaWF0Ijox...
```

Whis API Core usa `HS256`, es decir, HMAC SHA-256 con una clave secreta.

Uso recomendado:

- Login desde frontend desacoplado.
- Apps móviles.
- SPAs.
- Sesiones API de corta duración.

## 3.3 Qué usar

Usa **token persistente** cuando quieras dar una llave a una integración o servicio.

Usa **JWT** cuando el usuario inicia sesión con email y password y recibe un token temporal.

---

# 4. Estructura de archivos

La estructura base del paquete es esta:

```txt
whis-api-core/
├── app/
│   ├── Controllers/
│   │   └── Api/
│   │       └── AuthController.php
│   ├── Middlewares/
│   │   ├── ApiAbilityMiddleware.php
│   │   ├── ApiAuthMiddleware.php
│   │   ├── ApiCorsMiddleware.php
│   │   ├── ApiTokenMiddleware.php
│   │   └── JwtMiddleware.php
│   └── Models/
│       └── ApiToken.php
├── config/
│   └── api.php
├── database/
│   └── create_api_tokens.sql
├── routes/
│   └── api.php
└── src/
    ├── Auth/
    │   ├── Auth.php
    │   ├── Authenticatable_api_methods.patch.php
    │   └── Api/
    │       ├── ApiAuthContext.php
    │       ├── ApiTokenGuard.php
    │       ├── Jwt.php
    │       └── JwtGuard.php
    ├── Database/
    │   └── Model_toArray_fix.patch.php
    ├── Helpers/
    │   └── api_auth.php
    └── Http/
        ├── HttpMethod_patch.php
        ├── Request_api_methods.patch.php
        └── Response_api_methods.patch.php
```

---

# 5. Instalación paso a paso

## 5.1 Copiar archivos del core

Copia estos archivos al core de Whis:

```txt
src/Auth/Api/ApiAuthContext.php
src/Auth/Api/ApiTokenGuard.php
src/Auth/Api/Jwt.php
src/Auth/Api/JwtGuard.php
```

Después reemplaza o fusiona:

```txt
src/Auth/Auth.php
```

La nueva versión de `Auth` conserva la autenticación por sesión, pero agrega soporte para API.

## 5.2 Agregar patches al core

Los archivos `*.patch.php` no son archivos finales para ejecutar directamente. Son fragmentos para copiar dentro de clases existentes.

Debes agregar el contenido de:

```txt
src/Http/Request_api_methods.patch.php
```

dentro de:

```txt
src/Http/Request.php
```

Debes agregar el contenido de:

```txt
src/Http/Response_api_methods.patch.php
```

dentro de:

```txt
src/Http/Response.php
```

Debes agregar el contenido de:

```txt
src/Auth/Authenticatable_api_methods.patch.php
```

dentro de:

```txt
src/Auth/Authenticatable.php
```

Debes reemplazar el método `toArray()` de:

```txt
src/Database/Model.php
```

por el método que viene en:

```txt
src/Database/Model_toArray_fix.patch.php
```

## 5.3 Copiar archivos de app

Copia:

```txt
app/Controllers/Api/AuthController.php
app/Middlewares/ApiAbilityMiddleware.php
app/Middlewares/ApiAuthMiddleware.php
app/Middlewares/ApiCorsMiddleware.php
app/Middlewares/ApiTokenMiddleware.php
app/Middlewares/JwtMiddleware.php
app/Models/ApiToken.php
```

## 5.4 Crear tabla de base de datos

Ejecuta:

```sql
CREATE TABLE IF NOT EXISTS api_tokens (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    tokenable_type VARCHAR(190) NULL,
    tokenable_id BIGINT UNSIGNED NULL,

    name VARCHAR(190) NOT NULL,
    token_prefix VARCHAR(24) NOT NULL,
    token_hash CHAR(64) NOT NULL,

    abilities TEXT NULL,

    expires_at DATETIME NULL,
    last_used_at DATETIME NULL,
    last_used_ip VARCHAR(64) NULL,
    last_used_user_agent VARCHAR(255) NULL,
    revoked_at DATETIME NULL,

    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY api_tokens_token_hash_unique (token_hash),
    INDEX api_tokens_token_prefix_index (token_prefix),
    INDEX api_tokens_tokenable_index (tokenable_type, tokenable_id),
    INDEX api_tokens_revoked_expires_index (revoked_at, expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

## 5.5 Registrar helpers

Agrega:

```txt
src/Helpers/api_auth.php
```

al sistema de helpers de Whis o al autoload de Composer.

Ejemplo en `composer.json`, dependiendo de cómo tengas organizado Whis:

```json
{
  "autoload": {
    "files": [
      "src/Helpers/api_auth.php"
    ]
  }
}
```

Después ejecuta:

```bash
composer dump-autoload
```

## 5.6 Cargar rutas API

Incluye:

```txt
routes/api.php
```

en el archivo donde cargas tus rutas.

Ejemplo:

```php
require_once __DIR__ . '/../routes/web.php';
require_once __DIR__ . '/../routes/api.php';
```

El orden recomendado es:

```txt
1. Rutas web
2. Rutas admin
3. Rutas API
4. Rutas de storage o assets dinámicos
```

Si tienes rutas comodín o rutas de archivos, déjalas al final.

---

# 6. Variables de entorno y configuración

El archivo principal es:

```txt
config/api.php
```

Contenido base:

```php
<?php

return [
    'auth' => [
        'user_model' => App\Models\User::class,
    ],

    'tokens' => [
        'prefix' => 'whis_',
    ],

    'jwt' => [
        'secret' => $_ENV['JWT_SECRET'] ?? $_ENV['APP_KEY'] ?? null,
        'ttl' => 3600,
        'leeway' => 5,
        'issuer' => $_ENV['APP_URL'] ?? 'whis',
        'audience' => null,
    ],

    'cors' => [
        'origin' => $_ENV['API_CORS_ORIGIN'] ?? '*',
    ],
];
```

## 6.1 `.env` recomendado

```env
APP_URL=http://localhost
APP_KEY=base64-o-string-largo-seguro

JWT_SECRET=clave-muy-larga-para-firmar-jwt
API_CORS_ORIGIN=*
```

Para producción, no uses una clave débil.

Ejemplo de clave aceptable:

```txt
zUhf81f3JH29xw93dXNq1c2R6Wvck9HcKJ0V0kXfM5pz84QW
```

## 6.2 `api.auth.user_model`

Define qué modelo se usa como usuario autenticable por defecto.

Normalmente será:

```php
App\Models\User::class
```

Ese modelo debe extender de:

```php
Whis\Auth\Authenticatable
```

## 6.3 `api.tokens.prefix`

Define el prefijo de los tokens persistentes.

Por defecto:

```txt
whis_
```

Esto permite reconocer visualmente un token de Whis.

## 6.4 `api.jwt.ttl`

Define la duración de los JWT en segundos.

Ejemplo:

```php
'jwt' => [
    'ttl' => 3600,
]
```

`3600` equivale a una hora.

## 6.5 `api.jwt.leeway`

Margen pequeño para tolerar diferencias de reloj entre servidores.

Ejemplo:

```php
'leeway' => 5
```

Permite una tolerancia de 5 segundos.

---

# 7. Base de datos: tabla `api_tokens`

La tabla `api_tokens` guarda tokens persistentes.

Campos principales:

| Campo | Uso |
|---|---|
| `id` | Identificador interno del token. |
| `tokenable_type` | Clase del dueño del token, por ejemplo `App\Models\User`. |
| `tokenable_id` | ID del dueño del token. |
| `name` | Nombre visible del token, por ejemplo `Postman`, `Mobile App`, `Integración CRM`. |
| `token_prefix` | Primeros caracteres del token plano para identificarlo visualmente. |
| `token_hash` | Hash SHA-256 del token. El token real no se guarda. |
| `abilities` | Permisos del token en JSON. |
| `expires_at` | Fecha opcional de expiración. `NULL` significa que no expira. |
| `last_used_at` | Última vez que se usó el token. |
| `last_used_ip` | IP del último uso. |
| `last_used_user_agent` | User-Agent del último uso. |
| `revoked_at` | Fecha de revocación. Si tiene valor, el token ya no sirve. |
| `created_at` | Fecha de creación. |
| `updated_at` | Fecha de actualización. |

## 7.1 Por qué se usa `token_hash`

Nunca debes guardar el token plano completo.

Flujo correcto:

```txt
Usuario crea token
    ↓
Whis genera whis_xxxxxxxxx
    ↓
Whis muestra el token una sola vez
    ↓
Whis guarda hash('sha256', token)
    ↓
Cliente usa token en Authorization: Bearer
    ↓
Whis vuelve a hashear el token recibido
    ↓
Whis compara hashes
```

Esto es más seguro que guardar tokens en texto plano.

---

# 8. Explicación archivo por archivo

## 8.1 `config/api.php`

Archivo de configuración central de la API.

Controla:

- Modelo autenticable por defecto.
- Prefijo de tokens persistentes.
- Configuración JWT.
- Configuración CORS.

Es el archivo que debes modificar cuando quieras cambiar comportamiento global.

Ejemplo:

```php
return [
    'tokens' => [
        'prefix' => 'whis_',
    ],

    'jwt' => [
        'ttl' => 3600,
    ],
];
```

---

## 8.2 `database/create_api_tokens.sql`

Crea la tabla necesaria para tokens persistentes.

Solo es necesaria si usarás tokens persistentes tipo:

```txt
whis_xxxxxxxxx
```

JWT no necesita tabla para existir, porque es stateless. Aun así, es recomendable tener ambas opciones.

---

## 8.3 `routes/api.php`

Archivo donde viven las rutas API.

Ejemplo base:

```php
Route::group('/api', function () {
    Route::post('/auth/login', [AuthController::class, 'login']);

    Route::group('', function () {
        Route::get('/me', [AuthController::class, 'me']);
    }, [ApiAuthMiddleware::class]);
}, [ApiCorsMiddleware::class]);
```

La idea es separar mentalmente:

```txt
routes/web.php   → páginas, formularios, sesión, CSRF
routes/api.php   → JSON, Bearer Token, JWT, integraciones
```

---

## 8.4 `app/Controllers/Api/AuthController.php`

Controlador API de autenticación.

Incluye dos métodos principales:

```php
public function login(Request $request, Hasher $hasher): Response
```

Sirve para login JWT.

Recibe:

```json
{
  "email": "admin@test.com",
  "password": "secret"
}
```

Responde:

```json
{
  "ok": true,
  "message": "Autenticación correcta.",
  "token_type": "Bearer",
  "access_token": "jwt...",
  "expires_in": 3600,
  "user": {}
}
```

También incluye:

```php
public function me(): Response
```

Sirve para revisar qué usuario está autenticado.

---

## 8.5 `app/Models/ApiToken.php`

Modelo para tokens persistentes.

Funciones importantes:

```php
ApiToken::issue($user, 'Postman', ['projects:read']);
```

Crea un token nuevo.

```php
ApiToken::findByPlainTextToken($plainTextToken);
```

Busca un token por su versión plana, hasheándolo internamente.

```php
$token->can('projects:read');
```

Valida permisos.

```php
$token->revoke();
```

Revoca el token.

```php
$token->markAsUsed($request);
```

Actualiza `last_used_at`, IP y User-Agent.

---

## 8.6 `app/Middlewares/ApiAuthMiddleware.php`

Middleware principal de autenticación API.

Acepta dos guards:

```txt
token
jwt
```

Por defecto intenta ambos:

```php
new ApiAuthMiddleware(['token', 'jwt'])
```

Si el Bearer parece JWT porque tiene dos puntos (`.`), prioriza JWT.

Si no, prioriza token persistente.

Uso:

```php
Route::group('/api', function () {
    Route::get('/me', [AuthController::class, 'me']);
}, [ApiAuthMiddleware::class]);
```

---

## 8.7 `app/Middlewares/ApiTokenMiddleware.php`

Versión específica para aceptar solo tokens persistentes.

Internamente hace:

```php
parent::__construct(['token']);
```

Uso:

```php
Route::get('/api/integracion', [IntegrationController::class, 'index'])
    ->middleware(ApiTokenMiddleware::class);
```

---

## 8.8 `app/Middlewares/JwtMiddleware.php`

Versión específica para aceptar solo JWT.

Internamente hace:

```php
parent::__construct(['jwt']);
```

Uso:

```php
Route::get('/api/app/profile', [ProfileController::class, 'show'])
    ->middleware(JwtMiddleware::class);
```

---

## 8.9 `app/Middlewares/ApiAbilityMiddleware.php`

Middleware de permisos.

Sirve para exigir una ability específica.

Ejemplo:

```php
Route::post('/projects', [ProjectApiController::class, 'store'])
    ->middleware(new ApiAbilityMiddleware('projects:write'));
```

Si el token no tiene ese permiso, responde:

```json
{
  "ok": false,
  "message": "No tienes permiso para realizar esta acción.",
  "errors": {
    "ability": "Se requiere el permiso [projects:write]."
  }
}
```

con status `403`.

---

## 8.10 `app/Middlewares/ApiCorsMiddleware.php`

Agrega headers CORS.

Headers principales:

```http
Access-Control-Allow-Origin: *
Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS
Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With
```

Uso recomendado:

```php
Route::group('/api', function () {
    // rutas API
}, [ApiCorsMiddleware::class]);
```

---

## 8.11 `src/Auth/Auth.php`

Versión extendida de Auth.

Antes:

```php
Auth::user()
```

solo podía resolver usuario de sesión.

Ahora:

```php
Auth::user()
```

primero revisa si hay usuario API y después usuario de sesión.

Métodos importantes:

```php
Auth::user();
Auth::sessionUser();
Auth::apiUser();
Auth::apiContext();
Auth::apiToken();
Auth::jwtPayload();
Auth::isApi();
Auth::tokenCan('projects:read');
Auth::tokenCant('projects:write');
```

Esto permite usar helpers como:

```php
auth();
api_token();
jwt_payload();
api_token_can('projects:read');
```

---

## 8.12 `src/Auth/Api/ApiAuthContext.php`

Representa el resultado de una autenticación API.

Puede contener:

- Usuario autenticado.
- Token persistente activo.
- Payload JWT activo.
- Driver usado: `token` o `jwt`.
- Error de autenticación.
- Status HTTP.

Ejemplo conceptual:

```php
ApiAuthContext::token($user, $token);
ApiAuthContext::jwt($user, $payload);
ApiAuthContext::deny('Token inválido.');
```

También valida permisos:

```php
$context->can('projects:read');
$context->cant('projects:write');
```

---

## 8.13 `src/Auth/Api/ApiTokenGuard.php`

Guard encargado de autenticar tokens persistentes.

Flujo:

```txt
Lee Authorization: Bearer
    ↓
Obtiene token plano
    ↓
Calcula SHA-256
    ↓
Busca api_tokens.token_hash
    ↓
Valida que no esté revocado
    ↓
Valida que no esté expirado
    ↓
Busca dueño tokenable
    ↓
Marca último uso
    ↓
Regresa ApiAuthContext autenticado
```

---

## 8.14 `src/Auth/Api/Jwt.php`

Clase para emitir y validar JWT.

Emitir token:

```php
$jwt = Jwt::issue($user->id(), [
    'model' => $user::class,
    'abilities' => ['projects:read'],
], 3600);
```

Decodificar:

```php
$payload = Jwt::decode($jwt);
```

Valida:

- Formato de 3 segmentos.
- Header JSON.
- Payload JSON.
- Algoritmo `HS256`.
- Firma.
- `nbf`.
- `iat`.
- `exp`.
- `iss`.
- `aud`, si está configurado.

---

## 8.15 `src/Auth/Api/JwtGuard.php`

Guard encargado de autenticar JWT.

Flujo:

```txt
Lee Authorization: Bearer
    ↓
Confirma que parezca JWT
    ↓
Jwt::decode(...)
    ↓
Obtiene sub
    ↓
Obtiene model
    ↓
Busca usuario
    ↓
Regresa ApiAuthContext autenticado
```

El `sub` del JWT es el ID del usuario.

---

## 8.16 `src/Helpers/api_auth.php`

Helpers de comodidad.

Incluye:

```php
api_context();
api_token();
jwt_payload();
api_token_can('ability');
api_token_cant('ability');
```

Ejemplo:

```php
if (api_token_can('projects:write')) {
    // permitir acción
}
```

---

## 8.17 `src/Http/Request_api_methods.patch.php`

Agrega métodos útiles para API a `Request`.

Métodos:

```php
$request->input();
$request->input('name');
$request->json();
$request->json('email');
$request->header('Authorization');
$request->authorizationHeader();
$request->bearerToken();
$request->ip();
$request->userAgent();
$request->isApi();
```

Estos métodos hacen más fácil trabajar con JSON y Bearer Tokens.

---

## 8.18 `src/Http/Response_api_methods.patch.php`

Agrega respuestas JSON estandarizadas.

Métodos:

```php
Response::api($data, $message, $status, $meta);
Response::apiError($message, $status, $errors, $code);
Response::unauthorized();
Response::forbidden();
```

Ejemplo:

```php
return Response::api([
    'projects' => $projects,
], 'Proyectos obtenidos correctamente.');
```

Error:

```php
return Response::apiError(
    'Datos inválidos.',
    422,
    ['name' => 'El nombre es obligatorio.'],
    'VALIDATION_ERROR'
);
```

---

## 8.19 `src/Auth/Authenticatable_api_methods.patch.php`

Agrega a cualquier usuario autenticable la capacidad de crear tokens:

```php
$token = auth()->createApiToken('Postman', ['projects:read']);
```

Este método debe ir dentro de:

```txt
Whis\Auth\Authenticatable
```

---

## 8.20 `src/Database/Model_toArray_fix.patch.php`

Corrige `Model::toArray()` para respetar `$hidden` por nombre de campo.

Versión correcta:

```php
public function toArray(): array
{
    return array_filter(
        $this->attributes,
        fn ($value, $key) => ! in_array($key, $this->hidden, true),
        ARRAY_FILTER_USE_BOTH
    );
}
```

Esto es importante porque `ApiToken` oculta:

```php
protected array $hidden = [
    'token_hash',
];
```

Sin este fix, podrías exponer datos que deberían estar ocultos.

---

## 8.21 `src/Http/HttpMethod_patch.php`

Agrega soporte conceptual para `OPTIONS`.

Es útil para CORS preflight.

Tu enum actual debería quedar así:

```php
enum HttpMethod: string
{
    case GET = 'GET';
    case POST = 'POST';
    case PUT = 'PUT';
    case PATCH = 'PATCH';
    case DELETE = 'DELETE';
    case OPTIONS = 'OPTIONS';
}
```

Después debes agregar métodos equivalentes en:

- `Route::options()`
- `Router::options()`
- `ControllerRouteGroup::options()`
- `GroupRouteRegistrar`

Si todavía no quieres soporte completo de `OPTIONS`, puedes dejar CORS básico, pero navegadores modernos pueden necesitar preflight para peticiones con `Authorization`.

---

# 9. Cómo crear rutas API

## 9.1 Ruta pública simple

```php
use Whis\Http\Response;
use Whis\Routing\Route;

Route::get('/api/health', function () {
    return Response::json([
        'ok' => true,
        'message' => 'API funcionando.',
    ]);
});
```

Respuesta:

```json
{
  "ok": true,
  "message": "API funcionando."
}
```

---

## 9.2 Grupo `/api`

Recomendado:

```php
use App\Middlewares\ApiCorsMiddleware;
use Whis\Routing\Route;

Route::group('/api', function () {
    Route::get('/health', function () {
        return Response::json([
            'ok' => true,
            'message' => 'Whis API funcionando.',
        ]);
    });
}, [ApiCorsMiddleware::class]);
```

La URL final será:

```txt
GET /api/health
```

---

## 9.3 Ruta protegida

```php
use App\Middlewares\ApiAuthMiddleware;

Route::group('/api', function () {
    Route::group('', function () {
        Route::get('/me', [AuthController::class, 'me']);
    }, [ApiAuthMiddleware::class]);
});
```

La ruta `/api/me` necesita:

```http
Authorization: Bearer <token>
```

---

## 9.4 Ruta protegida con ability

```php
use App\Middlewares\ApiAbilityMiddleware;
use App\Middlewares\ApiAuthMiddleware;

Route::group('/api', function () {
    Route::group('', function () {
        Route::get('/projects', [ProjectApiController::class, 'index'])
            ->middleware(new ApiAbilityMiddleware('projects:read'));
    }, [ApiAuthMiddleware::class]);
});
```

---

## 9.5 Rutas con solo token persistente

```php
use App\Middlewares\ApiTokenMiddleware;

Route::get('/api/integration/sync', [IntegrationController::class, 'sync'])
    ->middleware(ApiTokenMiddleware::class);
```

Solo acepta tokens tipo:

```txt
whis_xxxxxxxxx
```

---

## 9.6 Rutas con solo JWT

```php
use App\Middlewares\JwtMiddleware;

Route::get('/api/app/profile', [ProfileController::class, 'show'])
    ->middleware(JwtMiddleware::class);
```

Solo acepta JWT.

---

## 9.7 Grupo completo recomendado

```php
<?php

use App\Controllers\Api\AuthController;
use App\Controllers\Api\ProjectApiController;
use App\Middlewares\ApiAbilityMiddleware;
use App\Middlewares\ApiAuthMiddleware;
use App\Middlewares\ApiCorsMiddleware;
use Whis\Http\Response;
use Whis\Routing\Route;

Route::group('/api', function () {
    Route::get('/health', function () {
        return Response::json([
            'ok' => true,
            'message' => 'API funcionando.',
        ]);
    });

    Route::post('/auth/login', [AuthController::class, 'login']);

    Route::group('', function () {
        Route::get('/me', [AuthController::class, 'me']);

        Route::get('/projects', [ProjectApiController::class, 'index'])
            ->middleware(new ApiAbilityMiddleware('projects:read'));

        Route::post('/projects', [ProjectApiController::class, 'store'])
            ->middleware(new ApiAbilityMiddleware('projects:write'));
    }, [ApiAuthMiddleware::class]);
}, [ApiCorsMiddleware::class]);
```

---

# 10. Cómo crear controladores API

Un controlador API debe regresar `Response::json()` o `Response::api()`.

Ejemplo:

```php
<?php

namespace App\Controllers\Api;

use App\Models\Project;
use Whis\Http\Controller;
use Whis\Http\Request;
use Whis\Http\Response;

class ProjectApiController extends Controller
{
    public function index(): Response
    {
        return Response::json([
            'ok' => true,
            'message' => 'Proyectos obtenidos correctamente.',
            'data' => Project::all('id', true),
        ]);
    }

    public function show(string|int $id): Response
    {
        $project = Project::find($id);

        if ($project === null) {
            return Response::json([
                'ok' => false,
                'message' => 'Proyecto no encontrado.',
                'errors' => [],
            ])->setStatus(404);
        }

        return Response::json([
            'ok' => true,
            'message' => 'Proyecto encontrado.',
            'data' => $project->toArray(),
        ]);
    }

    public function store(Request $request): Response
    {
        $data = array_merge($request->json(), $request->data());

        if (empty($data['title'])) {
            return Response::json([
                'ok' => false,
                'message' => 'Datos inválidos.',
                'errors' => [
                    'title' => 'El título es obligatorio.',
                ],
            ])->setStatus(422);
        }

        $project = Project::create([
            'title' => $data['title'],
            'slug' => $data['slug'] ?? null,
            'status' => $data['status'] ?? 'draft',
        ]);

        return Response::json([
            'ok' => true,
            'message' => 'Proyecto creado correctamente.',
            'data' => $project->toArray(),
        ])->setStatus(201);
    }
}
```

## 10.1 Recomendación de estilo

Usa respuestas consistentes:

```json
{
  "ok": true,
  "message": "Mensaje legible.",
  "data": {}
}
```

Para errores:

```json
{
  "ok": false,
  "message": "Datos inválidos.",
  "errors": {
    "title": "El título es obligatorio."
  }
}
```

---

# 11. Cómo proteger una API con Bearer Token o JWT

## 11.1 Header requerido

Toda ruta protegida espera:

```http
Authorization: Bearer <token>
```

Ejemplo token persistente:

```http
Authorization: Bearer whis_f0d7f14f5c8a9c0a7d5f0e3e2b
```

Ejemplo JWT:

```http
Authorization: Bearer eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...
```

## 11.2 Proteger grupo completo

```php
Route::group('/api', function () {
    Route::group('', function () {
        Route::get('/me', [AuthController::class, 'me']);
        Route::get('/projects', [ProjectApiController::class, 'index']);
    }, [ApiAuthMiddleware::class]);
});
```

## 11.3 Proteger ruta individual

```php
Route::get('/api/me', [AuthController::class, 'me'])
    ->middleware(ApiAuthMiddleware::class);
```

## 11.4 Aceptar solo tokens persistentes

```php
Route::get('/api/server-sync', [SyncController::class, 'index'])
    ->middleware(ApiTokenMiddleware::class);
```

## 11.5 Aceptar solo JWT

```php
Route::get('/api/mobile/me', [AuthController::class, 'me'])
    ->middleware(JwtMiddleware::class);
```

---

# 12. Cómo crear tokens persistentes

## 12.1 Crear token desde un usuario autenticado

Una vez agregado el método de `Authenticatable_api_methods.patch.php`, puedes hacer:

```php
$result = auth()->createApiToken(
    'Postman',
    ['projects:read', 'projects:write'],
    '2027-01-01 00:00:00'
);

$plainTextToken = $result['plain_text_token'];
$tokenModel = $result['token'];
```

El token completo solo aparece en:

```php
$result['plain_text_token']
```

Guárdalo o muéstralo al usuario una sola vez.

## 12.2 Crear token para otro usuario

```php
use App\Models\User;

$user = User::find(1);

$result = $user->createApiToken('Integración CRM', [
    'projects:read',
    'messages:read',
]);
```

## 12.3 Crear token sin expiración

```php
$result = $user->createApiToken('Token permanente', ['*']);
```

El campo `expires_at` será `NULL`.

## 12.4 Crear token con expiración

```php
$result = $user->createApiToken(
    'Token temporal',
    ['projects:read'],
    date('Y-m-d H:i:s', strtotime('+30 days'))
);
```

## 12.5 Crear token directamente con el modelo

```php
use App\Models\ApiToken;

$result = ApiToken::issue(
    $user,
    'Postman',
    ['projects:read'],
    '2027-01-01 00:00:00'
);
```

## 12.6 Revocar token

```php
$token = ApiToken::find($id);

if ($token !== null) {
    $token->revoke();
}
```

Revocar no borra el registro. Solo llena:

```txt
revoked_at
```

Así puedes auditar que existió.

## 12.7 Usar token persistente en una petición

```bash
curl http://localhost/api/me \
  -H "Authorization: Bearer whis_xxxxxxxxxxxxxxxxx"
```

---

# 13. Cómo usar JWT

## 13.1 Login JWT

Endpoint base:

```txt
POST /api/auth/login
```

Body:

```json
{
  "email": "admin@test.com",
  "password": "secret"
}
```

curl:

```bash
curl -X POST http://localhost/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"admin@test.com","password":"secret"}'
```

Respuesta:

```json
{
  "ok": true,
  "message": "Autenticación correcta.",
  "token_type": "Bearer",
  "access_token": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...",
  "expires_in": 3600,
  "user": {}
}
```

## 13.2 Usar JWT

```bash
curl http://localhost/api/me \
  -H "Authorization: Bearer <access_token>"
```

## 13.3 Emitir JWT manualmente

```php
use Whis\Auth\Api\Jwt;

$jwt = Jwt::issue($user->id(), [
    'model' => $user::class,
    'abilities' => ['projects:read'],
], 3600);
```

## 13.4 Leer payload JWT actual

Dentro de una ruta protegida:

```php
$payload = jwt_payload();
```

Ejemplo:

```php
return Response::json([
    'ok' => true,
    'payload' => jwt_payload(),
]);
```

## 13.5 JWT con abilities

El login permite enviar abilities opcionales:

```json
{
  "email": "admin@test.com",
  "password": "secret",
  "abilities": ["projects:read", "projects:write"]
}
```

También puedes mandarlas como string:

```json
{
  "email": "admin@test.com",
  "password": "secret",
  "abilities": "projects:read,projects:write"
}
```

---

# 14. Abilities / permisos de API

Las abilities son permisos simples en texto.

Ejemplos:

```txt
*
projects:read
projects:write
projects:delete
messages:read
messages:reply
users:read
users:write
```

## 14.1 Ability `*`

El permiso `*` significa acceso total.

```php
$result = $user->createApiToken('Admin Token', ['*']);
```

## 14.2 Verificar ability en ruta

```php
Route::get('/api/projects', [ProjectApiController::class, 'index'])
    ->middleware(new ApiAbilityMiddleware('projects:read'));
```

## 14.3 Verificar ability dentro del controlador

```php
if (api_token_cant('projects:write')) {
    return Response::json([
        'ok' => false,
        'message' => 'No tienes permiso para editar proyectos.',
        'errors' => [],
    ])->setStatus(403);
}
```

## 14.4 Convención recomendada

Usa este patrón:

```txt
recurso:acción
```

Ejemplos:

```txt
projects:read
projects:create
projects:update
projects:delete
messages:read
messages:delete
clients:read
clients:write
```

Evita nombres vagos como:

```txt
admin
access
edit
```

Mejor:

```txt
admin:access
projects:update
```

---

# 15. Formato recomendado de respuestas JSON

## 15.1 Respuesta exitosa simple

```php
return Response::json([
    'ok' => true,
    'message' => 'Operación realizada correctamente.',
]);
```

## 15.2 Respuesta exitosa con datos

```php
return Response::json([
    'ok' => true,
    'message' => 'Proyectos obtenidos correctamente.',
    'data' => $projects,
]);
```

## 15.3 Respuesta de creación

```php
return Response::json([
    'ok' => true,
    'message' => 'Registro creado correctamente.',
    'data' => $record->toArray(),
])->setStatus(201);
```

## 15.4 Error de validación

```php
return Response::json([
    'ok' => false,
    'message' => 'Datos inválidos.',
    'errors' => [
        'email' => 'El email es obligatorio.',
    ],
])->setStatus(422);
```

## 15.5 No autorizado

```php
return Response::json([
    'ok' => false,
    'message' => 'No autorizado.',
    'errors' => [],
])->setStatus(401)
  ->setHeader('WWW-Authenticate', 'Bearer');
```

## 15.6 Prohibido

```php
return Response::json([
    'ok' => false,
    'message' => 'No tienes permiso para realizar esta acción.',
    'errors' => [],
])->setStatus(403);
```

## 15.7 No encontrado

```php
return Response::json([
    'ok' => false,
    'message' => 'Recurso no encontrado.',
    'errors' => [],
])->setStatus(404);
```

## 15.8 Status codes recomendados

| Status | Uso |
|---|---|
| `200` | Consulta o actualización correcta. |
| `201` | Recurso creado. |
| `204` | Sin contenido. Útil para delete sin body. |
| `400` | Petición mal formada. |
| `401` | No autenticado. Falta token o es inválido. |
| `403` | Autenticado pero sin permiso. |
| `404` | Recurso no encontrado. |
| `409` | Conflicto, duplicado o estado incompatible. |
| `422` | Validación fallida. |
| `500` | Error interno. |

---

# 16. CSRF vs Bearer Token

## 16.1 Rutas web

Las rutas web usan:

- Sesión.
- Cookies.
- Formularios.
- CSRF.

Ejemplo:

```php
Route::post('/login', [LoginController::class, 'store'])
    ->setMiddlewares([CsrfSaverMiddleware::class]);
```

## 16.2 Rutas API

Las rutas API usan:

```http
Authorization: Bearer <token>
```

No necesitan CSRF porque no dependen de cookies de sesión para autenticar acciones externas.

## 16.3 Regla práctica

```txt
Formulario web con sesión → CSRF
API con Authorization Bearer → no CSRF
Admin web para crear tokens → sesión + CSRF
Consumo externo de API → Bearer Token o JWT
```

No mezcles el panel admin de creación de tokens dentro de `/api`. Ese panel debe quedarse en `/admin` protegido por sesión, `AuthMiddleware`, `AdminMiddleware` si aplica, y CSRF.

---

# 17. CORS y preflight `OPTIONS`

Cuando una app frontend externa consume tu API con `Authorization`, el navegador puede hacer una petición previa:

```http
OPTIONS /api/projects
```

A eso se le llama preflight.

## 17.1 Middleware CORS

El middleware incluido agrega:

```http
Access-Control-Allow-Origin
Access-Control-Allow-Methods
Access-Control-Allow-Headers
Access-Control-Allow-Credentials
Vary
```

## 17.2 Agregar `OPTIONS` al core

Si quieres soporte completo, agrega `OPTIONS` al enum:

```php
enum HttpMethod: string
{
    case GET = 'GET';
    case POST = 'POST';
    case PUT = 'PUT';
    case PATCH = 'PATCH';
    case DELETE = 'DELETE';
    case OPTIONS = 'OPTIONS';
}
```

Después agrega en `Route`:

```php
public static function options(string | array $uri, Closure | array | null $action = null): Route | array
{
    return app()->router->options($uri, $action);
}
```

Agrega en `Router`:

```php
public function options(string | array $uri, Closure | array | null $action = null): Route | array
{
    if (is_array($uri)) {
        return $this->registerMany(HttpMethod::OPTIONS, $uri);
    }

    if ($action === null) {
        throw new \InvalidArgumentException('OPTIONS route action is required.');
    }

    return $this->registerRoute(HttpMethod::OPTIONS, $uri, $action);
}
```

Entonces puedes crear una ruta general:

```php
Route::options('/api/{any:.*}', function () {
    return Response::text('')->setStatus(204);
})->middleware(ApiCorsMiddleware::class);
```

---

# 18. Ejemplo completo: API de proyectos

## 18.1 Controlador

```php
<?php

namespace App\Controllers\Api;

use App\Models\Project;
use Whis\Http\Controller;
use Whis\Http\Request;
use Whis\Http\Response;

class ProjectApiController extends Controller
{
    public function index(Request $request): Response
    {
        $projects = Project::all('id', true);

        return Response::json([
            'ok' => true,
            'message' => 'Proyectos obtenidos correctamente.',
            'data' => $projects,
        ]);
    }

    public function show(string|int $id): Response
    {
        $project = Project::find($id);

        if ($project === null) {
            return Response::json([
                'ok' => false,
                'message' => 'Proyecto no encontrado.',
                'errors' => [],
            ])->setStatus(404);
        }

        return Response::json([
            'ok' => true,
            'message' => 'Proyecto encontrado.',
            'data' => $project->toArray(),
        ]);
    }

    public function store(Request $request): Response
    {
        $data = array_merge($request->json(), $request->data());

        $errors = [];

        if (empty($data['title'])) {
            $errors['title'] = 'El título es obligatorio.';
        }

        if (empty($data['slug'])) {
            $errors['slug'] = 'El slug es obligatorio.';
        }

        if (! empty($errors)) {
            return Response::json([
                'ok' => false,
                'message' => 'Datos inválidos.',
                'errors' => $errors,
            ])->setStatus(422);
        }

        $project = Project::create([
            'title' => $data['title'],
            'slug' => $data['slug'],
            'status' => $data['status'] ?? 'draft',
            'short_description' => $data['short_description'] ?? null,
        ]);

        return Response::json([
            'ok' => true,
            'message' => 'Proyecto creado correctamente.',
            'data' => $project->toArray(),
        ])->setStatus(201);
    }

    public function update(Request $request, string|int $id): Response
    {
        $project = Project::find($id);

        if ($project === null) {
            return Response::json([
                'ok' => false,
                'message' => 'Proyecto no encontrado.',
                'errors' => [],
            ])->setStatus(404);
        }

        $data = array_merge($request->json(), $request->data());

        foreach (['title', 'slug', 'status', 'short_description'] as $field) {
            if (array_key_exists($field, $data)) {
                $project->{$field} = $data[$field];
            }
        }

        $project->update('id', $id);

        return Response::json([
            'ok' => true,
            'message' => 'Proyecto actualizado correctamente.',
            'data' => $project->toArray(),
        ]);
    }

    public function destroy(string|int $id): Response
    {
        $project = Project::find($id);

        if ($project === null) {
            return Response::json([
                'ok' => false,
                'message' => 'Proyecto no encontrado.',
                'errors' => [],
            ])->setStatus(404);
        }

        $project->delete();

        return Response::json([
            'ok' => true,
            'message' => 'Proyecto eliminado correctamente.',
        ]);
    }
}
```

## 18.2 Rutas

```php
<?php

use App\Controllers\Api\ProjectApiController;
use App\Middlewares\ApiAbilityMiddleware;
use App\Middlewares\ApiAuthMiddleware;
use App\Middlewares\ApiCorsMiddleware;
use Whis\Routing\Route;

Route::group('/api', function () {
    Route::group('', function () {
        Route::get('/projects', [ProjectApiController::class, 'index'])
            ->middleware(new ApiAbilityMiddleware('projects:read'));

        Route::get('/projects/{id:\\d+}', [ProjectApiController::class, 'show'])
            ->middleware(new ApiAbilityMiddleware('projects:read'));

        Route::post('/projects', [ProjectApiController::class, 'store'])
            ->middleware(new ApiAbilityMiddleware('projects:create'));

        Route::put('/projects/{id:\\d+}', [ProjectApiController::class, 'update'])
            ->middleware(new ApiAbilityMiddleware('projects:update'));

        Route::delete('/projects/{id:\\d+}', [ProjectApiController::class, 'destroy'])
            ->middleware(new ApiAbilityMiddleware('projects:delete'));
    }, [ApiAuthMiddleware::class]);
}, [ApiCorsMiddleware::class]);
```

## 18.3 Crear token para probar esta API

```php
$result = auth()->createApiToken('Postman Projects', [
    'projects:read',
    'projects:create',
    'projects:update',
    'projects:delete',
]);

$token = $result['plain_text_token'];
```

## 18.4 Probar listado

```bash
curl http://localhost/api/projects \
  -H "Authorization: Bearer whis_xxxxxxxxx"
```

## 18.5 Crear proyecto

```bash
curl -X POST http://localhost/api/projects \
  -H "Authorization: Bearer whis_xxxxxxxxx" \
  -H "Content-Type: application/json" \
  -d '{
    "title": "Proyecto API",
    "slug": "proyecto-api",
    "status": "published",
    "short_description": "Proyecto creado desde API."
  }'
```

---

# 19. Consumir la API desde curl, Postman y JavaScript

## 19.1 curl con token persistente

```bash
curl http://localhost/api/me \
  -H "Authorization: Bearer whis_xxxxxxxxx"
```

## 19.2 curl con JWT

```bash
curl http://localhost/api/me \
  -H "Authorization: Bearer eyJhbGciOiJIUzI1NiIs..."
```

## 19.3 Postman

En Postman:

1. Ve a la pestaña **Authorization**.
2. Type: **Bearer Token**.
3. Pega el token sin escribir `Bearer` manualmente.
4. Envía la petición.

O en Headers:

```http
Authorization: Bearer whis_xxxxxxxxx
Content-Type: application/json
Accept: application/json
```

## 19.4 JavaScript fetch con JWT

```js
const response = await fetch('/api/me', {
  method: 'GET',
  headers: {
    'Accept': 'application/json',
    'Authorization': `Bearer ${token}`,
  },
});

const data = await response.json();
console.log(data);
```

## 19.5 JavaScript fetch con POST JSON

```js
const response = await fetch('/api/projects', {
  method: 'POST',
  headers: {
    'Accept': 'application/json',
    'Content-Type': 'application/json',
    'Authorization': `Bearer ${token}`,
  },
  body: JSON.stringify({
    title: 'Proyecto API',
    slug: 'proyecto-api',
    status: 'published',
  }),
});

const data = await response.json();
```

## 19.6 Login JWT desde JavaScript

```js
const response = await fetch('/api/auth/login', {
  method: 'POST',
  headers: {
    'Accept': 'application/json',
    'Content-Type': 'application/json',
  },
  body: JSON.stringify({
    email: 'admin@test.com',
    password: 'secret',
  }),
});

const data = await response.json();

if (data.ok) {
  localStorage.setItem('access_token', data.access_token);
}
```

Para producción, evalúa cuidadosamente dónde guardar el token. `localStorage` es fácil, pero sensible a XSS. En apps más estrictas, conviene usar almacenamiento más controlado.

---

# 20. Gestión administrativa de tokens

La API Core permite crear tokens por código:

```php
$result = auth()->createApiToken('Postman', ['projects:read']);
```

Pero para un framework sólido, normalmente querrás una interfaz web en `/admin` o `/account`.

## 20.1 Dónde debe vivir la gestión de tokens

Debe vivir en rutas web, no en `/api`.

Ejemplo recomendado:

```txt
/admin/api-tokens
/account/api-tokens
```

Estas rutas deben usar:

- Sesión.
- CSRF.
- `AuthMiddleware`.
- `AdminMiddleware`, si aplica.
- `ajax-form.js`, si estás siguiendo el patrón de formularios admin de Whis.

No uses Bearer Token para la pantalla que crea Bearer Tokens.

## 20.2 Qué debe hacer un CRUD administrativo de tokens

Un controlador administrativo debería permitir:

1. Listar tokens del usuario.
2. Crear token.
3. Mostrar el token plano solo una vez.
4. Revocar token.
5. Ver `last_used_at`, IP y User-Agent.
6. Ver abilities.
7. Ver expiración.

## 20.3 Nunca mostrar `token_hash`

El modelo `ApiToken` tiene:

```php
protected array $hidden = [
    'token_hash',
];
```

Además debes tener corregido `Model::toArray()` para respetar `$hidden`.

## 20.4 Ejemplo de creación desde controlador admin

```php
public function store(Request $request): Response
{
    $data = $request->validate([
        'name' => 'required',
        'abilities' => 'required',
    ]);

    $abilities = array_filter(array_map(
        'trim',
        explode(',', $data['abilities'])
    ));

    $result = auth()->createApiToken(
        $data['name'],
        $abilities,
        $request->data('expires_at') ?: null
    );

    session()->flash('plain_text_token', $result['plain_text_token']);

    return Response::json([
        'ok' => true,
        'message' => 'Token creado correctamente. Copia el token ahora; no volverá a mostrarse.',
        'redirect' => '/admin/api-tokens',
    ]);
}
```

---

# 21. Seguridad recomendada

## 21.1 Usa HTTPS en producción

Nunca uses tokens Bearer en HTTP plano en producción.

## 21.2 No guardes tokens planos

Solo guarda `token_hash`.

Correcto:

```php
hash('sha256', $plainTextToken)
```

Incorrecto:

```php
'token' => $plainTextToken
```

## 21.3 Muestra tokens una sola vez

Cuando crees un token persistente, muestra el token completo solo una vez.

Después solo muestra algo como:

```txt
whis_f0d7f14f5c8a9c...
```

## 21.4 Usa expiración cuando tenga sentido

Para integraciones temporales:

```php
$user->createApiToken('Temporal', ['projects:read'], '2026-12-31 23:59:59');
```

## 21.5 Usa abilities mínimas

Evita dar `*` a todo.

Mejor:

```php
['projects:read']
```

que:

```php
['*']
```

## 21.6 Revoca tokens comprometidos

```php
$token->revoke();
```

## 21.7 Protege rutas administrativas

La creación de tokens debe estar protegida por sesión y CSRF.

## 21.8 Cuida JWT_SECRET

`JWT_SECRET` debe ser largo, privado y diferente entre ambientes.

No lo subas a Git.

## 21.9 Controla CORS en producción

En desarrollo puedes usar:

```env
API_CORS_ORIGIN=*
```

En producción es mejor:

```env
API_CORS_ORIGIN=https://tudominio.com
```

## 21.10 Registra último uso

`ApiToken::markAsUsed()` guarda:

- `last_used_at`
- `last_used_ip`
- `last_used_user_agent`

Esto ayuda a auditar uso sospechoso.

---

# 22. Errores comunes y solución

## 22.1 `Token ausente.`

Causa: la petición no mandó header `Authorization`.

Solución:

```http
Authorization: Bearer <token>
```

En Apache puede que necesites reenviar el header.

En `.htaccess`:

```apache
RewriteEngine On
RewriteCond %{HTTP:Authorization} .
RewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]
```

---

## 22.2 `Token inválido, expirado o revocado.`

Causas posibles:

- Token mal copiado.
- Token revocado.
- Token expirado.
- Estás usando JWT en ruta que solo acepta token persistente.
- Estás usando token persistente en ruta que solo acepta JWT.

Solución:

- Revisa si la ruta usa `ApiAuthMiddleware`, `ApiTokenMiddleware` o `JwtMiddleware`.
- Genera un token nuevo.
- Revisa `expires_at` y `revoked_at`.

---

## 22.3 `JWT_SECRET o APP_KEY no está configurado.`

Causa: no tienes clave para firmar JWT.

Solución en `.env`:

```env
JWT_SECRET=clave-larga-segura
```

---

## 22.4 `Firma JWT inválida.`

Causas:

- Cambiaste `JWT_SECRET` después de emitir el token.
- El token fue alterado.
- El token pertenece a otro ambiente.

Solución:

- Inicia sesión otra vez.
- Revisa que el servidor use la misma clave.

---

## 22.5 `JWT expirado.`

Causa: el token superó `api.jwt.ttl`.

Solución:

- Iniciar sesión otra vez.
- Aumentar `ttl` si tu caso lo requiere.

---

## 22.6 `No tienes permiso para realizar esta acción.`

Causa: el token no tiene la ability requerida.

Ejemplo de ruta:

```php
->middleware(new ApiAbilityMiddleware('projects:write'))
```

El token necesita:

```txt
projects:write
```

O:

```txt
*
```

---

## 22.7 El navegador bloquea la petición por CORS

Causa: origen no permitido o falta respuesta a `OPTIONS`.

Solución:

- Usa `ApiCorsMiddleware` en el grupo `/api`.
- Configura `API_CORS_ORIGIN`.
- Agrega soporte `OPTIONS` si el navegador hace preflight.

---

## 22.8 `$request->json()` devuelve vacío

Causas:

- No estás mandando `Content-Type: application/json`.
- El body no es JSON válido.
- Estás enviando form-data pero leyendo JSON.

Solución:

```http
Content-Type: application/json
```

Body válido:

```json
{
  "name": "Test"
}
```

---

## 22.9 `token_hash` aparece en JSON

Causa: `Model::toArray()` no está respetando `$hidden` por llave.

Solución: reemplaza `toArray()` por el patch:

```php
public function toArray(): array
{
    return array_filter(
        $this->attributes,
        fn ($value, $key) => ! in_array($key, $this->hidden, true),
        ARRAY_FILTER_USE_BOTH
    );
}
```

---

# 23. Checklist de implementación

Usa esta lista para confirmar que todo quedó integrado.

## 23.1 Core

- [ ] Copié `src/Auth/Api/ApiAuthContext.php`.
- [ ] Copié `src/Auth/Api/ApiTokenGuard.php`.
- [ ] Copié `src/Auth/Api/Jwt.php`.
- [ ] Copié `src/Auth/Api/JwtGuard.php`.
- [ ] Fusioné o reemplacé `src/Auth/Auth.php`.
- [ ] Agregué métodos API a `Request.php`.
- [ ] Agregué métodos API a `Response.php`.
- [ ] Agregué `createApiToken()` a `Authenticatable.php`.
- [ ] Corregí `Model::toArray()`.
- [ ] Agregué helpers `api_auth.php`.

## 23.2 App

- [ ] Copié `app/Models/ApiToken.php`.
- [ ] Copié `app/Controllers/Api/AuthController.php`.
- [ ] Copié `ApiAuthMiddleware`.
- [ ] Copié `ApiTokenMiddleware`.
- [ ] Copié `JwtMiddleware`.
- [ ] Copié `ApiAbilityMiddleware`.
- [ ] Copié `ApiCorsMiddleware`.

## 23.3 Configuración

- [ ] Agregué `config/api.php`.
- [ ] Configuré `JWT_SECRET`.
- [ ] Configuré `API_CORS_ORIGIN`.
- [ ] Ejecuté SQL de `api_tokens`.
- [ ] Cargué `routes/api.php`.

## 23.4 Pruebas

- [ ] `GET /api/health` responde.
- [ ] `POST /api/auth/login` devuelve JWT.
- [ ] `GET /api/me` funciona con JWT.
- [ ] Creé un token persistente.
- [ ] `GET /api/me` funciona con token persistente.
- [ ] Una ruta con `ApiAbilityMiddleware` bloquea tokens sin permiso.
- [ ] Una ruta con `ApiAbilityMiddleware` permite tokens con permiso.
- [ ] CORS funciona desde frontend externo si aplica.

---

# 24. Resumen rápido para usuarios nuevos

## 24.1 Crear una API pública

```php
Route::get('/api/health', function () {
    return Response::json([
        'ok' => true,
        'message' => 'API funcionando.',
    ]);
});
```

## 24.2 Crear login JWT

Ya viene incluido:

```txt
POST /api/auth/login
```

Body:

```json
{
  "email": "admin@test.com",
  "password": "secret"
}
```

## 24.3 Proteger una ruta

```php
Route::get('/api/me', [AuthController::class, 'me'])
    ->middleware(ApiAuthMiddleware::class);
```

## 24.4 Crear token persistente

```php
$result = auth()->createApiToken('Postman', ['projects:read']);

echo $result['plain_text_token'];
```

## 24.5 Consumir API

```bash
curl http://localhost/api/me \
  -H "Authorization: Bearer whis_xxxxxxxxx"
```

## 24.6 Agregar permiso a ruta

```php
Route::get('/api/projects', [ProjectApiController::class, 'index'])
    ->middleware([
        ApiAuthMiddleware::class,
        new ApiAbilityMiddleware('projects:read'),
    ]);
```

## 24.7 Leer usuario actual

```php
$user = auth();
```

## 24.8 Leer token actual

```php
$token = api_token();
```

## 24.9 Leer payload JWT

```php
$payload = jwt_payload();
```

---

# Cierre

Whis API Core mantiene la filosofía de Whis: simple de implementar, entendible por el usuario, pero suficientemente sólido para crecer.

La base recomendada es:

```txt
Web tradicional:
    sesión + CSRF

API externa:
    Authorization: Bearer

Integraciones:
    token persistente en DB

Frontend desacoplado / app móvil:
    JWT

Permisos:
    abilities por ruta o controlador
```

Con esta estructura, Whis puede soportar APIs públicas, privadas, integraciones externas, paneles administrativos, frontends desacoplados y clientes móviles sin romper el funcionamiento actual del framework.
