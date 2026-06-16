<?php
define('CLI_SCRIPT', true);
require_once('config.php');

// Error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "=== Simple Debug - Manager Only ===\n";

try {
    echo "1. Testing core components...\n";
    
    // Test if attendance module exists
    $attendance_dir = \core_component::get_component_directory('mod_attendance');
    echo "Attendance module: " . ($attendance_dir ? "Found" : "Not found") . "\n";
    
    // Test if block directory exists
    $block_dir = \core_component::get_component_directory('block_attendanceleaderboard');
    echo "Block directory: " . ($block_dir ? "Found" : "Not found") . "\n";
    
    echo "2. Testing manager class only...\n";
    
    // Test manager class without loading block class
    $manager_file = $CFG->dirroot . '/blocks/attendanceleaderboard/classes/leaderboard_manager.php';
    if (file_exists($manager_file)) {
        echo "✓ Manager file exists\n";
        
        // Include the manager class
        require_once($manager_file);
        
        if (class_exists('\block_attendanceleaderboard\leaderboard_manager')) {
            echo "✓ Manager class loaded successfully\n";
            
            // Test creating manager instance
            $manager = new \block_attendanceleaderboard\leaderboard_manager(2);
            echo "✓ Manager instance created\n";
            
            // Test update_scores method
            echo "3. Testing update_scores method...\n";
            $result = $manager->update_scores();
            echo "update_scores result: " . ($result ? "Success" : "Failed") . "\n";
            
            // Test get_leaderboard method
            echo "4. Testing get_leaderboard method...\n";
            $scores = $manager->get_leaderboard(5);
            echo "✓ get_leaderboard executed\n";
            echo "   Data type: " . gettype($scores) . "\n";
            echo "   Count: " . count($scores) . "\n";
            
            if (!empty($scores)) {
                $first = $scores[0];
                echo "   First entry type: " . gettype($first) . "\n";
                if (is_array($first)) {
                    echo "   First entry keys: " . implode(', ', array_keys($first)) . "\n";
                    echo "   Sample data: userid=" . $first['userid'] . ", points=" . $first['points'] . "\n";
                }
            }
            
        } else {
            echo "✗ Manager class not found after include\n";
        }
    } else {
        echo "✗ Manager file not found at: $manager_file\n";
    }
    
    echo "5. Testing cache system...\n";
    
    // Test cache
    try {
        $cache = \cache::make('block_attendanceleaderboard', 'leaderboarddata');
        echo "✓ Cache created successfully\n";
        
        // Test cache operations
        $test_key = "test_key";
        $test_data = ["test" => "data"];
        $cache->set($test_key, $test_data);
        $retrieved = $cache->get($test_key);
        echo "✓ Cache set/get works: " . (($retrieved !== false) ? "Yes" : "No") . "\n";
        
    } catch (Exception $e) {
        echo "✗ Cache error: " . $e->getMessage() . "\n";
    }
    
    echo "=== Debug completed successfully ===\n";
    
} catch (Throwable $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n";
    echo "Trace:\n" . $e->getTraceAsString() . "\n";
}
?> 