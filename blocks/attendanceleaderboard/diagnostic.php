<?php
define('CLI_SCRIPT', true);
define('NO_UPGRADE_CHECK', true);
require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/classes/motivation/motivation_sentence_repository.php');
require_once(__DIR__ . '/classes/delivery/delivery_system.php');

try {
    global $DB, $PAGE, $OUTPUT;
    $PAGE->set_url(new moodle_url('/course/view.php', ['id' => 2]));
    $PAGE->set_context(context_course::instance(2));

    $repo = new \block_attendanceleaderboard\motivation\motivation_sentence_repository();
    $repo->seed_static_templates();

    $userid = 3;
    $courseid = 2;

    $profile_record = $DB->get_record(
        'acmls_learner_profile',
        ['userid' => $userid, 'courseid' => $courseid]
    );

    $profile = $profile_record ? \block_attendanceleaderboard\profiling\learner_profile::from_db_record($profile_record) : null;
    $motivation_category = 'reinforcement';

    $delivery = new \block_attendanceleaderboard\delivery\delivery_system();

    $ref = new ReflectionClass($delivery);
    $method = $ref->getMethod('resolve_encouragement_message');
    $method->setAccessible(true);

    $encouragement = $method->invoke($delivery, $userid, $courseid, $profile, $profile_record, $motivation_category);

    echo "Encouragement array:\n";
    print_r($encouragement);

    $html = $delivery->render_block($userid, $courseid);
    file_put_contents(__DIR__ . '/rendered_user_26.html', $html);
    echo "HTML written. Length: " . strlen($html) . "\n";
} catch (Throwable $e) {
    echo "DIAGNOSTIC ERROR: " . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n";
}
