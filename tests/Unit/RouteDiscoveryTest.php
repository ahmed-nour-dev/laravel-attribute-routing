<?php

declare(strict_types=1);

namespace AhmedNour\AttributeRouting\Tests\Unit;

use AhmedNour\AttributeRouting\Discovery\DiscoveredRoute;
use AhmedNour\AttributeRouting\Discovery\RouteDiscovery;
use AhmedNour\AttributeRouting\Tests\Fixtures\Controllers\BaseController;
use AhmedNour\AttributeRouting\Tests\Fixtures\Controllers\ChildController;
use AhmedNour\AttributeRouting\Tests\Fixtures\Controllers\LeadController;
use AhmedNour\AttributeRouting\Tests\Fixtures\Controllers\LoginController;
use AhmedNour\AttributeRouting\Tests\Fixtures\Controllers\SkippedController;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class RouteDiscoveryTest extends TestCase
{
    private RouteDiscovery $discovery;

    protected function setUp(): void
    {
        parent::setUp();

        $this->discovery = new RouteDiscovery('permission:%s');
    }

    #[Test]
    public function it_composes_class_prefixes_in_declaration_order(): void
    {
        $route = $this->route(LeadController::class, 'index');

        $this->assertSame('api/v1/leads', $route->uri);
    }

    #[Test]
    public function it_appends_the_method_path_to_the_class_prefix(): void
    {
        $this->assertSame('api/v1/leads/{lead}', $this->route(LeadController::class, 'show')->uri);
    }

    #[Test]
    public function it_registers_head_alongside_get(): void
    {
        $this->assertSame(['GET', 'HEAD'], $this->route(LeadController::class, 'index')->methods);
    }

    #[Test]
    public function it_prefixes_route_names_with_the_class_name_attribute(): void
    {
        $this->assertSame('leads.index', $this->route(LeadController::class, 'index')->name);
    }

    #[Test]
    public function a_method_level_name_attribute_wins_over_the_name_argument(): void
    {
        $this->assertSame('leads.update', $this->route(LeadController::class, 'update')->name);
    }

    #[Test]
    public function it_leaves_unnamed_routes_unnamed_instead_of_reusing_the_class_prefix(): void
    {
        $routes = $this->routes(LeadController::class);

        $unnamed = array_values(array_filter(
            $routes,
            static fn (DiscoveredRoute $route): bool => $route->uri === 'api/v1/leads/download',
        ));

        $this->assertCount(1, $unnamed);
        $this->assertNull($unnamed[0]->name);
    }

    #[Test]
    public function it_stacks_class_middleware_then_method_middleware_then_route_middleware(): void
    {
        $route = $this->route(LeadController::class, 'destroy');

        $this->assertSame(
            ['api', 'auth:sanctum', 'permission:delete_lead', 'signed'],
            $route->middleware,
        );
    }

    #[Test]
    public function it_collapses_multiple_permissions_on_one_attribute_into_a_single_any_of_entry(): void
    {
        $this->assertSame(
            ['api', 'auth:sanctum', 'permission:view_leads,edit_lead'],
            $this->route(LeadController::class, 'audit')->middleware,
        );
    }

    #[Test]
    public function it_turns_throttle_into_middleware(): void
    {
        $this->assertContains('throttle:6,1', $this->route(LeadController::class, 'store')->middleware);
        $this->assertContains('throttle:5,2', $this->route(LoginController::class, 'login')->middleware);
    }

    #[Test]
    public function it_collects_without_middleware(): void
    {
        $this->assertSame(['auth:sanctum'], $this->route(LoginController::class, 'login')->withoutMiddleware);
    }

    #[Test]
    public function it_keeps_where_constraints(): void
    {
        $this->assertSame(['lead' => '[0-9]+'], $this->route(LeadController::class, 'show')->where);
    }

    #[Test]
    public function it_supports_repeatable_route_attributes(): void
    {
        $uris = array_map(
            static fn (DiscoveredRoute $route): string => $route->uri,
            array_values(array_filter(
                $this->routes(LeadController::class),
                static fn (DiscoveredRoute $route): bool => $route->action[1] === 'export',
            )),
        );

        $this->assertSame(['api/v1/leads/export', 'api/v1/leads/download'], $uris);
    }

    #[Test]
    public function it_resolves_explicit_verbs_for_match_routes(): void
    {
        $this->assertSame(['PUT', 'PATCH'], $this->route(LoginController::class, 'password')->methods);
    }

    #[Test]
    public function it_formats_plain_enums_and_passes_through_raw_middleware_strings(): void
    {
        $middleware = $this->route(LoginController::class, 'export')->middleware;

        $this->assertContains('permission:export_report', $middleware);
        $this->assertContains('can:download-audit', $middleware);
    }

    #[Test]
    public function it_ignores_methods_without_route_attributes(): void
    {
        $actions = array_map(
            static fn (DiscoveredRoute $route): string => $route->action[1],
            $this->routes(LeadController::class),
        );

        $this->assertNotContains('helper', $actions);
    }

    #[Test]
    public function it_skips_classes_marked_with_skip_discovery(): void
    {
        $this->assertSame([], $this->discovery->discover(SkippedController::class));
    }

    #[Test]
    public function it_skips_abstract_classes(): void
    {
        $this->assertSame([], $this->discovery->discover(BaseController::class));
    }

    #[Test]
    public function it_does_not_re_register_routes_inherited_from_a_parent(): void
    {
        $routes = $this->routes(ChildController::class);

        $this->assertCount(1, $routes);
        $this->assertSame('child/own', $routes[0]->uri);
    }

    /**
     * @param  class-string  $class
     * @return list<DiscoveredRoute>
     */
    private function routes(string $class): array
    {
        return $this->discovery->discover($class);
    }

    /**
     * @param  class-string  $class
     */
    private function route(string $class, string $method): DiscoveredRoute
    {
        foreach ($this->routes($class) as $route) {
            if ($route->action[1] === $method) {
                return $route;
            }
        }

        $this->fail("No route discovered for {$class}@{$method}.");
    }
}
