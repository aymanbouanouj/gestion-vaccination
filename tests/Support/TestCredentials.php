<?php

declare(strict_types=1);

namespace Tests\Support;

final class TestCredentials
{
    public const PRIMARY_ADMIN_LOGIN = 'qa_admin';
    public const FORCED_ADMIN_LOGIN = 'qa_force_change';

    public const PRIMARY_ADMIN_PASSWORD = 'QaSuite-Primary-2026!';
    public const FORCED_ADMIN_PASSWORD = 'QaSuite-Forced-2026!';
    public const UPDATED_ADMIN_PASSWORD = 'QaSuite-Updated-2026!';

    private function __construct()
    {
    }
}
