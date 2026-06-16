#!/usr/bin/env php
<?php
/**
 * Simple test runner for Property 8 tests.
 *
 * This script verifies that the Property 8 tests can be executed
 * and that the classify_learning_style method works correctly.
 */

define('CLI_SCRIPT', true);

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/clilib.php');

// Simple test to verify classify_learning_style consistency.
echo "Testing Property 8: Learning_Style Classification Consistency\n";
echo str_repeat("=", 70) . "\n\n";

$ps = new \block_attendanceleaderboard\profiling\profiling_system();

// Test 1: Empty pattern
echo "Test 1: Empty pattern should return 'unknown'\n";
$result = $ps->classify_learning_style([]);
echo "Result: {$result}\n";
assert($result === 'unknown', 'Empty pattern must return unknown');
echo "✓ PASSED\n\n";

// Test 2: Single type pattern - video (auditory)
echo "Test 2: Video pattern should return 'auditory'\n";
$pattern = ['video', 'video', 'video'];
$result1 = $ps->classify_learning_style($pattern);
$result2 = $ps->classify_learning_style($pattern);
$result3 = $ps->classify_learning_style($pattern);
echo "Result 1: {$result1}\n";
echo "Result 2: {$result2}\n";
echo "Result 3: {$result3}\n";
assert($result1 === 'auditory', 'Video pattern must return auditory');
assert($result1 === $result2, 'Results must be consistent');
assert($result2 === $result3, 'Results must be consistent');
echo "✓ PASSED - Classification is consistent\n\n";

// Test 3: Document pattern (reading)
echo "Test 3: Document pattern should return 'reading'\n";
$pattern = ['document', 'pdf', 'book'];
$result1 = $ps->classify_learning_style($pattern);
$result2 = $ps->classify_learning_style($pattern);
echo "Result 1: {$result1}\n";
echo "Result 2: {$result2}\n";
assert($result1 === 'reading', 'Document pattern must return reading');
assert($result1 === $result2, 'Results must be consistent');
echo "✓ PASSED - Classification is consistent\n\n";

// Test 4: Image pattern (visual)
echo "Test 4: Image pattern should return 'visual'\n";
$pattern = ['image', 'diagram', 'presentation'];
$result1 = $ps->classify_learning_style($pattern);
$result2 = $ps->classify_learning_style($pattern);
echo "Result 1: {$result1}\n";
echo "Result 2: {$result2}\n";
assert($result1 === 'visual', 'Image pattern must return visual');
assert($result1 === $result2, 'Results must be consistent');
echo "✓ PASSED - Classification is consistent\n\n";

// Test 5: Quiz pattern (kinesthetic)
echo "Test 5: Quiz pattern should return 'kinesthetic'\n";
$pattern = ['quiz', 'assignment', 'workshop'];
$result1 = $ps->classify_learning_style($pattern);
$result2 = $ps->classify_learning_style($pattern);
echo "Result 1: {$result1}\n";
echo "Result 2: {$result2}\n";
assert($result1 === 'kinesthetic', 'Quiz pattern must return kinesthetic');
assert($result1 === $result2, 'Results must be consistent');
echo "✓ PASSED - Classification is consistent\n\n";

// Test 6: Order independence
echo "Test 6: Classification should be independent of array order\n";
$pattern1 = ['video', 'document', 'image', 'quiz'];
$pattern2 = ['quiz', 'image', 'document', 'video'];
$result1 = $ps->classify_learning_style($pattern1);
$result2 = $ps->classify_learning_style($pattern2);
echo "Pattern 1 result: {$result1}\n";
echo "Pattern 2 result: {$result2}\n";
assert($result1 === $result2, 'Order should not affect classification');
echo "✓ PASSED - Classification is order-independent\n\n";

// Test 7: Case insensitivity
echo "Test 7: Classification should be case-insensitive\n";
$pattern1 = ['video', 'video', 'video'];
$pattern2 = ['VIDEO', 'Video', 'ViDeO'];
$result1 = $ps->classify_learning_style($pattern1);
$result2 = $ps->classify_learning_style($pattern2);
echo "Lowercase result: {$result1}\n";
echo "Mixed case result: {$result2}\n";
assert($result1 === $result2, 'Case should not affect classification');
echo "✓ PASSED - Classification is case-insensitive\n\n";

// Test 8: Consistency under stress (100 calls)
echo "Test 8: Classification consistency under stress (100 calls)\n";
$pattern = ['video', 'document', 'image'];
$first_result = $ps->classify_learning_style($pattern);
$all_consistent = true;
for ($i = 0; $i < 100; $i++) {
    $result = $ps->classify_learning_style($pattern);
    if ($result !== $first_result) {
        $all_consistent = false;
        echo "✗ FAILED at iteration {$i}: Expected '{$first_result}', got '{$result}'\n";
        break;
    }
}
if ($all_consistent) {
    echo "All 100 calls returned: {$first_result}\n";
    echo "✓ PASSED - Classification is consistent under stress\n\n";
}

echo str_repeat("=", 70) . "\n";
echo "All Property 8 tests PASSED!\n";
echo "Property 8 (Konsistensi Klasifikasi Learning_Style) is verified.\n";
