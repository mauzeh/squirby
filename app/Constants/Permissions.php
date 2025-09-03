<?php

namespace App\Constants;

class Permissions
{
    public const USERS_VIEW = 'users.view';
    public const USERS_CREATE = 'users.create';
    public const USERS_UPDATE = 'users.update';
    public const USERS_DELETE = 'users.delete';

    public const ROLES_VIEW = 'roles.view';
    public const ROLES_CREATE = 'roles.create';
    public const ROLES_UPDATE = 'roles.update';
    public const ROLES_DELETE = 'roles.delete';

    public const DAILY_LOGS_VIEW = 'daily-logs.view';

    // Permissions created during tests, consider if these should be actual application permissions
    public const TEST_PERMISSION = 'test_permission';
    public const PERMISSION_TO_ASSIGN_1 = 'permission_to_assign_1';
    public const PERMISSION_TO_ASSIGN_2 = 'permission_to_assign_2';
    public const PERMISSION_NOT_ASSIGNED = 'permission_not_assigned';
}
