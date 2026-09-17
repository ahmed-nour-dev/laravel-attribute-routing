<?php

declare(strict_types=1);

namespace AhmedNour\AttributeRouting\Tests\Unit\Attributes;

use AhmedNour\AttributeRouting\Attributes\WithPermission;
use AhmedNour\AttributeRouting\Tests\Fixtures\Enums\PermissionEnum;
use AhmedNour\AttributeRouting\Tests\Fixtures\Enums\PlainPermission;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class WithPermissionTest extends TestCase
{
    #[Test]
    public function a_single_permission_resolves_to_one_middleware_entry(): void
    {
        $middleware = (new WithPermission(PermissionEnum::VIEW_LEADS))->toMiddleware('permission:%s');

        $this->assertSame(['permission:view_leads'], $middleware);
    }

    #[Test]
    public function multiple_permitted_enums_collapse_into_one_any_of_entry(): void
    {
        $middleware = (new WithPermission(
            PermissionEnum::VIEW_LEADS,
            PermissionEnum::EDIT_LEAD,
        ))->toMiddleware('permission:%s');

        $this->assertSame(['permission:view_leads,edit_lead'], $middleware);
    }

    #[Test]
    public function plain_backed_enums_and_permitted_enums_collapse_together_when_formats_match(): void
    {
        $middleware = (new WithPermission(
            PermissionEnum::VIEW_LEADS,
            PlainPermission::EXPORT_REPORT,
        ))->toMiddleware('permission:%s');

        $this->assertSame(['permission:view_leads,export_report'], $middleware);
    }

    #[Test]
    public function differently_named_middleware_stay_as_separate_entries(): void
    {
        $middleware = (new WithPermission(
            PermissionEnum::VIEW_LEADS,
            'can:download-audit',
        ))->toMiddleware('permission:%s');

        $this->assertSame(['permission:view_leads', 'can:download-audit'], $middleware);
    }

    #[Test]
    public function duplicate_permissions_are_not_repeated_in_the_collapsed_entry(): void
    {
        $middleware = (new WithPermission(
            PermissionEnum::VIEW_LEADS,
            PermissionEnum::VIEW_LEADS,
        ))->toMiddleware('permission:%s');

        $this->assertSame(['permission:view_leads'], $middleware);
    }
}
