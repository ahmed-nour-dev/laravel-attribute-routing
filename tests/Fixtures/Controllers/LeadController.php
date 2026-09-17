<?php

declare(strict_types=1);

namespace AhmedNour\AttributeRouting\Tests\Fixtures\Controllers;

use AhmedNour\AttributeRouting\Attributes\Delete;
use AhmedNour\AttributeRouting\Attributes\Get;
use AhmedNour\AttributeRouting\Attributes\Middleware;
use AhmedNour\AttributeRouting\Attributes\Name;
use AhmedNour\AttributeRouting\Attributes\Post;
use AhmedNour\AttributeRouting\Attributes\Prefix;
use AhmedNour\AttributeRouting\Attributes\Put;
use AhmedNour\AttributeRouting\Attributes\Throttle;
use AhmedNour\AttributeRouting\Attributes\Version;
use AhmedNour\AttributeRouting\Attributes\WithPermission;
use AhmedNour\AttributeRouting\Tests\Fixtures\Enums\PermissionEnum;

#[Prefix('api')]
#[Version('v1')]
#[Prefix('leads')]
#[Middleware(['api', 'auth:sanctum'])]
#[Name('leads.')]
class LeadController
{
    #[Get('', name: 'index')]
    #[WithPermission(PermissionEnum::VIEW_LEADS)]
    public function index(): string
    {
        return 'index';
    }

    #[Get('{lead}', name: 'show', where: ['lead' => '[0-9]+'])]
    #[WithPermission(PermissionEnum::VIEW_LEADS)]
    public function show(): string
    {
        return 'show';
    }

    #[Get('{lead}/audit', name: 'audit')]
    #[WithPermission(PermissionEnum::VIEW_LEADS, PermissionEnum::EDIT_LEAD)]
    public function audit(): string
    {
        return 'audit';
    }

    #[Post('', name: 'store')]
    #[WithPermission(PermissionEnum::CREATE_LEAD)]
    #[Throttle(6)]
    public function store(): string
    {
        return 'store';
    }

    #[Put('{lead}')]
    #[Name('update')]
    #[WithPermission(PermissionEnum::EDIT_LEAD)]
    public function update(): string
    {
        return 'update';
    }

    #[Delete('{lead}', name: 'destroy', middleware: 'signed')]
    #[WithPermission(PermissionEnum::DELETE_LEAD)]
    public function destroy(): string
    {
        return 'destroy';
    }

    /** Two paths, one handler — attributes are repeatable. */
    #[Get('export', name: 'export')]
    #[Get('download')]
    public function export(): string
    {
        return 'export';
    }

    /** No route attribute: must never be registered. */
    public function helper(): string
    {
        return 'helper';
    }
}
