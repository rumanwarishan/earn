<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase as BaseTestCase;

/**
 * Deliberately does NOT wrap each test in an outer transaction. Services under
 * test call Database::transaction(), which decides whether it owns the
 * begin/commit/rollback by checking PDO::inTransaction() - a connection-global
 * flag. An outer test-owned transaction would make every service call think
 * it's nested and skip its own rollback on failure, hiding real rollback bugs
 * behind false green tests. Isolation instead comes from TestSeed::createUser()
 * generating unique random identifiers per test and a full-table truncate once
 * at suite bootstrap (see tests/bootstrap.php).
 */
abstract class TestCase extends BaseTestCase
{
}
