# Laravel Attribute Routing

[![Tests](https://github.com/ahmed-nour-dev/laravel-attribute-routing/actions/workflows/tests.yml/badge.svg)](https://github.com/ahmed-nour-dev/laravel-attribute-routing/actions/workflows/tests.yml)
[![Latest Version](https://img.shields.io/packagist/v/ahmednour/laravel-attribute-routing.svg)](https://packagist.org/packages/ahmednour/laravel-attribute-routing)
[![License](https://img.shields.io/packagist/l/ahmednour/laravel-attribute-routing.svg)](LICENSE)

Define Laravel routes with PHP 8 attributes, on the controller methods that handle them.

```php
#[Prefix('api/leads')]
#[Middleware(['api', 'auth:sanctum'])]
#[Name('leads.')]
class LeadController
{
    #[Get('', name: 'index')]
    #[WithPermission(PermissionEnum::VIEW_LEADS)]
    public function index(LeadListQuery $query): AnonymousResourceCollection { /* ... */ }

    #[Post('', name: 'store')]
    #[WithPermission(PermissionEnum::CREATE_LEAD)]
    #[Throttle(6)]
    public function store(LeadRequest $request): JsonResponse { /* ... */ }

    #[Delete('{lead}', name: 'destroy', where: ['lead' => '[0-9]+'])]
    #[WithPermission(PermissionEnum::DELETE_LEAD)]
    public function destroy(Lead $lead): JsonResponse { /* ... */ }
}
```

That controller is its own routing documentation. There is no `api.php` entry to keep in sync.

## Why

Past a couple of hundred routes, `routes/api.php` becomes a wall of text, and understanding one
endpoint means holding two files in your head at once — the path and middleware live in one place,
the code that serves them in another.

Colocation is how the rest of the ecosystem already works: React colocates components, Vue has
`<script setup>`, Symfony has shipped attribute routing for years. This package brings the same
idea to Laravel, as an **opt-in** layer — your existing `web.php` and `api.php` keep working
untouched, and you can move one controller at a time.

Three things you get that route files can't give you:

- **Find usages actually works.** "Find all references" on a permission enum case lists every route
  that requires it.
- **Middleware composition reads top to bottom.** Class-level attributes apply to the whole
  controller, method-level attributes stack on top, `#[WithoutMiddleware]` opts a single method out.
- **Nothing costs anything in production.** Discovery is skipped entirely when routes are cached.

## Installation

```bash
composer require ahmednour/laravel-attribute-routing
```

The service provider is auto-discovered. Publish the config if you need to change the scanned paths:

```bash
php artisan vendor:publish --tag=attribute-routing-config
```

Requires PHP 8.2+ and Laravel 12 or 13. (Laravel 11 is not supported: every 11.x
release is now blocked by unpatched security advisories.)

## Usage

Import the attributes and put them on your controller:

```php
use AhmedNour\AttributeRouting\Attributes\{Get, Post, Prefix, Middleware, Name};
```

### HTTP verbs

| Attribute | Registers |
| --- | --- |
| `#[Get('users')]` | `GET`, `HEAD` |
| `#[Post('users')]` | `POST` |
| `#[Put('users/{user}')]` | `PUT` |
| `#[Patch('users/{user}')]` | `PATCH` |
| `#[Delete('users/{user}')]` | `DELETE` |
| `#[Options('users')]` | `OPTIONS` |
| `#[Any('webhook')]` | every verb |
| `#[MatchRoute(['put', 'patch'], 'password')]` | the verbs you list |

Every verb attribute takes the same optional arguments:

```php
#[Get(
    path: 'reports/{report}',
    middleware: ['signed'],           // string or array
    prefix: 'admin',                  // extra prefix for this route only
    name: 'reports.show',
    where: ['report' => '[0-9]+'],
    withoutMiddleware: ['auth:sanctum'],
)]
```

They are **repeatable** — one handler can answer on more than one path:

```php
#[Get('export', name: 'leads.export')]
#[Get('download')]
public function export(): StreamedResponse { /* ... */ }
```

### Class-level composition

| Attribute | Effect |
| --- | --- |
| `#[Prefix('api/leads')]` | URI prefix for every route in the class. Repeatable — segments join in declaration order. |
| `#[Version('v1')]` | Same as `Prefix`, named for intent: `#[Prefix('api')] #[Version('v1')]` → `api/v1/...` |
| `#[Middleware(['api', 'auth:sanctum'])]` | Middleware for every route in the class. |
| `#[Name('leads.')]` | Name **prefix** for every named route in the class. |
| `#[Throttle(60, 1)]` | 60 requests per minute, applied to the whole class. |
| `#[WithPermission(...)]` | Permission middleware for the whole class. |
| `#[WithoutMiddleware('auth:sanctum')]` | Excludes inherited middleware. |
| `#[SkipDiscovery]` | Excludes the class from scanning entirely. |

All of them work on methods too, where they stack on top of the class-level value:

```php
#[Prefix('api/auth')]
#[Middleware(['api', 'auth:sanctum'])]
class LoginController
{
    // The one route in this controller that must stay public.
    #[Post('login', name: 'auth.login')]
    #[WithoutMiddleware('auth:sanctum')]
    #[Throttle(5)]
    public function login(LoginRequest $request): JsonResponse { /* ... */ }
}
```

Middleware is applied in a predictable order: class-level, then method-level, then the verb
attribute's own `middleware:` argument. Duplicates are removed, first occurrence wins.

### Route names

A route is named **only when the method supplies a name**. A class-level `#[Name('leads.')]` is a
prefix, never a name on its own — so adding one can't silently collapse five routes onto a single
name. `#[Name]` on a method overrides the verb attribute's `name:` argument.

```php
#[Name('leads.')]                       // class
#[Get('', name: 'index')]               // → leads.index
#[Get('archive')]                       // → unnamed
```

### Permissions

`#[WithPermission]` turns permission enum cases into middleware. Implement `Permitted` on your enum
to control the exact string:

```php
use AhmedNour\AttributeRouting\Contracts\Permitted;

enum PermissionEnum: string implements Permitted
{
    case EDIT_TASK = 'edit_task';

    public function getMiddleware(): string
    {
        return 'permission:'.$this->value;
    }
}
```

```php
#[WithPermission(PermissionEnum::EDIT_TASK)]
#[Put('{task}', name: 'tasks.update')]
public function update(Task $task): JsonResponse { /* ... */ }
```

Three forms are accepted:

- a `Permitted` enum case → uses its own `getMiddleware()`
- any other backed enum → formatted with `attribute-routing.permission_middleware` (`permission:%s`)
- a raw string → passed straight through if it contains a `:` (e.g. `'can:download-audit'`),
  otherwise formatted

Pass several at once for "any of" (OR) semantics — `#[WithPermission(Permission::A, Permission::B)]`
collapses into a single `permission:a,b` middleware entry, and the user needs only one of them.
To require several *different* permissions (AND), stack the attribute instead — it's repeatable,
and each instance contributes its own middleware entry:

```php
#[WithPermission(Permission::EDIT_TASK)]
#[WithPermission(Permission::MANAGE_PROJECT)]
#[Put('{task}', name: 'tasks.update')]
public function update(Task $task): JsonResponse { /* ... */ }
```

## Configuration

```php
return [
    // Turn discovery off without uninstalling. Ignored when routes are cached.
    'enabled' => env('ATTRIBUTE_ROUTING_ENABLED', true),

    // PSR-4 namespace prefix => directory to scan.
    'paths' => [
        'App\\' => app_path(),
    ],

    // Middleware format for permissions that aren't Permitted enums.
    'permission_middleware' => 'permission:%s',
];
```

Narrowing `paths` to the directory that actually holds your controllers makes boot faster:

```php
'paths' => [
    'App\\Http\\Controllers\\' => app_path('Http/Controllers'),
    'App\\Modules\\' => app_path('Modules'),
],
```

## Performance and route caching

Discovery runs at boot in development. It scans the configured directories, skips any file with no
`#[` in it before autoloading, and only then reflects over classes.

In production you run `php artisan route:cache` as usual. Cached routes already contain everything
discovery would have produced, so **the package does no work at all on a cached boot**.

## Inspecting what was discovered

`php artisan route:list` shows attribute routes alongside the rest. When a route doesn't show up
and you want to know whether the class was even scanned:

```bash
php artisan attribute-routing:list
php artisan attribute-routing:list --path=leads
```

```
  GET|HEAD      /api/leads ......................... leads.index › LeadController@index
  GET|HEAD      /api/leads/{lead} ..................... leads.show › LeadController@show
  POST          /api/leads ......................... leads.store › LeadController@store
  DELETE        /api/leads/{lead} ............... leads.destroy › LeadController@destroy

                                          Showing [4] routes from [65] scanned classes
```

Each route shows the `Controller@method` it came from, and the footer reports how many classes were
scanned — usually enough to spot a `paths` config that doesn't cover your controllers.

Add `-v` for the middleware stack, excluded middleware, and parameter constraints, and to see
controller names in full rather than shortened against your application namespace:

```
  GET|HEAD      /api/leads/{lead} leads.show › App\Http\Controllers\LeadController@show
                 ⇂ api
                 ⇂ auth:sanctum
                 ⇂ permission:view_leads
                 ⇂ where lead = [0-9]+
```

## Gotchas

- **Inherited methods are not re-registered.** A route attribute on a method in an abstract base
  controller registers nothing; each subclass would otherwise register the same URI. Put the
  attribute on the concrete method. Trait methods *do* register, once per using class.
- **Abstract classes, interfaces, and enums are skipped.**
- **Discovery autoloads the classes it scans.** Anything with side effects at class-definition time
  will run at boot. Attribute-free files are skipped before that happens.
- **`Match` is a reserved word**, hence `#[MatchRoute]`.

## Testing

```bash
composer install
composer test
composer lint
```

## Credits

Extracted from the routing layer of a production Laravel application, where it registers 200+ routes.

Built by [Ahmed Nour](https://www.linkedin.com/in/ahmed-nour-dev).

## License

MIT. See [LICENSE](LICENSE).
