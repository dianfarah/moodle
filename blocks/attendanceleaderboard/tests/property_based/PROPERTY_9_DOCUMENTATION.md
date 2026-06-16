# Property 9: Kesesuaian Tingkat Kesulitan Rekomendasi

## Property Specification

**Formal Statement**: For ANY Learner_Profile with a given Performance_Category, ALL recommended resources MUST have difficulty_level values that are appropriate for that Performance_Category.

**Mapping Rules**:
- Performance_Category = 1 (Low) → difficulty_level MUST be 1
- Performance_Category = 2 (Middle) → difficulty_level MUST be 1 OR 2
- Performance_Category = 3 (High) → difficulty_level MUST be 2 OR 3

## Rationale

This property ensures that the Coach component never recommends resources that are too difficult or too easy for a learner's current performance level. This is critical for:

1. **Pedagogical Effectiveness**: Resources that are too difficult can frustrate learners and reduce motivation. Resources that are too easy waste time and don't promote growth.

2. **Zone of Proximal Development**: The mapping implements Vygotsky's concept of the ZPD by ensuring learners receive resources slightly above (but not far beyond) their current level.

3. **System Correctness**: This property verifies that the Coach's rule-based engine correctly implements the performance-to-difficulty mapping defined in the design specification.

4. **Data Integrity**: Ensures that the recommendation pipeline (Coach → Learning Resource Repository → Delivery System) maintains difficulty appropriateness throughout.

## Test Implementation

### File Location
`blocks/attendanceleaderboard/tests/property_based/coach_properties_test.php`

### Test Methods

#### 1. Main Property Test: `test_property9_resource_difficulty_appropriateness()`
- **Strategy**: Property-based testing with random inputs
- **Generators**:
  - Performance_Category: 1, 2, 3
  - Motivation_Level: 0-100
  - Learning_Style: visual, auditory, reading, kinesthetic, unknown
- **Test Data**: Creates 9 resources (3 per difficulty level)
- **Verification**:
  - ALL recommended resources have appropriate difficulty_level
  - Mapping is consistent with specification
  - Database state matches returned results

#### 2. Edge Case: Low Performance (`test_property9_low_performance_only_basic_difficulty()`)
- **Scenario**: Performance_Category = 1
- **Expected**: ONLY difficulty_level = 1 resources
- **Verification**: No difficulty 2 or 3 resources in results

#### 3. Edge Case: Middle Performance (`test_property9_middle_performance_basic_and_intermediate()`)
- **Scenario**: Performance_Category = 2
- **Expected**: ONLY difficulty_level = 1 OR 2 resources
- **Verification**: No difficulty 3 resources in results

#### 4. Edge Case: High Performance (`test_property9_high_performance_intermediate_and_advanced()`)
- **Scenario**: Performance_Category = 3
- **Expected**: ONLY difficulty_level = 2 OR 3 resources
- **Verification**: No difficulty 1 resources in results

#### 5. Edge Case: Multiple Recommendations (`test_property9_multiple_recommendations_all_appropriate()`)
- **Scenario**: Large resource pool (30 resources)
- **Expected**: ALL resources in recommendation set are appropriate
- **Verification**: No mixed inappropriate resources

#### 6. Edge Case: Empty Resource Pool (`test_property9_empty_pool_returns_empty_array()`)
- **Scenario**: No resources with appropriate difficulty exist
- **Expected**: Empty array (not error, not inappropriate resources)
- **Verification**: System handles missing resources gracefully

#### 7. Invariant: Consistency (`test_property9_consistency_across_invocations()`)
- **Scenario**: Multiple calls with same profile
- **Expected**: Consistent difficulty appropriateness across all calls
- **Verification**: Deterministic behavior

#### 8. Invariant: Independence (`test_property9_independence_from_other_dimensions()`)
- **Scenario**: Varying motivation_level and learning_style
- **Expected**: Difficulty appropriateness depends ONLY on Performance_Category
- **Verification**: Other dimensions don't affect difficulty mapping

## Requirements Validation

This property test validates:

- **Requirement 4.2**: "THE Coach SHALL recommend Learning_Resource berdasarkan kombinasi: Cognitive_Level, Performance_Category, Learning_Style, dan Behavioral_History Learner."
  - Verified: Performance_Category correctly determines difficulty_level

- **Requirement 4.3**: "WHEN Performance_Category Learner adalah *Low*, THE Coach SHALL memprioritaskan rekomendasi Learning_Resource dengan tingkat kesulitan dasar..."
  - Verified: Low performers receive only difficulty=1 resources

- **Requirement 4.4**: "WHEN Performance_Category Learner adalah *High*, THE Coach SHALL merekomendasikan Learning_Resource dengan tingkat kesulitan lanjutan..."
  - Verified: High performers receive only difficulty=2 or 3 resources

## Design Validation

This property test validates:

- **Design Section B.4 (Coach)**: "Coach mengimplementasikan *pedagogical knowledge* dalam bentuk aturan yang dapat diaudit dan dimodifikasi."
  - Verified: The difficulty mapping rule is correctly implemented

- **Design Section E.4 (Learning Resource Repository)**: Resource query filtering by difficulty_level
  - Verified: Query returns only appropriate difficulty levels

- **Coach.map_performance_to_difficulty()**: Private method implementation
  - Verified: Mapping logic is correct for all categories

## Test Execution

### Run Property 9 Tests Only
```bash
cd blocks/attendanceleaderboard
vendor/bin/phpunit --filter test_property9 tests/property_based/coach_properties_test.php
```

### Run All Coach Property Tests
```bash
vendor/bin/phpunit tests/property_based/coach_properties_test.php
```

### Run with Verbose Output
```bash
vendor/bin/phpunit --verbose --filter test_property9 tests/property_based/coach_properties_test.php
```

### Run with More Iterations (Stress Test)
```bash
ERIS_ITERATIONS=500 vendor/bin/phpunit --filter test_property9 tests/property_based/coach_properties_test.php
```

## Expected Results

### Success Output
```
PHPUnit 9.x.x by Sebastian Bergmann and contributors.

........                                                            8 / 8 (100%)

Time: 00:15.234, Memory: 45.00 MB

OK (8 tests, 150 assertions)
```

### Failure Example
If the property is violated, Eris will show:
```
Failed asserting that 3 is contained in [1].
Property 9 violated: Resource 'Advanced Resource' (ID=123) has difficulty_level=3, 
which is NOT appropriate for Performance_Category=1. 
Allowed difficulties: [1].
```

## Coverage

This property test provides:

- **Input Space Coverage**: All Performance_Category values (1, 2, 3)
- **Boundary Coverage**: All difficulty level boundaries
- **Edge Case Coverage**: Empty pools, multiple recommendations, consistency
- **Invariant Coverage**: Independence from other dimensions, determinism
- **Integration Coverage**: Coach → Learning Resource Repository → Database

## Maintenance Notes

### When to Update This Test

1. **Mapping Rules Change**: If the performance-to-difficulty mapping is modified in the design
2. **New Difficulty Levels**: If difficulty_level range expands beyond 1-3
3. **New Performance Categories**: If Performance_Category range changes
4. **Coach Logic Changes**: If Coach.recommend_resources() or Coach.map_performance_to_difficulty() are modified

### Related Tests

- **Unit Test**: `blocks/attendanceleaderboard/tests/coach_test.php::test_recommend_resources()`
- **Property Test**: Property 10 (Coach Decision Recording Completeness)
- **Integration Test**: End-to-end recommendation flow

## References

- **Requirements Document**: Section "Persyaratan 4: Mesin Rekomendasi Adaptif (Coach)"
- **Design Document**: Section "B.4. Coach (Rule-Based Engine)"
- **Implementation**: `blocks/attendanceleaderboard/classes/coach/coach.php`
- **Database Schema**: `acmls_learning_resource` table (difficulty_level column)

## Property Test Metrics

- **Test Methods**: 8
- **Assertions per Run**: ~150 (varies with Eris iterations)
- **Eris Iterations**: 100 (default), configurable via ERIS_ITERATIONS
- **Execution Time**: ~15 seconds (depends on database performance)
- **Code Coverage**: Coach.recommend_resources(), Coach.map_performance_to_difficulty()

## Conclusion

Property 9 is a **critical correctness property** that ensures the pedagogical soundness of the ACMLS recommendation system. By verifying that ALL recommended resources have appropriate difficulty levels for the learner's performance category, this property test provides strong evidence that the Coach component correctly implements adaptive learning principles.

The property-based testing approach with Eris ensures that this correctness holds not just for a few hand-picked examples, but for the entire input space of possible learner profiles and resource configurations.
