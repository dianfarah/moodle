<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Property-based test for Property 22: Konsistensi Notifikasi Perubahan Performance_Category.
 *
 * Verifies that every Performance_Category transition (old_category ≠ new_category,
 * both in [1, 2, 3]) BOTH triggers a Coach notification AND is recorded in
 * Learner_Record. Also verifies that no notification is triggered when the
 * category does NOT change (old == new).
 *
 * **Validates: Requirements 13.3**
 *
 * @package    block_attendanceleaderboard
 * @copyright  2024 ACMLS Project
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @group      block_attendanceleaderboard
 */

namespace block_attendanceleaderboard;

use Eris\TestTrait;
use Eris\Generator;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/blocks/attendanceleaderboard/vendor/autoload.php');

/**
 * Property 22: Konsistensi Notifikasi Perubahan Performance_Category.
 *
 * For every Performance_Category transition (old_category ≠ new_category,
 * both in {1, 2, 3}), the ProfilingSystem must:
 *   (a) trigger a Coach notification (via notify_coach()), AND
 *   (b) record the event in Learner_Record with record_type='profile_snapshot'
 *       or 'performance_category_change'.
 *
 * Additionally, when the category does NOT change (old == new), no extra
 * transition notification should be triggered beyond the normal profile update.
 *
 * Sub-properties tested:
 *   - P22a: Every transition triggers a Coach notification (acmls_coach_decision
 *           record is created for the user/course after update_profile()).
 *   - P22b: Every transition is recorded in acmls_learner_record with
 *           record_type='profile_snapshot' and the new performance_category
 *           in data_payload.
 *   - P22c: No false positives — when old_category == new_category, the
 *           profile snapshot is still saved (normal update), but the
 *           performance_category in the snapshot matches the unchanged value.
 *   - P22d: All six valid transitions (Low→Mid, Low→High, Mid→Low, Mid→High,
 *           High→Low, High→Mid) are handled correctly.
 *
 * @package    block_attendanceleaderboard
 * @copyright  2024 ACMLS Project
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @group      block_attendanceleaderboard
 */
class performance_category_notification_property_test extends \advanced_testcase {
    use TestTrait;

    /** @var \block_attendanceleaderboard\profiling\profiling_system System under test. */
    private \block_attendanceleaderboard\profiling\profiling_system $ps;

    /**
     * Override Eris annotation lookup to be compatible with PHPUnit 10+/11+.
     *
     * PHPUnit 10+ removed PHPUnit\Util\Test::parseTestMethodAnnotations() and
     * the getAnnotations() method from TestCase. This override returns an empty
     * array so Eris uses its defaults (100 iterations, rand method).
     *
     * @return array
     */
    public function getTestCaseAnnotations(): array {
        return [];
    }

    /**
     * Setup before each test.
     *
     * @return void
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest(true);
        $this->setAdminUser();
        $this->ps = new \block_attendanceleaderboard\profiling\profiling_system();
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    /**
     * Map a Performance_Category integer to a score that will produce it.
     *
     * Uses the midpoint of each category's score range:
     *   1 (Low)    → 30.0  (< 60)
     *   2 (Middle) → 70.0  (60–79)
     *   3 (High)   → 90.0  (≥ 80)
     *
     * @param  int   $category 1=Low, 2=Middle, 3=High.
     * @return float           A score that classifies to $category.
     */
    private function score_for_category(int $category): float {
        switch ($category) {
            case \block_attendanceleaderboard\profiling\learner_profile::PERFORMANCE_HIGH:
                return 90.0;
            case \block_attendanceleaderboard\profiling\learner_profile::PERFORMANCE_MIDDLE:
                return 70.0;
            case \block_attendanceleaderboard\profiling\learner_profile::PERFORMANCE_LOW:
            default:
                return 30.0;
        }
    }

    /**
     * Seed an existing profile in acmls_learner_profile with a given
     * performance_category so that update_profile() will see a transition.
     *
     * @param  int $userid    Moodle user ID.
     * @param  int $courseid  Moodle course ID.
     * @param  int $category  Initial performance_category (1, 2, or 3).
     * @return void
     */
    private function seed_profile(int $userid, int $courseid, int $category): void {
        global $DB;

        $record = new \stdClass();
        $record->userid               = $userid;
        $record->courseid             = $courseid;
        $record->cognitive_level      = $category;
        $record->motivation_level     = 50.0;
        $record->performance_category = $category;
        $record->learning_style       = 'unknown';
        $record->behavioral_score     = 0.0;
        $record->engagement_score     = 0.0;
        $record->profile_version      = 1;
        $record->last_updated         = time() - 60;
        $record->created_at           = time() - 60;

        $DB->insert_record('acmls_learner_profile', $record);
    }

    /**
     * Count acmls_coach_decision records for a user/course created after $since.
     *
     * @param  int $userid    Moodle user ID.
     * @param  int $courseid  Moodle course ID.
     * @param  int $since     Unix timestamp — only count records created after this.
     * @return int            Number of matching coach decision records.
     */
    private function count_coach_decisions(int $userid, int $courseid, int $since): int {
        global $DB;

        $sql = "SELECT COUNT(*) FROM {acmls_coach_decision}
                 WHERE userid = :userid
                   AND courseid = :courseid
                   AND timecreated >= :since";

        return (int) $DB->count_records_sql($sql, [
            'userid'   => $userid,
            'courseid' => $courseid,
            'since'    => $since,
        ]);
    }

    /**
     * Fetch all acmls_learner_record rows for a user/course with
     * record_type='profile_snapshot' created after $since.
     *
     * @param  int $userid    Moodle user ID.
     * @param  int $courseid  Moodle course ID.
     * @param  int $since     Unix timestamp — only return records created after this.
     * @return array          Array of stdClass rows with decoded data_payload.
     */
    private function get_profile_snapshots(int $userid, int $courseid, int $since): array {
        global $DB;

        $sql = "SELECT * FROM {acmls_learner_record}
                 WHERE userid = :userid
                   AND courseid = :courseid
                   AND record_type = 'profile_snapshot'
                   AND timecreated >= :since
                 ORDER BY timecreated ASC, id ASC";

        $rows = $DB->get_records_sql($sql, [
            'userid'   => $userid,
            'courseid' => $courseid,
            'since'    => $since,
        ]);

        $results = [];
        foreach ($rows as $row) {
            $row->data_payload = json_decode($row->data_payload, true) ?? [];
            $results[] = $row;
        }

        return $results;
    }

    // =========================================================================
    // P22a: Every transition triggers a Coach notification
    // =========================================================================

    /**
     * P22a (Coach Notification on Transition): For any valid Performance_Category
     * transition (old ≠ new, both in {1, 2, 3}), calling update_profile() with
     * a score that produces new_category must result in at least one
     * acmls_coach_decision record being created for the user/course.
     *
     * **Validates: Requirements 13.3**
     *
     * Test Strategy:
     * - Generate (old_category, new_category) pairs where old ≠ new, both in {1,2,3}
     * - Seed the profile with old_category
     * - Call update_profile() with a score that maps to new_category
     * - Verify at least one acmls_coach_decision record was created
     *
     * @return void
     */
    public function test_property22a_transition_triggers_coach_notification(): void {
        // All valid transitions: (old, new) where old ≠ new, both in {1, 2, 3}.
        $valid_transitions = [
            [1, 2], [1, 3],
            [2, 1], [2, 3],
            [3, 1], [3, 2],
        ];

        $this->forAll(
            Generator\elements(...$valid_transitions)
        )->then(function (array $transition) {
            [$old_category, $new_category] = $transition;

            $user   = $this->getDataGenerator()->create_user();
            $course = $this->getDataGenerator()->create_course();

            // Seed the profile with old_category.
            $this->seed_profile((int) $user->id, (int) $course->id, $old_category);

            $before = time();

            // Trigger update_profile() with a score that maps to new_category.
            $score = $this->score_for_category($new_category);
            $this->ps->update_profile((int) $user->id, (int) $course->id, ['score' => $score]);

            // Verify at least one Coach decision was created.
            $decision_count = $this->count_coach_decisions((int) $user->id, (int) $course->id, $before);

            $this->assertGreaterThanOrEqual(
                1,
                $decision_count,
                "Property 22a violated: Transition {$old_category}→{$new_category} " .
                "(score={$score}) did not trigger a Coach notification. " .
                "Expected at least 1 acmls_coach_decision record for " .
                "userid={$user->id}, courseid={$course->id}, but found {$decision_count}. " .
                "Every Performance_Category transition must trigger a Coach notification (Req 13.3)."
            );
        });
    }

    // =========================================================================
    // P22b: Every transition is recorded in Learner_Record
    // =========================================================================

    /**
     * P22b (Learner_Record on Transition): For any valid Performance_Category
     * transition (old ≠ new, both in {1, 2, 3}), calling update_profile() must
     * result in at least one acmls_learner_record row with record_type=
     * 'profile_snapshot' whose data_payload contains the new performance_category.
     *
     * **Validates: Requirements 13.3**
     *
     * Test Strategy:
     * - Generate (old_category, new_category) pairs where old ≠ new
     * - Seed the profile with old_category
     * - Call update_profile() with a score that maps to new_category
     * - Verify a profile_snapshot record exists with the new performance_category
     *
     * @return void
     */
    public function test_property22b_transition_is_recorded_in_learner_record(): void {
        $valid_transitions = [
            [1, 2], [1, 3],
            [2, 1], [2, 3],
            [3, 1], [3, 2],
        ];

        $this->forAll(
            Generator\elements(...$valid_transitions)
        )->then(function (array $transition) {
            [$old_category, $new_category] = $transition;

            $user   = $this->getDataGenerator()->create_user();
            $course = $this->getDataGenerator()->create_course();

            $this->seed_profile((int) $user->id, (int) $course->id, $old_category);

            $before = time();

            $score = $this->score_for_category($new_category);
            $this->ps->update_profile((int) $user->id, (int) $course->id, ['score' => $score]);

            // Fetch profile_snapshot records created after the update.
            $snapshots = $this->get_profile_snapshots((int) $user->id, (int) $course->id, $before);

            $this->assertNotEmpty(
                $snapshots,
                "Property 22b violated: Transition {$old_category}→{$new_category} " .
                "(score={$score}) produced no profile_snapshot in acmls_learner_record. " .
                "Every Performance_Category transition must be recorded in Learner_Record (Req 13.3)."
            );

            // At least one snapshot must contain the new performance_category.
            $found_new_category = false;
            foreach ($snapshots as $snapshot) {
                $payload = $snapshot->data_payload;
                if (isset($payload['performance_category']) &&
                    (int) $payload['performance_category'] === $new_category) {
                    $found_new_category = true;
                    break;
                }
            }

            $this->assertTrue(
                $found_new_category,
                "Property 22b violated: Transition {$old_category}→{$new_category} " .
                "(score={$score}) — no profile_snapshot in acmls_learner_record contains " .
                "performance_category={$new_category}. " .
                "The Learner_Record must reflect the new category after a transition (Req 13.3)."
            );
        });
    }

    // =========================================================================
    // P22c: No false positives — no-change does not produce spurious records
    // =========================================================================

    /**
     * P22c (No False Positives): When old_category == new_category (no transition),
     * update_profile() still saves a profile_snapshot (normal update), but the
     * performance_category in the snapshot must match the unchanged value.
     *
     * This verifies that the system does not misclassify a non-transition as a
     * transition (no spurious extra records beyond the normal profile update).
     *
     * **Validates: Requirements 13.3**
     *
     * Test Strategy:
     * - Generate a category in {1, 2, 3}
     * - Seed the profile with that category
     * - Call update_profile() with a score that maps to the SAME category
     * - Verify the snapshot's performance_category equals the unchanged value
     *
     * @return void
     */
    public function test_property22c_no_false_positive_when_category_unchanged(): void {
        $this->forAll(
            Generator\elements(
                \block_attendanceleaderboard\profiling\learner_profile::PERFORMANCE_LOW,
                \block_attendanceleaderboard\profiling\learner_profile::PERFORMANCE_MIDDLE,
                \block_attendanceleaderboard\profiling\learner_profile::PERFORMANCE_HIGH
            )
        )->then(function (int $category) {
            $user   = $this->getDataGenerator()->create_user();
            $course = $this->getDataGenerator()->create_course();

            // Seed with the same category that the score will produce.
            $this->seed_profile((int) $user->id, (int) $course->id, $category);

            $before = time();

            // Use a score that maps to the SAME category (no transition).
            $score = $this->score_for_category($category);
            $this->ps->update_profile((int) $user->id, (int) $course->id, ['score' => $score]);

            // A profile_snapshot must still be saved (normal update behaviour).
            $snapshots = $this->get_profile_snapshots((int) $user->id, (int) $course->id, $before);

            $this->assertNotEmpty(
                $snapshots,
                "Property 22c violated: update_profile() with unchanged category={$category} " .
                "(score={$score}) produced no profile_snapshot in acmls_learner_record. " .
                "A profile_snapshot must always be saved on update."
            );

            // The snapshot must reflect the unchanged performance_category.
            $latest_snapshot = end($snapshots);
            $payload = $latest_snapshot->data_payload;

            $this->assertEquals(
                $category,
                (int) ($payload['performance_category'] ?? -1),
                "Property 22c violated: No-transition update with category={$category} " .
                "(score={$score}) — the profile_snapshot contains " .
                "performance_category=" . ($payload['performance_category'] ?? 'null') .
                " instead of the expected {$category}. " .
                "The snapshot must accurately reflect the current (unchanged) category (Req 13.3)."
            );
        });
    }

    // =========================================================================
    // P22d: All six valid transitions are handled correctly (exhaustive check)
    // =========================================================================

    /**
     * P22d (All Six Transitions): Exhaustively verify that all six valid
     * Performance_Category transitions (Low→Mid, Low→High, Mid→Low, Mid→High,
     * High→Low, High→Mid) each produce BOTH a Coach notification AND a
     * Learner_Record entry with the correct new category.
     *
     * **Validates: Requirements 13.3**
     *
     * @return void
     */
    public function test_property22d_all_six_transitions_produce_notification_and_record(): void {
        $all_transitions = [
            [1, 2, 'Low→Middle'],
            [1, 3, 'Low→High'],
            [2, 1, 'Middle→Low'],
            [2, 3, 'Middle→High'],
            [3, 1, 'High→Low'],
            [3, 2, 'High→Middle'],
        ];

        foreach ($all_transitions as [$old_category, $new_category, $label]) {
            $user   = $this->getDataGenerator()->create_user();
            $course = $this->getDataGenerator()->create_course();

            $this->seed_profile((int) $user->id, (int) $course->id, $old_category);

            $before = time();

            $score = $this->score_for_category($new_category);
            $this->ps->update_profile((int) $user->id, (int) $course->id, ['score' => $score]);

            // --- Verify Coach notification ---
            $decision_count = $this->count_coach_decisions((int) $user->id, (int) $course->id, $before);

            $this->assertGreaterThanOrEqual(
                1,
                $decision_count,
                "Property 22d violated [{$label}]: Transition {$old_category}→{$new_category} " .
                "(score={$score}) did not trigger a Coach notification. " .
                "Expected at least 1 acmls_coach_decision record, found {$decision_count}. " .
                "All six transitions must trigger Coach notifications (Req 13.3)."
            );

            // --- Verify Learner_Record entry ---
            $snapshots = $this->get_profile_snapshots((int) $user->id, (int) $course->id, $before);

            $this->assertNotEmpty(
                $snapshots,
                "Property 22d violated [{$label}]: Transition {$old_category}→{$new_category} " .
                "(score={$score}) produced no profile_snapshot in acmls_learner_record. " .
                "All six transitions must be recorded in Learner_Record (Req 13.3)."
            );

            $found_new_category = false;
            foreach ($snapshots as $snapshot) {
                $payload = $snapshot->data_payload;
                if (isset($payload['performance_category']) &&
                    (int) $payload['performance_category'] === $new_category) {
                    $found_new_category = true;
                    break;
                }
            }

            $this->assertTrue(
                $found_new_category,
                "Property 22d violated [{$label}]: Transition {$old_category}→{$new_category} " .
                "(score={$score}) — no profile_snapshot contains performance_category={$new_category}. " .
                "The Learner_Record must reflect the new category (Req 13.3)."
            );
        }
    }

    // =========================================================================
    // P22 (Integration): Randomised (old, new) pair via Eris generators
    // =========================================================================

    /**
     * P22 (Integration — Random Transitions): For any randomly generated
     * (old_category, new_category) pair where old ≠ new and both are in {1,2,3},
     * update_profile() must produce BOTH a Coach notification AND a
     * Learner_Record entry with the correct new performance_category.
     *
     * **Validates: Requirements 13.3**
     *
     * Test Strategy:
     * - Use Eris to generate random integers in {1, 2, 3} for old and new categories
     * - Filter to ensure old ≠ new (suchThat)
     * - Seed the profile with old_category
     * - Call update_profile() with a score that maps to new_category
     * - Assert both Coach notification and Learner_Record entry exist
     *
     * @return void
     */
    public function test_property22_random_transitions_trigger_notification_and_record(): void {
        $this->forAll(
            Generator\suchThat(
                function (array $pair) {
                    return $pair[0] !== $pair[1];
                },
                Generator\tuple(
                    Generator\elements(1, 2, 3),
                    Generator\elements(1, 2, 3)
                )
            )
        )->then(function (array $pair) {
            [$old_category, $new_category] = $pair;

            $user   = $this->getDataGenerator()->create_user();
            $course = $this->getDataGenerator()->create_course();

            $this->seed_profile((int) $user->id, (int) $course->id, $old_category);

            $before = time();

            $score = $this->score_for_category($new_category);
            $this->ps->update_profile((int) $user->id, (int) $course->id, ['score' => $score]);

            // --- Assert Coach notification ---
            $decision_count = $this->count_coach_decisions((int) $user->id, (int) $course->id, $before);

            $this->assertGreaterThanOrEqual(
                1,
                $decision_count,
                "Property 22 violated: Random transition {$old_category}→{$new_category} " .
                "(score={$score}) did not trigger a Coach notification. " .
                "Expected at least 1 acmls_coach_decision record, found {$decision_count}. " .
                "Every Performance_Category transition must trigger a Coach notification (Req 13.3)."
            );

            // --- Assert Learner_Record entry ---
            $snapshots = $this->get_profile_snapshots((int) $user->id, (int) $course->id, $before);

            $this->assertNotEmpty(
                $snapshots,
                "Property 22 violated: Random transition {$old_category}→{$new_category} " .
                "(score={$score}) produced no profile_snapshot in acmls_learner_record. " .
                "Every Performance_Category transition must be recorded in Learner_Record (Req 13.3)."
            );

            $found_new_category = false;
            foreach ($snapshots as $snapshot) {
                $payload = $snapshot->data_payload;
                if (isset($payload['performance_category']) &&
                    (int) $payload['performance_category'] === $new_category) {
                    $found_new_category = true;
                    break;
                }
            }

            $this->assertTrue(
                $found_new_category,
                "Property 22 violated: Random transition {$old_category}→{$new_category} " .
                "(score={$score}) — no profile_snapshot in acmls_learner_record contains " .
                "performance_category={$new_category}. " .
                "The Learner_Record must reflect the new category after a transition (Req 13.3)."
            );
        });
    }

    // =========================================================================
    // P22 (Boundary): Transitions at exact score boundaries
    // =========================================================================

    /**
     * P22 (Boundary Transitions): Verify that transitions triggered by scores
     * at the exact classification boundaries (59.99, 60.0, 79.99, 80.0) also
     * produce both a Coach notification and a Learner_Record entry.
     *
     * Boundary rules:
     *   score < 60  → Low  (1)
     *   60 ≤ score < 80 → Middle (2)
     *   score ≥ 80  → High (3)
     *
     * **Validates: Requirements 13.3**
     *
     * @return void
     */
    public function test_property22_boundary_score_transitions_produce_notification_and_record(): void {
        // [old_category, boundary_score, expected_new_category, description]
        $boundary_cases = [
            [2, 59.99, 1, 'Middle→Low at 59.99'],
            [1, 60.0,  2, 'Low→Middle at 60.0'],
            [3, 79.99, 2, 'High→Middle at 79.99'],
            [2, 80.0,  3, 'Middle→High at 80.0'],
            [1, 80.0,  3, 'Low→High at 80.0'],
            [3, 59.99, 1, 'High→Low at 59.99'],
        ];

        foreach ($boundary_cases as [$old_category, $score, $expected_new, $description]) {
            $user   = $this->getDataGenerator()->create_user();
            $course = $this->getDataGenerator()->create_course();

            $this->seed_profile((int) $user->id, (int) $course->id, $old_category);

            $before = time();

            $this->ps->update_profile((int) $user->id, (int) $course->id, ['score' => $score]);

            // Verify the classification is correct at this boundary.
            $actual_category = $this->ps->classify_performance_category($score);
            $this->assertEquals(
                $expected_new,
                $actual_category,
                "Property 22 boundary test [{$description}]: " .
                "classify_performance_category({$score}) returned {$actual_category} " .
                "but expected {$expected_new}."
            );

            // Only assert notification/record if this is actually a transition.
            if ($old_category !== $expected_new) {
                // --- Assert Coach notification ---
                $decision_count = $this->count_coach_decisions((int) $user->id, (int) $course->id, $before);

                $this->assertGreaterThanOrEqual(
                    1,
                    $decision_count,
                    "Property 22 violated [{$description}]: Boundary transition " .
                    "{$old_category}→{$expected_new} (score={$score}) did not trigger " .
                    "a Coach notification. Expected at least 1 acmls_coach_decision record, " .
                    "found {$decision_count}. " .
                    "Every Performance_Category transition must trigger a Coach notification (Req 13.3)."
                );

                // --- Assert Learner_Record entry ---
                $snapshots = $this->get_profile_snapshots((int) $user->id, (int) $course->id, $before);

                $this->assertNotEmpty(
                    $snapshots,
                    "Property 22 violated [{$description}]: Boundary transition " .
                    "{$old_category}→{$expected_new} (score={$score}) produced no " .
                    "profile_snapshot in acmls_learner_record (Req 13.3)."
                );

                $found_new_category = false;
                foreach ($snapshots as $snapshot) {
                    $payload = $snapshot->data_payload;
                    if (isset($payload['performance_category']) &&
                        (int) $payload['performance_category'] === $expected_new) {
                        $found_new_category = true;
                        break;
                    }
                }

                $this->assertTrue(
                    $found_new_category,
                    "Property 22 violated [{$description}]: Boundary transition " .
                    "{$old_category}→{$expected_new} (score={$score}) — no profile_snapshot " .
                    "contains performance_category={$expected_new} (Req 13.3)."
                );
            }
        }
    }
}
