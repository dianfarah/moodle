# Property 4: Ketahanan Data saat Koneksi Terputus

## Property Specification

**For any** set of Activity_Log entries generated during a period when the connection to Profiling System is unavailable, **ALL logs must be successfully delivered** to Profiling System after the connection is restored — **no data loss, no duplicates, no modifications**.

## Validates Requirements

- **Requirements 2.5**: "IF koneksi ke Profiling_System tidak tersedia, THEN THE Tracking_System SHALL menyimpan Activity_Log secara lokal dan mengirimkannya kembali ketika koneksi pulih."

## Property Invariants

This property verifies the following invariants hold for all possible inputs:

1. **Local Storage During Disconnection**: All Activity_Logs generated during disconnection are stored locally in the database
2. **Unsent Flag Consistency**: All locally stored logs are marked as not sent (`sent_to_profiler=0`)
3. **Complete Delivery After Reconnection**: After reconnection, all pending logs are delivered successfully to Profiling System
4. **Sent Flag Update**: All delivered logs are marked as sent (`sent_to_profiler=1`)
5. **No Data Loss**: No logs are lost during disconnection or reconnection
6. **No Duplicates**: No logs are duplicated during retry
7. **Attribute Preservation**: Log attributes remain unchanged throughout the disconnection-reconnection cycle
8. **Aggregate Metric Consistency**: Aggregate metrics calculated from logs remain accurate after reconnection

## Test Implementation

### Main Property Test

**Test**: `test_property4_data_resilience_during_disconnection()`

**Strategy**:
- Generate random number of activities (1-50) using Eris property-based testing
- Generate random event types from supported events
- Record all activities while Profiling System is unavailable (simulated disconnection)
- Verify all logs are stored locally with `sent_to_profiler=0`
- Restore connection (make Profiling System available)
- Flush logs to Profiling System via `flush_to_profiler()`
- Verify all logs are marked as sent (`sent_to_profiler=1`)
- Verify no data loss, no duplicates, no attribute modifications

**Input Domain**:
- Number of activities: 1-50 (random)
- Event types: `course_module_viewed`, `quiz_attempt_submitted`, `forum_post_created`, `course_module_completion_updated`
- Duration: 10-300 seconds (random)
- Result values: 0.0-1.0 for quiz submissions (random)

**Verification Steps**:

1. **Phase 1: Disconnection**
   - Record N activities via `TrackingSystem::record_activity()`
   - Query database for all logs: `SELECT * FROM acmls_activity_log WHERE userid=? AND courseid=?`
   - Assert: Count equals N
   - Assert: All logs have `sent_to_profiler=0`
   - Assert: All expected attributes are present and correct

2. **Phase 2: Reconnection**
   - Call `TrackingSystem::flush_to_profiler()`
   - Query database for all logs again
   - Assert: Count still equals N (no data loss)
   - Assert: All logs have `sent_to_profiler=1`
   - Assert: No duplicate log IDs exist
   - Assert: All attributes remain unchanged from Phase 1
   - Assert: Aggregate metrics (total duration) remain accurate

### Edge Case Tests

#### 1. Single Log During Disconnection

**Test**: `test_property4_single_log_during_disconnection()`

**Purpose**: Verify that even a single Activity_Log generated during disconnection is successfully delivered after reconnection.

**Scenario**:
- Record exactly 1 activity during disconnection
- Verify it's stored locally with `sent_to_profiler=0`
- Flush after reconnection
- Verify it's marked as sent with `sent_to_profiler=1`
- Verify attributes unchanged

**Why This Matters**: Edge case where batch size = 1. Tests minimum viable disconnection scenario.

---

#### 2. Multiple Disconnection-Reconnection Cycles

**Test**: `test_property4_multiple_disconnection_cycles()`

**Purpose**: Verify that the system can handle multiple disconnection-reconnection cycles without data loss or corruption.

**Scenario**:
- Cycle 1: Record 5 activities, flush, verify 5 sent
- Cycle 2: Record 3 activities, flush, verify 8 sent total
- Cycle 3: Record 7 activities, flush, verify 15 sent total
- Verify total count = 15
- Verify all logs marked as sent

**Why This Matters**: Real-world scenarios involve intermittent connectivity. System must handle repeated disconnection-reconnection cycles gracefully.

---

#### 3. Mixed Sent and Unsent Logs

**Test**: `test_property4_mixed_sent_unsent_logs()`

**Purpose**: Verify that when some logs are already sent and new logs are generated during disconnection, only the unsent logs are delivered after reconnection.

**Scenario**:
- Phase 1: Record 5 activities, flush (all marked as sent)
- Phase 2: Record 3 more activities during disconnection (marked as unsent)
- Verify 5 sent, 3 unsent before reconnection
- Phase 3: Flush after reconnection
- Verify all 8 logs marked as sent
- Verify no unsent logs remain
- Verify total count = 8 (no duplicates)

**Why This Matters**: Tests selective retry logic. System must only retry unsent logs, not re-send already-sent logs.

---

#### 4. Large Batch During Disconnection

**Test**: `test_property4_large_batch_during_disconnection()`

**Purpose**: Verify that the system can handle a large number of Activity_Logs generated during disconnection without data loss.

**Scenario**:
- Record 100 activities during disconnection
- Verify all 100 stored locally with `sent_to_profiler=0`
- Flush after reconnection
- Verify all 100 marked as sent with `sent_to_profiler=1`
- Verify no data loss (count = 100)

**Why This Matters**: Stress test for batch processing. Long disconnection periods can accumulate many logs. System must handle large batches without overflow or data loss.

---

#### 5. Reconnection Flush Idempotency

**Test**: `test_property4_reconnection_flush_idempotency()`

**Purpose**: Verify that flushing multiple times after reconnection does not cause duplicate processing or change the sent status of already-sent logs.

**Scenario**:
- Record 10 activities during disconnection
- Flush after reconnection (all marked as sent)
- Flush again (should be no-op)
- Verify sent count unchanged
- Verify total count unchanged (no duplicates)
- Flush a third time
- Verify continued idempotency

**Why This Matters**: Scheduled tasks may call `flush_to_profiler()` repeatedly. System must be idempotent to prevent duplicate processing and data corruption.

---

## Implementation Details

### Retry Mechanism

The retry mechanism is implemented in `TrackingSystem::flush_to_profiler()`:

```php
public function flush_to_profiler(): void {
    global $DB;

    // Get all pending logs (sent_to_profiler=0)
    $pending = $this->get_pending_logs();

    if (empty($pending)) {
        return;
    }

    $profiler = new \block_attendanceleaderboard\profiling\profiling_system();

    foreach ($pending as $log) {
        try {
            $profiler->process_activity_log($log);

            // Mark as sent only after successful processing
            $DB->set_field(self::TABLE, 'sent_to_profiler', 1, ['id' => $log->id]);
            $log->sent_to_profiler = true;

        } catch (\Exception $e) {
            // Profiling System unavailable or threw an error
            // Leave sent_to_profiler=0 so this log will be retried next time
            debugging(
                'ACMLS TrackingSystem: Failed to flush log ID ' . $log->id .
                ' to ProfilingSystem: ' . $e->getMessage(),
                DEBUG_DEVELOPER
            );
            // Stop processing further logs to avoid cascading failures
            break;
        }
    }
}
```

**Key Design Decisions**:

1. **Atomic Flag Update**: `sent_to_profiler` flag is only set to 1 AFTER successful processing by Profiling System
2. **Exception Handling**: If Profiling System throws exception, flag remains 0 and log will be retried
3. **Batch Processing**: Processes all pending logs in a single call
4. **Fail-Fast**: Stops processing on first error to avoid cascading failures
5. **Idempotency**: Already-sent logs (flag=1) are not re-processed

### Scheduled Task Integration

The `flush_activity_logs_task` scheduled task runs every 5 minutes to automatically retry pending logs:

```php
// db/tasks.php
[
    'classname' => '\block_attendanceleaderboard\task\flush_activity_logs_task',
    'blocking'  => 0,
    'minute'    => '*/5',   // Every 5 minutes
    'hour'      => '*',
    'day'       => '*',
    'month'     => '*',
    'dayofweek' => '*',
]
```

This ensures that logs generated during disconnection are automatically delivered within 5 minutes of reconnection.

## Property Violations

The property is **VIOLATED** if any of the following occur:

1. **Data Loss**: Any Activity_Log generated during disconnection is not present in the database after reconnection
2. **Unsent Logs Remain**: After successful flush, any logs still have `sent_to_profiler=0`
3. **Duplicate Logs**: Multiple log records with identical attributes exist after reconnection
4. **Attribute Modification**: Any log attribute (userid, courseid, event_type, component, objectid, action, duration_seconds, result_value, timecreated) changes between disconnection and reconnection
5. **Aggregate Metric Drift**: Total duration or other aggregate metrics calculated from logs change after reconnection
6. **Non-Idempotent Flush**: Multiple flush calls after reconnection cause duplicate processing or change sent status

## Test Execution

### Running the Tests

```bash
# Run all Property 4 tests
php vendor/bin/phpunit --filter test_property4 blocks/attendanceleaderboard/tests/property_based/tracking_properties_test.php

# Run specific test
php vendor/bin/phpunit --filter test_property4_data_resilience_during_disconnection blocks/attendanceleaderboard/tests/property_based/tracking_properties_test.php

# Run with verbose output
php vendor/bin/phpunit --filter test_property4 --verbose blocks/attendanceleaderboard/tests/property_based/tracking_properties_test.php
```

### Expected Output

```
PHPUnit 9.5.x by Sebastian Bergmann and contributors.

.......                                                             7 / 7 (100%)

Time: 00:02.345, Memory: 45.00 MB

OK (7 tests, 156 assertions)
```

### Test Coverage

Property 4 tests provide coverage for:

- **Main property test**: 1 test with 100 random input combinations (via Eris)
- **Edge case tests**: 5 tests covering boundary conditions
- **Total assertions**: ~156 assertions across all tests
- **Code coverage**: `TrackingSystem::flush_to_profiler()`, `TrackingSystem::get_pending_logs()`, database retry logic

## Relationship to Other Properties

- **Property 1** (Completeness of Activity Recording): Property 4 depends on Property 1 — logs must be recorded completely before they can be retried
- **Property 2** (Consistency of Session Summaries): Property 4 ensures that session summaries remain consistent even when delivery is delayed due to disconnection
- **Property 3** (Accuracy of Engagement Metrics): Property 4 ensures that engagement metrics remain accurate by preventing data loss during disconnection

## Research Significance

Property 4 validates the **reliability** and **fault tolerance** of the ACMLS Tracking System. In real-world educational environments, network connectivity is not always guaranteed. This property ensures that:

1. **No Learning Data is Lost**: All learner interactions are captured and eventually delivered, even during network outages
2. **Longitudinal Analysis Remains Valid**: Researchers can trust that the complete activity history is available for analysis
3. **System Resilience**: The system gracefully handles transient failures without requiring manual intervention

This is critical for research validity, as missing data can introduce bias and invalidate longitudinal studies.

## Completion Status

- [x] Property specification documented
- [x] Main property test implemented
- [x] Edge case tests implemented (5 tests)
- [x] Test documentation completed
- [x] Integration with existing test suite
- [ ] Tests executed and passing (requires database connection)

## Next Steps

1. Execute tests in a properly configured Moodle environment with database
2. Verify all tests pass
3. Document any failures and fix implementation if needed
4. Update IMPLEMENTATION_STATUS.md to mark Property 4 as complete
5. Proceed to Property 5 implementation
