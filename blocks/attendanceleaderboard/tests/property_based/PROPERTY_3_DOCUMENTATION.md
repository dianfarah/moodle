# Property 3: Akurasi Kalkulasi Metrik Engagement

## Property Specification

**For any** kumpulan Activity_Log Learner dalam periode tertentu, kalkulasi metrik engagement (frekuensi login, jumlah resource yang diakses, total durasi sesi) harus secara matematis akurat dan konsisten dengan data mentah di Activity_Log.

**Validates: Requirements 2.3**

## Implementation Status

✅ **IMPLEMENTED** in `tracking_properties_test.php`

## Test Strategy

The property test verifies that engagement metrics calculated from Activity_Log data are mathematically accurate by:

1. **Generating random activity data**: Creates 1-100 activities with random event types and durations
2. **Tracking expected metrics**: Manually calculates expected login count, resource access count, and total duration
3. **Recording activities**: Uses TrackingSystem to record all activities to Activity_Log
4. **Calculating actual metrics**: Queries Activity_Log and calculates actual metrics
5. **Verifying mathematical accuracy**: Asserts that actual metrics match expected metrics exactly

## Test Coverage

### Main Property Test: `test_property3_engagement_metrics_accuracy()`

Verifies that for any random set of activities:
- Login frequency count matches the number of login events
- Resource access count matches the number of resource access events  
- Total session duration equals the sum of all duration_seconds values
- Aggregate metrics (average duration) are calculated correctly
- No activities are double-counted or omitted

**Generators Used:**
- `Generator\choose(1, 100)` - Number of activities (1-100)
- `Generator\choose(1, 10)` - Number of login events (1-10)
- Random durations: 0-60 seconds for logins, 10-600 seconds for other activities
- Random event types: login, resource view, quiz submission, forum post, completion

**Assertions:**
1. Total activity count matches expected count
2. Login count is mathematically accurate
3. Resource access count is mathematically accurate
4. Total duration is mathematically accurate (no rounding errors)
5. No duplicate activities exist
6. Average duration calculation is consistent

### Edge Case Tests

#### 1. `test_property3_zero_duration_activities()`
**Purpose**: Verifies that activities with zero duration are counted correctly

**Test Data**: 10 activities with duration_seconds = 0

**Assertions**:
- All zero-duration activities are recorded
- Total duration is exactly 0
- Resource access count is still accurate

#### 2. `test_property3_very_long_duration_activities()`
**Purpose**: Verifies that very long durations (multi-hour sessions) are calculated correctly without overflow

**Test Data**: 10 activities with durations from 1-10 hours (3600-36000 seconds)

**Assertions**:
- Total duration calculation is accurate for large values
- No integer overflow or precision loss

#### 3. `test_property3_mixed_event_types_accuracy()`
**Purpose**: Verifies that engagement metrics are accurate with diverse event types

**Test Data**:
- 3 login events (30 seconds each)
- 5 resource views (120 seconds each)
- 2 quiz submissions (300 seconds each)
- 4 forum posts (180 seconds each)

**Assertions**:
- Each event type count is accurate
- Total duration matches sum of all individual durations
- No cross-contamination between event types

#### 4. `test_property3_multi_user_independent_metrics()`
**Purpose**: Verifies that metrics for different users are calculated independently

**Test Data**: 2-5 users, each with 5-20 activities

**Assertions**:
- Each user's login count is independent and accurate
- Each user's resource count is independent and accurate
- Each user's total duration is independent and accurate
- No cross-user contamination

#### 5. `test_property3_single_activity_per_type()`
**Purpose**: Verifies accuracy with minimal data (boundary case)

**Test Data**: Exactly 1 activity of each type (login, resource view, quiz)

**Assertions**:
- Login count = 1
- Resource count = 1
- Total duration = 300 seconds (100 + 200)

## Mathematical Properties Verified

### 1. Additivity
```
total_duration = Σ(duration_seconds) for all activities
```

### 2. Counting Accuracy
```
login_count = COUNT(activities WHERE event_type = 'user_loggedin')
resource_count = COUNT(activities WHERE event_type = 'course_module_viewed')
```

### 3. Average Calculation
```
avg_duration = total_duration / activity_count
```

### 4. Independence
```
For users U1, U2:
  metrics(U1) ∩ metrics(U2) = ∅
```

## Property Violations Detected

The test will fail if:

1. **Counting errors**: Login or resource access counts don't match Activity_Log
2. **Duration calculation errors**: Total duration doesn't equal sum of individual durations
3. **Double-counting**: Activities are counted multiple times
4. **Omissions**: Activities are missing from calculations
5. **Cross-user contamination**: One user's activities affect another user's metrics
6. **Rounding errors**: Aggregate calculations lose precision
7. **Overflow errors**: Very large durations cause calculation errors

## Example Failure Messages

```
Property 3 violated: Login count mismatch. Expected 5 logins, got 4. 
Engagement metrics must accurately reflect raw Activity_Log data.

Property 3 violated: Total duration mismatch. Expected 1500 seconds, got 1450 seconds. 
Engagement metrics must be consistent with raw Activity_Log data.

Property 3 violated: User 42 total duration mismatch. Expected 800, got 1200. 
Metrics must be calculated independently per user.
```

## Integration with ACMLS Components

This property ensures:

1. **Profiling System**: Receives accurate engagement data for profile updates
2. **Leaderboard**: Calculates scores based on accurate engagement metrics
3. **Coach**: Makes decisions based on reliable engagement indicators
4. **Analytics**: Provides accurate longitudinal data for research

## Running the Tests

```bash
# Run all Property 3 tests
vendor/bin/phpunit --filter test_property3 blocks/attendanceleaderboard/tests/property_based/tracking_properties_test.php

# Run with verbose output
vendor/bin/phpunit --verbose --filter test_property3 blocks/attendanceleaderboard/tests/property_based/tracking_properties_test.php

# Run with specific number of iterations (default: 100)
ERIS_ITERATIONS=200 vendor/bin/phpunit --filter test_property3 blocks/attendanceleaderboard/tests/property_based/tracking_properties_test.php
```

## Test Execution Time

- Main property test: ~30-60 seconds (100 iterations)
- Edge case tests: ~5-10 seconds each
- Total: ~1-2 minutes for all Property 3 tests

## Dependencies

- **Eris library**: Property-based testing framework
- **Moodle test database**: For Activity_Log storage
- **TrackingSystem class**: For recording activities
- **Moodle data generators**: For creating test users and courses

## Related Properties

- **Property 1**: Ensures all activities are recorded (prerequisite)
- **Property 2**: Ensures session summaries are consistent (related)
- **Property 11**: Verifies aggregate performance metrics (similar pattern)
- **Property 18**: Verifies leaderboard score calculations (uses engagement metrics)

## Implementation Notes

1. **Duration tracking**: The test assumes duration_seconds is stored as an integer in Activity_Log
2. **Event type filtering**: Uses exact string matching for event types
3. **Floating point precision**: Uses `assertEqualsWithDelta` with 0.01 tolerance for average calculations
4. **Test isolation**: Each test creates its own users and courses to avoid interference

## Future Enhancements

Potential additions to Property 3 testing:

1. Test with concurrent activity recording (multi-threaded)
2. Test with database transaction rollbacks
3. Test with very large datasets (1000+ activities)
4. Test with time-based filtering (activities in specific date ranges)
5. Test with course-specific filtering (multiple courses per user)

## Conclusion

Property 3 ensures that the foundation of ACMLS's adaptive system - accurate engagement metrics - is mathematically sound and reliable. This property is critical because all downstream components (Profiling, Coach, Leaderboard) depend on accurate engagement data to function correctly.
