<?php
define('CLI_SCRIPT', true);
require_once('config.php');
require_once($CFG->libdir.'/blocklib.php');
require_once($CFG->dirroot.'/blocks/moodleblock.class.php');

// Error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "=== Debug Attendance Leaderboard ===\n";

try {
    echo "1. Testing basic loading...\n";
    
    // Test core component
    $attendance_dir = \core_component::get_component_directory('mod_attendance');
    echo "Attendance dir: " . ($attendance_dir ? "Found" : "Not found") . "\n";
    
    $block_dir = \core_component::get_component_directory('block_attendanceleaderboard');
    echo "Block dir: " . ($block_dir ? "Found" : "Not found") . "\n";
    
    echo "2. Testing class autoloading...\n";
    
    // Test if classes exist
    if (class_exists('block_attendanceleaderboard')) {
        echo "✓ block_attendanceleaderboard class found\n";
    } else {
        echo "✗ block_attendanceleaderboard class NOT found\n";
        // Try manual include
        require_once($CFG->dirroot . '/blocks/attendanceleaderboard/block_attendanceleaderboard.php');
        if (class_exists('block_attendanceleaderboard')) {
            echo "✓ block_attendanceleaderboard loaded manually\n";
        }
    }
    
    echo "3. Testing manager class...\n";
    
    // Test manager class
    $manager_file = $CFG->dirroot . '/blocks/attendanceleaderboard/classes/leaderboard_manager.php';
    if (file_exists($manager_file)) {
        echo "✓ Manager file exists\n";
        require_once($manager_file);
        
        if (class_exists('\block_attendanceleaderboard\leaderboard_manager')) {
            echo "✓ Manager class loaded\n";
            
            // Test creating manager
            $manager = new \block_attendanceleaderboard\leaderboard_manager(2);
            echo "✓ Manager instance created\n";
            
            // Test get_leaderboard method
            echo "5. Testing get_leaderboard method...\n";
            $scores = $manager->get_leaderboard(5);
            echo "✓ get_leaderboard() executed\n";
            echo "   Data type: " . gettype($scores) . "\n";
            echo "   Count: " . count($scores) . "\n";
            
        } else {
            echo "✗ Manager class NOT found after include\n";
        }
    } else {
        echo "✗ Manager file NOT found\n";
    }
    
    echo "4. Testing cache...\n";
    
    // Test cache
    try {
        $cache = \cache::make('block_attendanceleaderboard', 'leaderboarddata');
        echo "✓ Cache created successfully\n";
    } catch (Exception $e) {
        echo "✗ Cache error: " . $e->getMessage() . "\n";
    }
    
    echo "=== Debug completed ===\n";
    
} catch (Throwable $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n";
    echo "Trace:\n" . $e->getTraceAsString() . "\n";
}
?> 