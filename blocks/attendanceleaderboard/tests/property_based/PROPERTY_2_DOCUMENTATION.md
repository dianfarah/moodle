# Property 2: Konsistensi Ringkasan Sesi dengan Activity_Log

## Property Specification

**For any** sesi Learner yang berakhir, ringkasan Activity_Log yang dikirimkan ke Profiling System harus secara akurat merepresentasikan seluruh aktivitas yang tercatat dalam Activity_Log untuk sesi tersebut — tidak ada aktivitas yang hilang, ditambahkan, atau dimodifikasi.

**Validates Requirements**: 1.5

## Property Statement

```
∀ session S with activities A₁, A₂, ..., Aₙ in Activity_Log:
  flush_to_profiler(S) ⟹
    ∀ i ∈ [1, n]: activity Aᵢ is sent to Profiling System exactly once
    ∧ ∀ i ∈ [1, n]: attributes(Aᵢ) remain unchanged
    ∧ count(sent_activities) = n
    ∧ aggregate_metrics(sent_activities) = Σ individual_metrics(Aᵢ)
```

## What This Property Verifies

### 1. Completeness
- All activities recorded in Activity_Log are sent to Profiling System
- No activities are lost during the flush operation
- Empty sessions (0 activities) are handled correctly

### 2. Uniqueness
- Each activity is sent exactly once (no duplicates)
- Multiple flushes do not cause re-sending of already-sent activities
- The `sent_to_profiler` flag correctly tracks sent status

### 3. Integrity
- Activity attributes remain unchanged during flush
- userid, courseid, event_type, component, objectid, action, duration_seconds, result_value, timecreated all match exactly
- Optional fields (duration, result) are preserved correctly including null values

### 4. Mathematical Accuracy
- Total duration = sum of all individual durations
- Activity count = number of activities in Activity_Log
- Result sum = sum of all non-null result values
- Aggregate metrics are mathematically accurate

## Test Implementation

### Main Property Test

**Test**: `test_property2_session_summary_consistency()`

**Strategy**:
- Generate random number of activities (1-100)
- Generate random event types
- Record all activities in Activity_Log
- Capture state before flush
- Execute flush_to_profiler()
- Capture state after flush
- Verify all properties hold

**Generators**:
- `Generator\choose(1, 100)` - Number of activities
- `Generator\elements([...])` - Event types

**Assertions**:
1. All activities marked as not sent before flush
2. Count remains the same after flush
3. All activities marked as sent after flush
4. All attributes unchanged
5. No duplicates
6. Aggregate metrics accurate

### Edge Cases

#### 1. Empty Session
**Test**: `test_property2_empty_session_consistency()`

Verifies that flushing a session with 0 activities:
- Does not cause errors
- Does not create phantom activities
- Maintains count of 0

#### 2. Single Activity
**Test**: `test_property2_single_activity_consistency()`

Verifies that a session with exactly 1 activity:
- Is flushed correctly
- Has all attributes preserved
- Is marked as sent

#### 3. Missing Optional Fields
**Test**: `test_property2_missing_optional_fields_consistency()`

Verifies that activities with null/missing optional fields:
- duration_seconds = 0 or null
- result_value = null
- Are flushed without data corruption
- Preserve null values correctly

#### 4. Multiple Flushes (Idempotency)
**Test**: `test_property2_multiple_flush_idempotency()`

Verifies that flushing multiple times:
- Does not cause duplicate processing
- Does not change activity count
- Does not modify attributes
- Respects `sent_to_profiler` flag

#### 5. Aggregate Metrics Accuracy
**Test**: `test_property2_aggregate_metrics_accuracy()`

Verifies mathematical accuracy:
- Total duration = Σ duration_seconds
- Result count = count of non-null results
- Result sum = Σ result_value (where not null)
- Activity count = n

## Why This Property Matters

### Data Integrity
Session summaries are the primary input to the Profiling System. If summaries are incomplete or inaccurate, the entire adaptive learning system fails because:
- Learner profiles will be based on incomplete data
- Motivation levels will be calculated incorrectly
- Coach recommendations will be inappropriate

### Requirement Validation
This property directly validates Requirement 1.5:
> "WHEN sesi Learner berakhir (logout atau timeout), THE Tracking_System SHALL mencatat waktu akhir sesi dan mengirimkan ringkasan Activity_Log ke Profiling_System."

### System Reliability
Ensures that:
- No data loss occurs during session end
- Profiling System receives complete information
- Aggregate metrics used for analytics are accurate

## Test Execution

### Run Property 2 Tests Only
```bash
cd blocks/attendanceleaderboard
vendor/bin/phpunit --filter test_property2 tests/property_based/tracking_properties_test.php
```

### Run with Verbose Output
```bash
vendor/bin/phpunit --verbose --filter test_property2 tests/property_based/tracking_properties_test.php
```

### Run with Custom Iterations
```bash
ERIS_ITERATIONS=200 vendor/bin/phpunit --filter test_property2 tests/property_based/tracking_properties_test.php
```

## Expected Test Output

### Success
```
PHPUnit 9.x.x by Sebastian Bergmann and contributors.

.....                                                               5 / 5 (100%)

Time: 00:15.234, Memory: 128.00 MB

OK (5 tests, 450 assertions)
```

### Failure Example
```
Failed asserting that 98 matches expected 100.
Property 2 violated: Expected 100 activities to be recorded, but found 98.
All activities in an active session must be recorded.

Reproduced with seed: 1234567890
Minimal failing input: [100, "course_module_viewed"]
```

## Debugging Failed Tests

### If Count Mismatch
1. Check TrackingSystem::record_activity() for data loss
2. Verify database transaction handling
3. Check for race conditions in concurrent recording

### If Attributes Changed
1. Check flush_to_profiler() for unintended modifications
2. Verify ProfilingSystem::process_activity_log() doesn't mutate input
3. Check database field types and conversions

### If Duplicates Found
1. Check sent_to_profiler flag logic
2. Verify get_pending_logs() query
3. Check for transaction rollback issues

### If Aggregate Metrics Wrong
1. Verify duration_seconds field type (int)
2. Check result_value field type (decimal)
3. Verify null handling in calculations

## Related Properties

- **Property 1**: Completeness of Activity Recording - ensures activities are recorded in the first place
- **Property 3**: Accuracy of Engagement Metrics - uses the same aggregate data
- **Property 4**: Data Resilience During Disconnection - tests retry mechanism for failed flushes

## Implementation Notes

### Key Methods Tested
- `TrackingSystem::flush_to_profiler()`
- `TrackingSystem::get_pending_logs()`
- `ProfilingSystem::process_activity_log()`

### Database Tables
- `acmls_activity_log` - stores all activities
  - `sent_to_profiler` flag (0 = pending, 1 = sent)

### Critical Invariants
1. `sent_to_profiler` flag is atomic (no partial updates)
2. Activity attributes are immutable after creation
3. Flush operation is idempotent
4. Aggregate metrics are derived, not stored

## Test Coverage

### Scenarios Covered
- ✅ Random number of activities (1-100)
- ✅ Multiple event types
- ✅ Empty sessions (0 activities)
- ✅ Single activity sessions
- ✅ Activities with missing optional fields
- ✅ Multiple flush operations
- ✅ Aggregate metric calculations
- ✅ Attribute preservation
- ✅ Duplicate prevention

### Scenarios NOT Covered (Future Work)
- ⚠️ Concurrent flushes from multiple sessions
- ⚠️ Database transaction failures
- ⚠️ ProfilingSystem unavailable (covered by Property 4)
- ⚠️ Very large sessions (>1000 activities)

## Maintenance

### When to Update This Test
- When Activity_Log schema changes
- When flush_to_profiler() logic changes
- When new aggregate metrics are added
- When sent_to_profiler flag behavior changes

### Test Stability
- **Deterministic**: Yes (with fixed seed)
- **Flaky**: No
- **Performance**: ~15 seconds for 100 iterations
- **Dependencies**: Requires Moodle database

## References

- Design Document: Section B.2 (Tracking System)
- Requirements Document: Requirement 1.5
- Property 1 Documentation: PROPERTY_1_DOCUMENTATION.md
- Eris Documentation: https://github.com/giorgiosironi/eris

---

**Status**: ✅ IMPLEMENTED
**Date**: 2024
**Author**: ACMLS Project
**Test File**: `tracking_properties_test.php`
