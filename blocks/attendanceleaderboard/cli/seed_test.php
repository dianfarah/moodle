<?php
define('CLI_SCRIPT', true);
try {
    require_once(__DIR__ . '/../../../config.php');
} catch (Throwable $e) {
    echo "REQUIRE ERROR: " . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n";
    exit(1);
}

global $DB;
try {
    $repo = new \block_attendanceleaderboard\motivation\motivation_sentence_repository();
    $repo->seed_static_templates();
    echo "SUCCESS: Templates in DB = " . $DB->count_records('acmls_motivation_sentence') . "\n";
} catch (Throwable $e) {
    echo "ERROR: " . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n";
}
