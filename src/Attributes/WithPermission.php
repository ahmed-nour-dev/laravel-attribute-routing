<?php

declare(strict_types=1);

namespace AhmedNour\AttributeRouting\Attributes;

use AhmedNour\AttributeRouting\Contracts\Permitted;
use Attribute;
use BackedEnum;

/**
 * Guard the routes below it with one or more permissions.
 *
 * `#[WithPermission(PermissionEnum::EDIT_TASK)]` on a controller method puts the
 * permission requirement next to the code it protects — and makes "find usages"
 * on an enum case list every route that requires it.
 *
 * Accepts three shapes:
 *  - a {@see Permitted} enum case  → uses its own getMiddleware()
 *  - any other BackedEnum case     → formatted with the configured format string
 *  - a raw string                  → used as-is if it already looks like middleware
 *                                    (contains a `:`), otherwise formatted
 *
 * Multiple arguments of the same middleware are "any of" (OR): passing
 * `#[WithPermission(PermissionEnum::VIEW_X, PermissionEnum::MANAGE_X)]` collapses
 * into a single `permission:view_x,manage_x` middleware entry, since the
 * permission middleware itself treats comma-separated values as "any of". To
 * require several *different* permissions (AND), stack the attribute — it is
 * repeatable — so each instance contributes its own middleware entry.
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
final class WithPermission
{
    /** @var array<int, Permitted|BackedEnum|string> */
    public readonly array $permissions;

    public function __construct(Permitted|BackedEnum|string ...$permissions)
    {
        $this->permissions = $permissions;
    }

    /**
     * Resolve the permissions to middleware strings, collapsing same-named
     * middleware into one comma-separated "any of" entry.
     *
     * @param  string  $format  sprintf format applied to non-Permitted values, e.g. 'permission:%s'
     * @return array<int, string>
     */
    public function toMiddleware(string $format): array
    {
        $resolved = array_map(
            static fn (Permitted|BackedEnum|string $permission): string => match (true) {
                $permission instanceof Permitted => $permission->getMiddleware(),
                $permission instanceof BackedEnum => sprintf($format, (string) $permission->value),
                str_contains($permission, ':') => $permission,
                default => sprintf($format, $permission),
            },
            $this->permissions,
        );

        return $this->collapse($resolved);
    }

    /**
     * Group resolved middleware strings by their name (the part before the
     * first `:`), joining each group's arguments with commas. Middleware
     * with different names (e.g. `permission:` vs `can:`) can't be OR'd
     * together — those pass through untouched, one entry each.
     *
     * @param  array<int, string>  $resolved
     * @return array<int, string>
     */
    private function collapse(array $resolved): array
    {
        $names = [];
        $arguments = [];

        foreach ($resolved as $middleware) {
            [$name, $rest] = str_contains($middleware, ':')
                ? explode(':', $middleware, 2)
                : [$middleware, null];

            if (! array_key_exists($name, $arguments)) {
                $names[] = $name;
                $arguments[$name] = [];
            }

            if ($rest !== null) {
                $arguments[$name][] = $rest;
            }
        }

        return array_map(
            static fn (string $name): string => $arguments[$name] === []
                ? $name
                : $name.':'.implode(',', array_unique($arguments[$name])),
            $names,
        );
    }
}
