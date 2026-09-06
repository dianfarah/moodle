<?php
define('CLI_SCRIPT', true);
require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/classes/motivation/motivation_sentence_repository.php');
require_once(__DIR__ . '/classes/delivery/delivery_system.php');

global $DB;

$userid = 26;
$courseid = 4;

$profile_record = $DB->get_record(
    'acmls_learner_profile',
    ['userid' => $userid, 'courseid' => $courseid]
);

$profile = \block_attendanceleaderboard\profiling\learner_profile::from_db_record($profile_record);
$motivation_category = 'reinforcement';

$delivery = new \block_attendanceleaderboard\delivery\delivery_system();

// Let's call the protected method resolve_encouragement_message using reflection
$ref = new ReflectionClass($delivery);
$method = $ref->getMethod('resolve_encouragement_message');
$method->setAccessible(true);

$encouragement = $method->invoke($delivery, $userid, $courseid, $profile, $profile_record, $motivation_category);

echo "Encouragement array:\n";
print_r($encouragement);

$html = $delivery->render_block($userid, $courseid);
file_put_contents(__DIR__ . '/rendered_user_26.html', $html);
echo "HTML written to rendered_user_26.html. Length: " . strlen($html) . "\n";
