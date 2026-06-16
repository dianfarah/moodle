# Property 6: Kebenaran Klasifikasi Performance_Category

## Property Statement

**For ALL score values in the range [0, 100], the classification of Performance_Category MUST be consistent with the defined rules:**

```
score < 60       → Category 1 (Low)
60 ≤ score < 80  → Category 2 (Middle)
score ≥ 80       → Category 3 (High)
```

## Formal Specification

```
∀ score ∈ [0, 100]:
  classify_performance_category(score) = 
    1  if score < 60
    2  if 60 ≤ score < 80
    3  if score ≥ 80

∀ score ∈ ℝ:
  classify_performance_category(score) ∈ {1, 2, 3}

∀ score₁, score₂ ∈ [0, 100]:
  score₁ = score₂ ⟹ classify_performance_category(score₁) = classify_performance_category(score₂)
  (Determinism)

∀ score₁, score₂ ∈ [0, 100]:
  score₁ < score₂ ⟹ classify_performance_category(score₁) ≤ classify_performance_category(score₂)
  (Monotonicity)
```

## Requirements Addressed

- **Requirement 3.2**: Create initial profile with Performance_Category classification
- **Requirement 3.3**: Update Performance_Category based on latest score
- **Requirement 4.3**: Classification must follow exact boundary rules

## Why This Property Matters

### Educational Significance

Performance_Category classification is the foundation of the entire adaptive learning system:

1. **Pedagogical Correctness**: The classification boundaries (60%, 80%) align with standard academic grading thresholds. Incorrect classification would result in learners receiving inappropriate interventions.

2. **Fairness**: All learners with the same score must receive the same classification. Any inconsistency would be unfair and undermine trust in the system.

3. **Intervention Effectiveness**: The Coach uses Performance_Category to determine:
   - Difficulty level of recommended resources
   - Frequency of motivational interventions
   - Type of encouragement content

4. **Research Validity**: For research purposes, classification must be deterministic and reproducible. Inconsistent classification would invalidate research findings.

### Technical Significance

1. **Boundary Correctness**: The most error-prone cases are at boundaries (59.99, 60.00, 79.99, 80.00). Off-by-one errors or floating-point precision issues could cause misclassification.

2. **Determinism**: The same score must always produce the same category, regardless of when or how many times the function is called.

3. **Monotonicity**: Higher scores must never result in lower categories. This is a fundamental invariant of performance classification.

4. **Completeness**: Every possible score value must map to exactly one valid category (no gaps, no overlaps).

## Test Strategy

### 1. Property-Based Testing (Main Test)

**Test**: `test_property6_performance_category_classification_correctness`

- **Generator**: Random scores from 0.00 to 100.00 (with 0.01 precision)
- **Properties Verified**:
  - Category is always valid (1, 2, or 3)
  - Classification follows exact rules
  - Classification is deterministic (same score → same category)
  - Classification matches expected category

**Why Property-Based**: Exhaustively tests all possible score values to ensure no edge cases are missed.

### 2. Boundary Value Testing

**Test**: `test_property6_boundary_values_classification`

Critical boundary values:
- `0.00` → Low (minimum valid score)
- `59.99` → Low (just below Middle threshold)
- `60.00` → Middle (exactly at threshold)
- `60.01` → Middle (just above threshold)
- `79.99` → Middle (just below High threshold)
- `80.00` → High (exactly at threshold)
- `80.01` → High (just above threshold)
- `100.00` → High (maximum valid score)

**Why Boundary Testing**: Boundaries are where classification logic is most likely to fail due to off-by-one errors or incorrect comparison operators.

### 3. Edge Case Testing

**Test**: `test_property6_edge_cases_outside_valid_range`

Tests scores outside the valid [0, 100] range:
- Negative scores (-10.0, -0.01)
- Scores above 100 (100.01, 150.0, 999.99)

**Why Edge Case Testing**: Ensures the function handles invalid inputs gracefully without crashing, and produces reasonable classifications.

### 4. Monotonicity Testing

**Test**: `test_property6_classification_is_monotonic`

Verifies: `score₁ < score₂ ⟹ category(score₁) ≤ category(score₂)`

**Why Monotonicity**: This is a fundamental invariant. If violated, it would mean higher performance results in lower classification, which is logically incorrect.

### 5. Partition Testing

**Test**: `test_property6_classification_partitions_score_space`

Exhaustively tests:
- All scores in [0, 60) → Low
- All scores in [60, 80) → Middle
- All scores in [80, 100] → High

**Why Partition Testing**: Verifies that the three categories completely partition the score space with no gaps or overlaps.

### 6. Stress Testing

**Test**: `test_property6_classification_consistency_under_stress`

Calls classification 100 times for each test score to verify consistency under high volume.

**Why Stress Testing**: Ensures classification remains deterministic even under repeated calls, ruling out any state-dependent behavior.

### 7. Integration Testing

**Test**: `test_property6_classification_in_profile_update_context`

Verifies classification correctness when used within the profile update workflow:
- Profile's performance_category matches classification result
- Database record matches profile
- Direct classification call matches profile update result

**Why Integration Testing**: Ensures classification works correctly in its real-world usage context, not just in isolation.

## Implementation Details

### Function Under Test

```php
public function classify_performance_category(float $score): int {
    if ($score < 60.0) {
        return learner_profile::PERFORMANCE_LOW;    // 1
    }
    if ($score < 80.0) {
        return learner_profile::PERFORMANCE_MIDDLE; // 2
    }
    return learner_profile::PERFORMANCE_HIGH;       // 3
}
```

### Classification Rules

| Score Range | Category | Constant | Value |
|-------------|----------|----------|-------|
| [0, 60)     | Low      | `PERFORMANCE_LOW` | 1 |
| [60, 80)    | Middle   | `PERFORMANCE_MIDDLE` | 2 |
| [80, 100]   | High     | `PERFORMANCE_HIGH` | 3 |

### Critical Boundary Values

| Score | Expected Category | Rationale |
|-------|-------------------|-----------|
| 59.99 | Low (1) | Just below 60 threshold |
| 60.00 | Middle (2) | Exactly at threshold (inclusive) |
| 79.99 | Middle (2) | Just below 80 threshold |
| 80.00 | High (3) | Exactly at threshold (inclusive) |

## Potential Failure Modes

### 1. Floating-Point Precision Issues

**Problem**: Floating-point comparison can be imprecise.

**Example**:
```php
$score = 59.999999999999;  // Might be treated as 60.0 due to rounding
```

**Mitigation**: Tests use explicit decimal values (59.99, 60.00) to verify boundary behavior.

### 2. Incorrect Comparison Operators

**Problem**: Using `<=` instead of `<` or vice versa.

**Example**:
```php
if ($score <= 60.0) {  // WRONG: 60.0 would be Low instead of Middle
    return PERFORMANCE_LOW;
}
```

**Mitigation**: Boundary tests explicitly verify 60.00 → Middle and 80.00 → High.

### 3. Off-By-One Errors

**Problem**: Boundary values classified incorrectly.

**Example**:
```php
if ($score < 59.0) {  // WRONG: threshold should be 60.0
    return PERFORMANCE_LOW;
}
```

**Mitigation**: Property-based tests generate scores across entire range, catching any threshold errors.

### 4. Non-Deterministic Behavior

**Problem**: Classification depends on external state or randomness.

**Example**:
```php
// WRONG: Classification should not depend on time or random values
if ($score < 60.0 || rand(0, 1) == 1) {
    return PERFORMANCE_LOW;
}
```

**Mitigation**: Determinism test calls classification multiple times for same score and verifies identical results.

### 5. Invalid Category Values

**Problem**: Function returns value outside {1, 2, 3}.

**Example**:
```php
return 0;  // WRONG: Invalid category
return 4;  // WRONG: Invalid category
```

**Mitigation**: Every test verifies category ∈ {1, 2, 3}.

## Test Execution

### Running the Tests

```bash
# Run all property-based tests
php admin/tool/phpunit/cli/util.php --run blocks/attendanceleaderboard/tests/property_based/profiling_properties_test.php

# Run only Property 6 tests
php admin/tool/phpunit/cli/util.php --filter test_property6 blocks/attendanceleaderboard/tests/property_based/profiling_properties_test.php
```

### Expected Output

```
Property 6 Tests:
✓ test_property6_performance_category_classification_correctness (100 random scores)
✓ test_property6_boundary_values_classification (8 boundary cases)
✓ test_property6_edge_cases_outside_valid_range (5 edge cases)
✓ test_property6_classification_is_monotonic (100 random pairs)
✓ test_property6_classification_partitions_score_space (241 scores)
✓ test_property6_classification_consistency_under_stress (900 calls)
✓ test_property6_classification_in_profile_update_context (100 random scores)

Total: 7 tests, 0 failures
```

## Success Criteria

Property 6 is considered **PASSED** if and only if:

1. ✅ All 7 test methods pass without failures
2. ✅ Property-based test generates at least 100 random test cases
3. ✅ All boundary values (59.99, 60.00, 79.99, 80.00) are classified correctly
4. ✅ Classification is deterministic (same score always produces same category)
5. ✅ Classification is monotonic (higher scores never produce lower categories)
6. ✅ All scores in [0, 100] map to valid categories {1, 2, 3}
7. ✅ Classification works correctly in profile update context

## Relationship to Other Properties

### Property 5 (Profile Dimension Update Completeness)

Property 6 is used by Property 5 to verify that performance_category is updated correctly when score data arrives.

**Dependency**: Property 5 relies on Property 6 being correct. If Property 6 fails, Property 5 tests may also fail.

### Property 12 (Performance Decline Detection)

Performance decline detection compares Performance_Category values over time. Incorrect classification would cause false positives or false negatives in decline detection.

**Dependency**: Property 12 assumes Property 6 is correct.

### Property 22 (Performance_Category Transition Notifications)

Notifications are triggered when Performance_Category changes. Incorrect classification would cause spurious notifications or missed notifications.

**Dependency**: Property 22 assumes Property 6 is correct.

## References

### Requirements Document

- **Requirement 3.2**: "WHEN seorang Learner pertama kali terdaftar dalam sistem, THE Profiling_System SHALL membuat Learner_Profile awal dengan Performance_Category yang diklasifikasikan sebagai *Low*, *Middle*, atau *High* berdasarkan data akademik awal yang tersedia."

- **Requirement 3.3**: "WHEN hasil evaluasi baru diterima dari Evaluation_System, THE Profiling_System SHALL memperbarui Performance_Category Learner berdasarkan skor terbaru dengan mempertimbangkan riwayat performa sebelumnya."

### Design Document

- **Section B.3 (Profiling System)**: "Profiling System mengklasifikasikan Performance_Category Learner (Low/Middle/High) berdasarkan data akademik dan pola engagement."

- **Section E.1 (Database Schema)**: `performance_category TINYINT(1) NOT NULL DEFAULT 1, -- 1=Low, 2=Middle, 3=High`

### Task Document

- **Task 5.3**: "Implementasi metode `classify_performance_category()` — klasifikasi Low/Middle/High berdasarkan aturan: <60%=Low, 60-79%=Middle, ≥80%=High"

## Conclusion

Property 6 is a **critical correctness property** that ensures the foundation of the adaptive learning system is sound. All classification decisions must be:

- **Correct**: Following exact boundary rules
- **Deterministic**: Same input always produces same output
- **Monotonic**: Higher scores never produce lower categories
- **Complete**: Every score maps to exactly one valid category

The comprehensive test suite (7 test methods, 1000+ test cases) provides high confidence that classification is correct for all possible inputs.
