<?php

declare(strict_types=1);

namespace Tests\Unit\Core\Permissions;

use App\Core\Permissions\Enums\PermissionScope;
use PHPUnit\Framework\TestCase;

class PermissionScopeTest extends TestCase
{
    public function test_project_creation_defaults_to_department_scope(): void
    {
        $this->assertSame(
            PermissionScope::Department,
            PermissionScope::defaultForPermission('projects.create'),
        );

        $allowed = PermissionScope::allowedForPermission('projects.create');

        $this->assertContains(PermissionScope::Department, $allowed);
        $this->assertContains(PermissionScope::DepartmentTree, $allowed);
        $this->assertContains(PermissionScope::Organization, $allowed);
        $this->assertNotContains(PermissionScope::Self, $allowed);
        $this->assertNotContains(PermissionScope::Project, $allowed);
    }

    public function test_operational_visibility_permissions_are_scope_aware(): void
    {
        $this->assertSame(
            PermissionScope::Project,
            PermissionScope::defaultForPermission('projects.view'),
        );
        $this->assertSame(
            PermissionScope::Project,
            PermissionScope::defaultForPermission('tasks.create'),
        );

        $this->assertTrue(PermissionScope::allows('projects.view', PermissionScope::Department));
        $this->assertTrue(PermissionScope::allows('tasks.create', PermissionScope::Project));
    }

    public function test_administrative_permissions_remain_organization_scoped(): void
    {
        $this->assertSame(
            PermissionScope::Organization,
            PermissionScope::defaultForPermission('departments.create'),
        );
        $this->assertSame(
            [PermissionScope::Organization],
            PermissionScope::allowedForPermission('departments.create'),
        );
    }
}
