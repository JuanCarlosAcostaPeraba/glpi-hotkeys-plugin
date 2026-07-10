<?php
declare(strict_types=1);

/**
 * GLPI Hotkeys Plugin - Lightweight Standalone PHP Test Runner
 * This script runs the PHPUnit tests without requiring external dependencies or extensions.
 */

namespace PHPUnit\Framework;

class TestCase {
    protected function setUp(): void {}
    
    protected function assertEquals($expected, $actual): void {
        if ($expected !== $actual) {
            throw new \Exception("Expected " . var_export($expected, true) . ", got " . var_export($actual, true));
        }
    }
    
    protected function assertTrue($actual): void {
        if ($actual !== true) {
            throw new \Exception("Expected true, got " . var_export($actual, true));
        }
    }
    
    protected function assertFalse($actual): void {
        if ($actual !== false) {
            throw new \Exception("Expected false, got " . var_export($actual, true));
        }
    }
    
    protected function assertNull($actual): void {
        if ($actual !== null) {
            throw new \Exception("Expected null, got " . var_export($actual, true));
        }
    }
    
    protected function assertEmpty($actual): void {
        if (!empty($actual)) {
            throw new \Exception("Expected empty, got " . var_export($actual, true));
        }
    }
    
    protected function assertNotEmpty($actual): void {
        if (empty($actual)) {
            throw new \Exception("Expected not empty, got " . var_export($actual, true));
        }
    }
    
    protected function assertCount(int $expected, $actual): void {
        if (!is_countable($actual)) {
            throw new \Exception("Expected countable, got " . var_export($actual, true));
        }
        if (count($actual) !== $expected) {
            throw new \Exception("Expected count " . $expected . ", got " . count($actual));
        }
    }
    
    protected function assertStringContainsString(string $needle, string $haystack): void {
        if (strpos($haystack, $needle) === false) {
            throw new \Exception("Expected '{$haystack}' to contain '{$needle}'");
        }
    }
}

namespace GlpiPlugin\Hotkeys\Tests;

// Include the test file
require_once __DIR__ . '/ConfigTest.php';

echo "Running PHP Unit Tests using standalone test runner...\n\n";

$testClass = new ConfigTest();
$ref = new \ReflectionClass($testClass);
$methods = $ref->getMethods(\ReflectionMethod::IS_PUBLIC);

$passed = 0;
$failed = 0;

foreach ($methods as $method) {
    if (str_starts_with($method->name, 'test')) {
        echo "Running {$method->name}... ";
        try {
            // Invoke setUp before each test case
            $setUpMethod = $ref->getMethod('setUp');
            $setUpMethod->setAccessible(true);
            $setUpMethod->invoke($testClass);
            
            // Run the test method
            $method->invoke($testClass);
            echo "PASSED\n";
            $passed++;
        } catch (\Throwable $e) {
            echo "FAILED: " . $e->getMessage() . "\n";
            $failed++;
        }
    }
}

echo "\nPHP Unit Tests Summary: {$passed} passed, {$failed} failed.\n";
if ($failed > 0) {
    exit(1);
}
exit(0);
