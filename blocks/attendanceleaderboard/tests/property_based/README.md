# Property-Based Testing for ACMLS

This directory contains property-based tests that verify the 22 correctness properties of the ACMLS system using the Eris library.

## Setup

1. Install Eris library:
```bash
cd blocks/attendanceleaderboard
composer install
```

2. Run property-based tests:
```bash
vendor/bin/phpunit tests/property_based/
```

## About Property-Based Testing

Property-based testing (PBT) verifies that a system satisfies formal specifications by:
1. Defining executable properties (invariants that must always hold)
2. Generating random test inputs
3. Checking that properties hold for all generated inputs

Unlike example-based tests that check specific cases, PBT explores the input space systematically.

## Eris Library

Eris is a PHP port of QuickCheck that provides:
- **Generators**: Create random test data (integers, strings, arrays, etc.)
- **Shrinking**: When a property fails, automatically find the minimal failing case
- **Forall**: Run property checks across many generated inputs

### Basic Eris Test Structure

```php
use Eris\TestTrait;

class MyPropertyTest extends \PHPUnit\Framework\TestCase {
    use TestTrait;
    
    public function testProperty() {
        $this->forAll(
            Generator\int(),  // Generate random integers
            Generator\string()  // Generate random strings
        )->then(function($int, $str) {
            // Property assertion
            $this->assertTrue(someProperty($int, $str));
        });
    }
}
```

## ACMLS Correctness Properties

The 22 properties are organized by system component:

### Tracking System (Properties 1-4)
- **Property 1**: Completeness of activity recording
- **Property 2**: Consistency of session summaries
- **Property 3**: Accuracy of engagement metrics
- **Property 4**: Data resilience during disconnection

### Profiling System (Properties 5-8)
- **Property 5**: Completeness of profile dimension updates
- **Property 6**: Correctness of Performance_Category classification
- **Property 7**: Completeness of profile version history
- **Property 8**: Consistency of Learning_Style classification

### Coach (Properties 9-10)
- **Property 9**: Appropriateness of resource difficulty levels
- **Property 10**: Completeness of decision recording

### Evaluation System (Properties 11-12)
- **Property 11**: Accuracy of aggregate performance metrics
- **Property 12**: Precision of performance decline detection

### Motivation System (Properties 13-16)
- **Property 13**: Appropriateness of encouragement categories
- **Property 14**: Completeness of encouragement metadata
- **Property 15**: Prevention of content duplication
- **Property 16**: Relevance of repository queries

### Learning Resource Repository (Property 17)
- **Property 17**: Conformance of query results to parameters

### Leaderboard (Properties 18-20)
- **Property 18**: Accuracy of score calculations
- **Property 19**: Completeness of leaderboard display
- **Property 20**: Precision of achievement prompt triggers

### Cross-Component (Properties 21-22)
- **Property 21**: Dominance of recent data in profile updates
- **Property 22**: Consistency of Performance_Category notifications

## Running Individual Property Tests

```bash
# Run a specific property test
vendor/bin/phpunit tests/property_based/tracking_properties_test.php

# Run with verbose output
vendor/bin/phpunit --verbose tests/property_based/

# Run with coverage
vendor/bin/phpunit --coverage-html coverage/ tests/property_based/
```

## Test Configuration

Eris configuration in `phpunit.xml`:

```xml
<phpunit>
    <php>
        <!-- Number of test iterations per property (default: 100) -->
        <env name="ERIS_ITERATIONS" value="100"/>
        
        <!-- Maximum shrinking attempts (default: 100) -->
        <env name="ERIS_SHRINK_LIMIT" value="100"/>
        
        <!-- Random seed for reproducibility -->
        <!-- <env name="ERIS_SEED" value="12345"/> -->
    </php>
</phpunit>
```

## Debugging Failed Properties

When a property fails, Eris will:
1. Show the failing input
2. Attempt to shrink to the minimal failing case
3. Display the seed for reproduction

Example output:
```
Failed asserting that false is true.
Reproduced with seed: 1234567890
Minimal failing input: [0, ""]
```

To reproduce:
```bash
ERIS_SEED=1234567890 vendor/bin/phpunit tests/property_based/my_test.php
```

## Best Practices

1. **Keep properties simple**: Each property should test one invariant
2. **Use appropriate generators**: Match generators to your domain
3. **Add preconditions**: Use `when()` to filter invalid inputs
4. **Document assumptions**: Explain what each property verifies
5. **Test boundaries**: Ensure generators cover edge cases

## References

- [Eris Documentation](https://github.com/giorgiosironi/eris)
- [QuickCheck Paper](https://www.cs.tufts.edu/~nr/cs257/archive/john-hughes/quick.pdf)
- [Property-Based Testing Guide](https://hypothesis.works/articles/what-is-property-based-testing/)
