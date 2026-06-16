# Property 7: Kelengkapan Penyimpanan Riwayat Profil

## Property Statement

**For any sequence of profile updates, ALL profile versions MUST be stored in `acmls_learner_record` and MUST be retrievable in correct chronological order.**

## Formal Specification

```
∀ sequence of updates U = [u₁, u₂, ..., uₙ] to profile P:
  1. |stored_snapshots(P)| = n
  2. ∀i ∈ [1, n]: snapshot_i exists in acmls_learner_record
  3. ∀i, j where i < j: timestamp(snapshot_i) ≤ timestamp(snapshot_j)
  4. ∀i, j where i < j: version(snapshot_i) < version(snapshot_j)
  5. ∀i ∈ [1, n]: snapshot_i contains complete profile data
  6. No gaps in version sequence: versions = [v, v+1, v+2, ..., v+n-1]
```

## Requirements Addressed

- **Requirement 3.5**: Store all historical profile versions in Learner_Record
- **Requirement 10.2**: Support query of longitudinal data in chronological order
- **Requirement 10.3**: Store data with accurate timestamp and metadata

## Why This Property Matters

### Educational Significance

1. **Longitudinal Analysis**: Researchers need complete historical data to study learning trajectories over time
2. **Intervention Effectiveness**: Evaluating the impact of adaptive interventions requires before/after profile comparisons
3. **Learning Pattern Discovery**: Identifying patterns in learner development requires complete, ordered historical data
4. **Audit Trail**: Complete history provides accountability and transparency for adaptive decisions

### System Integrity

1. **Data Completeness**: No profile updates should be lost or missing from the historical record
2. **Temporal Consistency**: Chronological order is essential for time-series analysis
3. **Version Tracking**: Sequential version numbers enable precise identification of profile states
4. **Reproducibility**: Complete history allows reconstruction of system state at any point in time

## Test Implementation

### Main Property Test

**Test**: `test_property7_profile_history_completeness_and_order()`

**Strategy**: Property-based test with random number of sequential updates (2-20)

**Verification Points**:

1. **Completeness**: Number of stored snapshots equals number of updates performed
2. **Chronological Order**: Timestamps are monotonically increasing
3. **Version Sequence**: Profile versions are strictly increasing with no gaps
4. **Data Integrity**: Each snapshot contains all required profile fields
5. **Correct Association**: All snapshots belong to the correct user and course
6. **Database Consistency**: Database records match retrieved history

### Edge Cases

#### 1. Single Update (`test_property7_single_update_creates_snapshot`)
- **Scenario**: Perform exactly one profile update
- **Verification**: Exactly one snapshot exists with correct data
- **Purpose**: Verify base case works correctly

#### 2. Identical Updates (`test_property7_identical_updates_create_separate_snapshots`)
- **Scenario**: Multiple updates with identical data
- **Verification**: Each update creates a separate snapshot with unique version and timestamp
- **Purpose**: Ensure updates are tracked even when data doesn't change

#### 3. User Isolation (`test_property7_user_isolation_in_history`)
- **Scenario**: Updates for multiple users in the same course
- **Verification**: Each user's history contains only their own snapshots
- **Purpose**: Verify data isolation between users

#### 4. Course Isolation (`test_property7_course_isolation_in_history`)
- **Scenario**: Same user in multiple courses
- **Verification**: Each course's history is correctly isolated
- **Purpose**: Verify data isolation between courses

#### 5. Idempotent Retrieval (`test_property7_history_retrieval_is_idempotent`)
- **Scenario**: Retrieve history multiple times
- **Verification**: All retrievals return identical results
- **Purpose**: Ensure retrieval doesn't modify data

#### 6. Large Volume (`test_property7_large_number_of_updates`)
- **Scenario**: 50+ rapid sequential updates
- **Verification**: All updates stored with correct ordering
- **Purpose**: Stress test for performance and data integrity

## Implementation Details

### Storage Mechanism

Profile snapshots are stored in `acmls_learner_record` table:

```php
$record = new \stdClass();
$record->userid           = $profile->userid;
$record->courseid         = $profile->courseid;
$record->record_type      = 'profile_snapshot';
$record->source_component = 'profiling';
$record->data_payload     = json_encode($profile->get_snapshot());
$record->profile_version  = $profile->profile_version;
$record->timecreated      = time();
```

### Retrieval Mechanism

History is retrieved via `get_profile_history()`:

```php
$records = $DB->get_records(
    'acmls_learner_record',
    [
        'userid'           => $userid,
        'courseid'         => $courseid,
        'record_type'      => 'profile_snapshot',
        'source_component' => 'profiling',
    ],
    'timecreated ASC, id ASC'  // Chronological ordering
);
```

### Data Structure

Each snapshot contains:
- `userid`: Learner identifier
- `courseid`: Course identifier
- `cognitive_level`: Cognitive ability level (1-3)
- `motivation_level`: Motivation score (0-100)
- `performance_category`: Performance classification (1-3)
- `learning_style`: Preferred learning style
- `behavioral_score`: Composite behavioral metric (0-100)
- `engagement_score`: Engagement metric (0-100)
- `profile_version`: Sequential version number
- `last_updated`: Timestamp of profile update
- `_timecreated`: Timestamp of snapshot creation (added by retrieval)
- `_record_id`: Database record ID (added by retrieval)

## Potential Violations

### How Property 7 Can Be Violated

1. **Missing Snapshots**: Update doesn't call `save_profile_snapshot()`
2. **Incorrect Ordering**: Database query doesn't sort by `timecreated ASC`
3. **Version Gaps**: Version number not incremented correctly
4. **Incomplete Data**: Snapshot doesn't include all profile fields
5. **Wrong Association**: Snapshot stored with incorrect userid/courseid
6. **Timestamp Issues**: Timestamps not monotonically increasing

### Detection

Property violations are detected through:
- Count mismatch between updates and snapshots
- Non-monotonic timestamps or versions
- Missing required fields in snapshots
- Gaps in version sequence
- Incorrect user/course associations

## Performance Considerations

### Storage Overhead

- Each update creates one database record in `acmls_learner_record`
- JSON encoding of profile data (~500 bytes per snapshot)
- For active learner with 100 updates: ~50KB storage

### Retrieval Performance

- Query uses indexed fields (`userid`, `courseid`, `record_type`)
- Sorting by `timecreated` is efficient with proper indexing
- Expected retrieval time: <100ms for 1000 snapshots

### Optimization Strategies

1. **Archival**: Move old snapshots to archive table after 1 year
2. **Compression**: Compress JSON payload for long-term storage
3. **Sampling**: For very active learners, consider snapshot sampling
4. **Indexing**: Ensure proper database indexes on query fields

## Related Properties

- **Property 5**: Ensures profile dimensions are updated (generates data to store)
- **Property 6**: Ensures correct classification (affects stored data quality)
- **Property 10**: Ensures Coach decisions are logged (parallel storage requirement)

## Testing Guidelines

### Running Property 7 Tests

```bash
# Run all Property 7 tests
php vendor/bin/phpunit --filter test_property7 \
  blocks/attendanceleaderboard/tests/property_based/profiling_properties_test.php

# Run specific edge case
php vendor/bin/phpunit --filter test_property7_single_update \
  blocks/attendanceleaderboard/tests/property_based/profiling_properties_test.php
```

### Expected Test Duration

- Main property test: ~30-60 seconds (property-based with multiple iterations)
- Edge case tests: ~5-10 seconds each
- Total suite: ~2-3 minutes

### Test Data Cleanup

All tests use `$this->resetAfterTest(true)` to ensure:
- Database is rolled back after each test
- No test data persists between tests
- Tests can run in any order

## Maintenance Notes

### When to Update Tests

Update Property 7 tests when:
1. Profile structure changes (add/remove fields)
2. Storage mechanism changes (different table, format)
3. Retrieval logic changes (different ordering, filtering)
4. Version numbering scheme changes

### Backward Compatibility

If profile structure changes:
1. Ensure old snapshots remain readable
2. Add migration for historical data if needed
3. Update tests to handle both old and new formats

## References

- Design Document: Section B.3 (Profiling System)
- Requirements Document: Requirements 3.5, 10.2, 10.3
- Database Schema: `acmls_learner_record` table definition
- Implementation: `profiling_system.php` - `save_profile_snapshot()` and `get_profile_history()`
