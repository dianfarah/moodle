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
 * Property-based test for Property 21: Dominansi Bobot Data Terbaru.
 *
 * Verifies that ProfilingSystem::calculate_motivation_level() gives higher
 * weight to recent data than to historical data, and that the ratio of
 * changes is proportional to alpha / (1 - alpha).
 *
 * **Validates: Requirements 13.2**
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
 * Property 21: Dominansi Bobot Data Terbaru.
 *
 * For any valid inputs, a change applied to recent_data must produce a larger
 * change in the output of calculate_motivation_level() than the same change
 * applied to historical_average, whenever alpha > 0.5.
 *
 * The weighted moving average formula is:
 *   new_value = (recent_data × alpha) + (historical_average × (1 − alpha))
 *
 * Sub-properties:
 *
 *   - P21a: For any base in [10, 90] and delta in [1, 20], applying delta to
 *           recent_data produces a larger output change than applying the same
 *           delta to historical_average (with default alpha = 0.7).
 *
 *   - P21b: Alpha determines dominance:
 *           alpha > 0.5 → recent dominates
 *           alpha = 0.5 → changes are equal
 *           alpha < 0.5 → historical dominates
 *
 *   - P21c: The ratio of recent_change to historical_change equals
 *           alpha / (1 − alpha) when clamping does not interfere.
 *
 *   - P21d: With default alpha = 0.7, the ratio is exactly 0.7/0.3 ≈ 2.333.
 *
 *   - P21e: Even when clamping occurs, the result is always in [0, 100].
 *
 * @package    block_attendanceleaderboard
 * @copyright  2024 ACMLS Project
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @group      block_attendanceleaderboard
 */
class recent_data_dominance_property_test extends \advanced_testcase {
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
    // P21a: Recent data change dominates historical data change
    // =========================================================================

    /**
     * P21a (Recent Data Dominance): For any base value in [10, 90] and delta in
     * [1, 20], applying delta to recent_data produces a strictly larger change
     * in the output than applying the same delta to historical_average.
     *
     * Formally:
     *   change_recent   = |f(base + delta, base)   − f(base, base)|
     *   change_historical = |f(base, base + delta) − f(base, base)|
     *   change_recent > change_historical
     *
     * This holds because alpha (0.7) > (1 − alpha) (0.3).
     *
     * **Validates: Requirements 13.2**
     *
     * Test Strategy:
     * - Generate base in [10, 90] and delta in [1, 20] to avoid clamping
     * - Compute the output change when delta is applied to recent_data
     * - Compute the output change when delta is applied to historical_average
     * - Assert that the recent change is strictly greater
     *
     * @return void
     */
    public function test_property21a_recent_data_change_dominates_historical_change(): void {
        $this->forAll(
            Generator\choose(10, 90),  // base value in [10, 90]
            Generator\choose(1, 20)    // delta in [1, 20]
        )->then(function (int $base_int, int $delta_int) {
            $base  = (float) $base_int;
            $delta = (float) $delta_int;

            // Baseline: both recent and historical equal base.
            $baseline = $this->ps->calculate_motivation_level($base, $base);

            // Apply delta to recent_data only.
            $with_recent_delta = $this->ps->calculate_motivation_level($base + $delta, $base);

            // Apply delta to historical_average only.
            $with_historical_delta = $this->ps->calculate_motivation_level($base, $base + $delta);

            $change_recent     = abs($with_recent_delta - $baseline);
            $change_historical = abs($with_historical_delta - $baseline);

            $this->assertGreaterThan(
                $change_historical,
                $change_recent,
                "Property 21a violated: base={$base}, delta={$delta}. " .
                "change_recent={$change_recent} must be > change_historical={$change_historical}. " .
                "Recent data (alpha=0.7) must produce a larger output change than historical data " .
                "(1-alpha=0.3) for the same delta. " .
                "Dynamic profile updating must give higher weight to recent data (Req 13.2)."
            );
        });
    }

    /**
     * P21a (Known Values): Explicit verification with known base and delta values
     * to confirm the dominance property holds for representative cases.
     *
     * **Validates: Requirements 13.2**
     *
     * @return void
     */
    public function test_property21a_known_values_confirm_recent_dominance(): void {
        $test_cases = [
            // [base, delta]
            [50.0, 10.0],
            [30.0,  5.0],
            [70.0, 15.0],
            [20.0,  1.0],
            [80.0, 10.0],
        ];

        foreach ($test_cases as [$base, $delta]) {
            $baseline              = $this->ps->calculate_motivation_level($base, $base);
            $with_recent_delta     = $this->ps->calculate_motivation_level($base + $delta, $base);
            $with_historical_delta = $this->ps->calculate_motivation_level($base, $base + $delta);

            $change_recent     = abs($with_recent_delta - $baseline);
            $change_historical = abs($with_historical_delta - $baseline);

            $this->assertGreaterThan(
                $change_historical,
                $change_recent,
                "Property 21a violated for base={$base}, delta={$delta}: " .
                "change_recent={$change_recent} must be > change_historical={$change_historical}. " .
                "Recent data must dominate historical data (Req 13.2)."
            );
        }
    }

    // =========================================================================
    // P21b: Alpha determines which data source dominates
    // =========================================================================

    /**
     * P21b (Alpha > 0.5 → Recent Dominates): When alpha > 0.5, a change applied
     * to recent_data always produces a larger output change than the same change
     * applied to historical_average.
     *
     * **Validates: Requirements 13.2**
     *
     * @return void
     */
    public function test_property21b_alpha_greater_than_half_recent_dominates(): void {
        $base  = 50.0;
        $delta = 10.0;

        // Test several alpha values > 0.5.
        $alpha_values = [0.6, 0.7, 0.8, 0.9, 0.99];

        foreach ($alpha_values as $alpha) {
            $baseline              = $this->ps->calculate_motivation_level($base, $base, $alpha);
            $with_recent_delta     = $this->ps->calculate_motivation_level($base + $delta, $base, $alpha);
            $with_historical_delta = $this->ps->calculate_motivation_level($base, $base + $delta, $alpha);

            $change_recent     = abs($with_recent_delta - $baseline);
            $change_historical = abs($with_historical_delta - $baseline);

            $this->assertGreaterThan(
                $change_historical,
                $change_recent,
                "Property 21b violated for alpha={$alpha}: " .
                "change_recent={$change_recent} must be > change_historical={$change_historical}. " .
                "When alpha > 0.5, recent data must dominate historical data (Req 13.2)."
            );
        }
    }

    /**
     * P21b (Alpha = 0.5 → Equal Changes): When alpha = 0.5, a change applied to
     * recent_data produces exactly the same output change as the same change
     * applied to historical_average.
     *
     * **Validates: Requirements 13.2**
     *
     * @return void
     */
    public function test_property21b_alpha_equal_half_produces_equal_changes(): void {
        $base  = 50.0;
        $delta = 10.0;
        $alpha = 0.5;

        $baseline              = $this->ps->calculate_motivation_level($base, $base, $alpha);
        $with_recent_delta     = $this->ps->calculate_motivation_level($base + $delta, $base, $alpha);
        $with_historical_delta = $this->ps->calculate_motivation_level($base, $base + $delta, $alpha);

        $change_recent     = abs($with_recent_delta - $baseline);
        $change_historical = abs($with_historical_delta - $baseline);

        $this->assertEqualsWithDelta(
            $change_recent,
            $change_historical,
            0.0001,
            "Property 21b violated for alpha=0.5: " .
            "change_recent={$change_recent} must equal change_historical={$change_historical}. " .
            "When alpha = 0.5, both data sources must have equal influence (Req 13.2)."
        );
    }

    /**
     * P21b (Alpha < 0.5 → Historical Dominates): When alpha < 0.5, a change
     * applied to historical_average produces a larger output change than the
     * same change applied to recent_data.
     *
     * **Validates: Requirements 13.2**
     *
     * @return void
     */
    public function test_property21b_alpha_less_than_half_historical_dominates(): void {
        $base  = 50.0;
        $delta = 10.0;

        // Test several alpha values < 0.5.
        $alpha_values = [0.1, 0.2, 0.3, 0.4, 0.49];

        foreach ($alpha_values as $alpha) {
            $baseline              = $this->ps->calculate_motivation_level($base, $base, $alpha);
            $with_recent_delta     = $this->ps->calculate_motivation_level($base + $delta, $base, $alpha);
            $with_historical_delta = $this->ps->calculate_motivation_level($base, $base + $delta, $alpha);

            $change_recent     = abs($with_recent_delta - $baseline);
            $change_historical = abs($with_historical_delta - $baseline);

            $this->assertGreaterThan(
                $change_recent,
                $change_historical,
                "Property 21b violated for alpha={$alpha}: " .
                "change_historical={$change_historical} must be > change_recent={$change_recent}. " .
                "When alpha < 0.5, historical data must dominate recent data (Req 13.2)."
            );
        }
    }

    // =========================================================================
    // P21c: Ratio of changes is proportional to alpha / (1 - alpha)
    // =========================================================================

    /**
     * P21c (Proportional Ratio): For any alpha in (0, 1) and values that avoid
     * clamping, the ratio of recent_change to historical_change equals
     * alpha / (1 − alpha).
     *
     * Formally:
     *   change_recent / change_historical = alpha / (1 − alpha)
     *
     * **Validates: Requirements 13.2**
     *
     * Test Strategy:
     * - Generate base in [20, 80] and delta in [1, 10] to avoid clamping
     * - Generate alpha in (0.1, 0.9) as integer tenths for simplicity
     * - Compute the ratio and compare to alpha / (1 − alpha)
     *
     * @return void
     */
    public function test_property21c_ratio_of_changes_proportional_to_alpha(): void {
        $this->forAll(
            Generator\choose(20, 80),  // base in [20, 80]
            Generator\choose(1, 10),   // delta in [1, 10]
            Generator\choose(1, 9)     // alpha_tenths: 1..9 → alpha = 0.1..0.9
        )->then(function (int $base_int, int $delta_int, int $alpha_tenths) {
            $base  = (float) $base_int;
            $delta = (float) $delta_int;
            $alpha = $alpha_tenths / 10.0;

            // Skip edge cases where (1 - alpha) is too small to divide safely.
            if ($alpha >= 0.99 || $alpha <= 0.01) {
                return;
            }

            $baseline              = $this->ps->calculate_motivation_level($base, $base, $alpha);
            $with_recent_delta     = $this->ps->calculate_motivation_level($base + $delta, $base, $alpha);
            $with_historical_delta = $this->ps->calculate_motivation_level($base, $base + $delta, $alpha);

            $change_recent     = abs($with_recent_delta - $baseline);
            $change_historical = abs($with_historical_delta - $baseline);

            // Skip if clamping occurred (changes would be distorted).
            $unclamped_recent     = $base * $alpha + $base * (1.0 - $alpha) + $delta * $alpha;
            $unclamped_historical = $base * $alpha + $base * (1.0 - $alpha) + $delta * (1.0 - $alpha);
            if ($unclamped_recent > 100.0 || $unclamped_historical > 100.0) {
                return;
            }

            // Skip if historical change is effectively zero (avoid division by zero).
            if ($change_historical < 0.0001) {
                return;
            }

            $actual_ratio   = $change_recent / $change_historical;
            $expected_ratio = $alpha / (1.0 - $alpha);

            $this->assertEqualsWithDelta(
                $expected_ratio,
                $actual_ratio,
                0.0001,
                "Property 21c violated: base={$base}, delta={$delta}, alpha={$alpha}. " .
                "actual_ratio={$actual_ratio} must equal expected_ratio={$expected_ratio} " .
                "(= alpha / (1 - alpha) = {$alpha} / " . (1.0 - $alpha) . "). " .
                "The ratio of recent_change to historical_change must equal alpha / (1 - alpha) " .
                "when clamping does not interfere (Req 13.2)."
            );
        });
    }

    /**
     * P21c (Known Ratios): Explicit verification of the proportionality ratio
     * for several known alpha values.
     *
     * **Validates: Requirements 13.2**
     *
     * @return void
     */
    public function test_property21c_known_alpha_values_produce_correct_ratios(): void {
        $base  = 50.0;
        $delta =  5.0;

        $test_cases = [
            // [alpha, expected_ratio]
            [0.7, 0.7 / 0.3],   // ≈ 2.333
            [0.6, 0.6 / 0.4],   // 1.5
            [0.8, 0.8 / 0.2],   // 4.0
            [0.9, 0.9 / 0.1],   // 9.0
            [0.3, 0.3 / 0.7],   // ≈ 0.429
        ];

        foreach ($test_cases as [$alpha, $expected_ratio]) {
            $baseline              = $this->ps->calculate_motivation_level($base, $base, $alpha);
            $with_recent_delta     = $this->ps->calculate_motivation_level($base + $delta, $base, $alpha);
            $with_historical_delta = $this->ps->calculate_motivation_level($base, $base + $delta, $alpha);

            $change_recent     = abs($with_recent_delta - $baseline);
            $change_historical = abs($with_historical_delta - $baseline);

            $this->assertGreaterThan(
                0.0,
                $change_historical,
                "Property 21c: change_historical must be > 0 for alpha={$alpha}."
            );

            $actual_ratio = $change_recent / $change_historical;

            $this->assertEqualsWithDelta(
                $expected_ratio,
                $actual_ratio,
                0.0001,
                "Property 21c violated for alpha={$alpha}: " .
                "actual_ratio={$actual_ratio} must equal expected_ratio={$expected_ratio}. " .
                "The ratio of changes must equal alpha / (1 - alpha) (Req 13.2)."
            );
        }
    }

    // =========================================================================
    // P21d: Default alpha = 0.7 produces ratio ≈ 2.333
    // =========================================================================

    /**
     * P21d (Default Alpha Ratio): With the default alpha = 0.7, the ratio of
     * recent_change to historical_change is exactly 0.7 / 0.3 ≈ 2.333.
     *
     * **Validates: Requirements 13.2**
     *
     * Test Strategy:
     * - Generate base in [10, 90] and delta in [1, 10] to avoid clamping
     * - Call calculate_motivation_level() with the default alpha (no explicit alpha)
     * - Verify the ratio equals 0.7 / 0.3
     *
     * @return void
     */
    public function test_property21d_default_alpha_ratio_is_seven_thirds(): void {
        $this->forAll(
            Generator\choose(10, 90),  // base in [10, 90]
            Generator\choose(1, 10)    // delta in [1, 10]
        )->then(function (int $base_int, int $delta_int) {
            $base  = (float) $base_int;
            $delta = (float) $delta_int;

            // Skip if clamping would occur.
            if (($base + $delta) > 100.0) {
                return;
            }

            // Use default alpha = 0.7 (no explicit alpha argument).
            $baseline              = $this->ps->calculate_motivation_level($base, $base);
            $with_recent_delta     = $this->ps->calculate_motivation_level($base + $delta, $base);
            $with_historical_delta = $this->ps->calculate_motivation_level($base, $base + $delta);

            $change_recent     = abs($with_recent_delta - $baseline);
            $change_historical = abs($with_historical_delta - $baseline);

            // Skip if historical change is effectively zero.
            if ($change_historical < 0.0001) {
                return;
            }

            $actual_ratio   = $change_recent / $change_historical;
            $expected_ratio = 0.7 / 0.3; // ≈ 2.3333...

            $this->assertEqualsWithDelta(
                $expected_ratio,
                $actual_ratio,
                0.0001,
                "Property 21d violated: base={$base}, delta={$delta}. " .
                "actual_ratio={$actual_ratio} must equal 0.7/0.3={$expected_ratio}. " .
                "With default alpha=0.7, the ratio of recent_change to historical_change " .
                "must be exactly 7/3 ≈ 2.333 (Req 13.2)."
            );
        });
    }

    /**
     * P21d (Explicit Default Alpha): Verify that passing alpha=0.7 explicitly
     * produces the same result as using the default parameter.
     *
     * **Validates: Requirements 13.2**
     *
     * @return void
     */
    public function test_property21d_explicit_default_alpha_matches_implicit(): void {
        $this->forAll(
            Generator\choose(0, 10000),  // recent_data raw (÷100 → [0, 100])
            Generator\choose(0, 10000)   // historical_average raw (÷100 → [0, 100])
        )->then(function (int $raw_recent, int $raw_hist) {
            $recent  = $raw_recent / 100.0;
            $hist    = $raw_hist / 100.0;

            $implicit = $this->ps->calculate_motivation_level($recent, $hist);
            $explicit = $this->ps->calculate_motivation_level($recent, $hist, 0.7);

            $this->assertSame(
                $implicit,
                $explicit,
                "Property 21d violated: recent={$recent}, hist={$hist}. " .
                "Implicit default alpha result={$implicit} must equal explicit alpha=0.7 result={$explicit}. " .
                "The default alpha must be 0.7 (Req 13.2)."
            );
        });
    }

    // =========================================================================
    // P21e: Clamping invariant — result always in [0, 100]
    // =========================================================================

    /**
     * P21e (Clamping Invariant): For any inputs, including those that would
     * produce out-of-range values before clamping, calculate_motivation_level()
     * must always return a value in [0.0, 100.0].
     *
     * **Validates: Requirements 13.2**
     *
     * Test Strategy:
     * - Generate recent_data and historical_average across the full range [0, 100]
     * - Generate alpha across (0, 1)
     * - Verify the result is always clamped to [0, 100]
     *
     * @return void
     */
    public function test_property21e_result_always_clamped_to_valid_range(): void {
        $this->forAll(
            Generator\choose(0, 10000),  // recent_data raw (÷100 → [0, 100])
            Generator\choose(0, 10000),  // historical_average raw (÷100 → [0, 100])
            Generator\choose(1, 99)      // alpha_percent: 1..99 → alpha = 0.01..0.99
        )->then(function (int $raw_recent, int $raw_hist, int $alpha_pct) {
            $recent  = $raw_recent / 100.0;
            $hist    = $raw_hist / 100.0;
            $alpha   = $alpha_pct / 100.0;

            $result = $this->ps->calculate_motivation_level($recent, $hist, $alpha);

            $this->assertGreaterThanOrEqual(
                0.0,
                $result,
                "Property 21e violated: recent={$recent}, hist={$hist}, alpha={$alpha}. " .
                "Result={$result} is below 0.0. " .
                "calculate_motivation_level() must always return a value >= 0.0 (Req 13.2)."
            );

            $this->assertLessThanOrEqual(
                100.0,
                $result,
                "Property 21e violated: recent={$recent}, hist={$hist}, alpha={$alpha}. " .
                "Result={$result} exceeds 100.0. " .
                "calculate_motivation_level() must always return a value <= 100.0 (Req 13.2)."
            );
        });
    }

    /**
     * P21e (Boundary Clamping): Verify that extreme input values are correctly
     * clamped to [0, 100].
     *
     * **Validates: Requirements 13.2**
     *
     * @return void
     */
    public function test_property21e_boundary_inputs_are_clamped_correctly(): void {
        $boundary_cases = [
            // [recent_data, historical_average, alpha, description]
            [0.0,   0.0,   0.7, 'both zero'],
            [100.0, 100.0, 0.7, 'both max'],
            [100.0, 0.0,   0.7, 'recent max, hist zero'],
            [0.0,   100.0, 0.7, 'recent zero, hist max'],
            [100.0, 100.0, 0.9, 'both max, high alpha'],
            [0.0,   0.0,   0.1, 'both zero, low alpha'],
        ];

        foreach ($boundary_cases as [$recent, $hist, $alpha, $desc]) {
            $result = $this->ps->calculate_motivation_level($recent, $hist, $alpha);

            $this->assertGreaterThanOrEqual(
                0.0,
                $result,
                "Property 21e violated ({$desc}): result={$result} is below 0.0. " .
                "Result must always be >= 0.0 (Req 13.2)."
            );

            $this->assertLessThanOrEqual(
                100.0,
                $result,
                "Property 21e violated ({$desc}): result={$result} exceeds 100.0. " .
                "Result must always be <= 100.0 (Req 13.2)."
            );
        }
    }

    /**
     * P21e (Clamping Does Not Affect Dominance Direction): Even when clamping
     * occurs, the dominance direction (which data source has more influence)
     * is preserved in the unclamped region.
     *
     * This test verifies that the formula itself is correct before clamping,
     * by using inputs that stay within [0, 100] after the weighted average.
     *
     * **Validates: Requirements 13.2**
     *
     * @return void
     */
    public function test_property21e_formula_correct_before_clamping(): void {
        $this->forAll(
            Generator\choose(10, 90),  // base in [10, 90]
            Generator\choose(1, 5)     // delta in [1, 5] — small to avoid clamping
        )->then(function (int $base_int, int $delta_int) {
            $base  = (float) $base_int;
            $delta = (float) $delta_int;

            // Verify the formula directly: new_value = recent * 0.7 + hist * 0.3
            $expected_recent_change = $delta * 0.7;
            $expected_hist_change   = $delta * 0.3;

            $baseline              = $this->ps->calculate_motivation_level($base, $base);
            $with_recent_delta     = $this->ps->calculate_motivation_level($base + $delta, $base);
            $with_historical_delta = $this->ps->calculate_motivation_level($base, $base + $delta);

            $actual_recent_change = abs($with_recent_delta - $baseline);
            $actual_hist_change   = abs($with_historical_delta - $baseline);

            $this->assertEqualsWithDelta(
                $expected_recent_change,
                $actual_recent_change,
                0.0001,
                "Property 21e formula check violated: base={$base}, delta={$delta}. " .
                "actual_recent_change={$actual_recent_change} must equal " .
                "expected_recent_change={$expected_recent_change} (= delta × 0.7). " .
                "The weighted average formula must be applied correctly (Req 13.2)."
            );

            $this->assertEqualsWithDelta(
                $expected_hist_change,
                $actual_hist_change,
                0.0001,
                "Property 21e formula check violated: base={$base}, delta={$delta}. " .
                "actual_hist_change={$actual_hist_change} must equal " .
                "expected_hist_change={$expected_hist_change} (= delta × 0.3). " .
                "The weighted average formula must be applied correctly (Req 13.2)."
            );
        });
    }
}
