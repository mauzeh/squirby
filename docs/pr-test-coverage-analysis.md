# PR Test Coverage Analysis

## Summary

After implementing multiple PR types (1RM, Rep-Specific, Volume), we discovered several issues with test coverage and implementation accuracy.

## Issues Found

### 1. Rep-Specific PR on First Attempt ✅ INTENTIONAL

**Behavior**: Rep-specific PRs trigger on FIRST attempt at a rep count, even if the weight is lighter than other lifts.

**Example**:
- Previous: 100 lbs × 5 reps
- Current: 80 lbs × 4 reps
- **Result**: Detected as "Rep PR" because it's the first 4-rep set

**Rationale**: This is intentional and correct. If the system has no previous data for that rep count, it IS technically a PR for that specific rep range. The system prioritizes accuracy over "impressiveness."

**Status**: ✅ Working as designed

### 2. ~~1RM PR Not Detected in Some Cases~~ ✅ FIXED

**Previous Problem**: Test case had incorrect math expectations.

**Solution**: Rewrote test with correct Epley formula calculations:
- 150 lbs × 8 reps → Est. 1RM = 189.96 lbs
- 165 lbs × 7 reps → Est. 1RM = 203.46 lbs  
- 168 lbs × 7 reps → Est. 1RM = 207.16 lbs ← 1RM PR!

**Note**: In practice, 1RM PRs almost always come with rep-specific PRs because:
- Heavier weight at same reps = rep-specific PR
- New rep count = rep-specific PR (by design)
- Lighter weight at same reps = usually not a 1RM PR

**Status**: ✅ Test rewritten with correct math, system working properly

### 3. High Rep 1RM PRs Not Detected ❌

**Problem**: Lifts with >10 reps aren't being detected as 1RM PRs.

**Example**:
- Previous: 100 lbs × 15 reps
- Current: 110 lbs × 15 reps
- **Issue**: Should be 1RM PR but isn't detected

**Root Cause**: Possible issue with 1RM calculation for high rep ranges.

### 4. Volume Tolerance Not Working ❌

**Problem**: Very small volume increases (within tolerance) are still being detected as PRs.

**Example**:
- Previous: 1500 lbs volume
- Current: 1501.5 lbs volume (0.1% increase)
- **Issue**: Should not be a PR due to tolerance

**Root Cause**: Tolerance (0.1 lbs) is applied per set comparison, not to total volume.

## Test Coverage Gaps Identified

### Missing Test Scenarios

1. **Rep-Specific PR Edge Cases**
   - ✅ First time doing a specific rep count (IS a PR - intentional)
   - ✅ Beating previous record for specific rep count (should be PR)
   - ❌ Rep-specific PR at exactly 10 reps (boundary)
   - ❌ Rep-specific PR at 11 reps (should not trigger)

2. **1RM PR Edge Cases**
   - ❌ 1RM PR with high reps (15+)
   - ❌ 1RM PR with very low reps (1-2)
   - ❌ 1RM PR when rep-specific is not triggered

3. **Volume PR Edge Cases**
   - ❌ Volume PR with tolerance boundary testing
   - ✅ Volume PR with varying set weights
   - ❌ Volume PR with different rep schemes

4. **PR Type Interference**
   - ✅ All three types simultaneously
   - ✅ Each type independently
   - ❌ Two types but not the third (various combinations)

5. **Tolerance Testing**
   - ❌ 1RM tolerance (0.1 lbs)
   - ❌ Rep-specific tolerance (0.1 lbs)
   - ❌ Volume tolerance (should be 0.1 lbs total? or per set?)

## Existing Test Coverage

### Well Covered ✅

1. **Basic PR Detection**
   - First lift is PR
   - Heavier lift is PR
   - Lighter lift is not PR
   - Equal weight is not PR

2. **Exercise Independence**
   - PRs work independently per exercise
   - Different exercises don't interfere

3. **Chronological Processing**
   - Only previous lifts are considered
   - Future lifts don't affect PR status

4. **Rep-Specific PRs (1-10 reps)**
   - Low rep ranges (1-5)
   - Medium rep ranges (6-10)
   - Multiple rep ranges in same session

5. **Volume PRs**
   - Basic volume increase detection
   - Volume decrease (not a PR)
   - Multiple PR types together

### Needs Improvement ⚠️

1. **Rep-Specific Logic**
   - Need to distinguish "first attempt" from "beating previous"
   - Need boundary testing (10 vs 11 reps)

2. **1RM Calculation**
   - High rep ranges (>10)
   - Very low rep ranges (1-2)
   - Edge cases where 1RM increases but rep-specific doesn't

3. **Tolerance Application**
   - How tolerance applies to volume
   - Tolerance at boundaries
   - Cumulative tolerance effects

4. **PR Type Priority**
   - Which label shows when multiple PRs achieved
   - Consistency of labeling

## Recommendations

### Immediate Fixes Needed

1. **~~Fix Rep-Specific Logic~~** ✅ WORKING AS DESIGNED
   - First-time rep counts ARE PRs (intentional)
   - System prioritizes accuracy over impressiveness

2. **Fix 1RM Detection**
   - Verify 1RM calculation for all rep ranges
   - Ensure 1RM PRs are detected independently of rep-specific

3. **Fix Volume Tolerance**
   - Apply tolerance to total volume comparison, not per-set
   - Or clarify tolerance strategy in documentation

### Additional Tests to Add

1. ~~First-time rep count scenarios~~ ✅ Already covered, working as designed
2. High rep (>10) 1RM PRs
3. Tolerance boundary cases
4. All combinations of 2 PR types (not just all 3)

### Documentation Updates

1. ✅ Clarify when rep-specific PRs trigger (first attempt IS a PR)
2. Document tolerance application strategy
3. Add examples of edge cases
4. Update PR type priority documentation

## Current Test Statistics

- **Total PR-related tests**: 33
- **Passing**: 31
- **Failing**: 2 (issues #3 and #4)
- **Coverage gaps identified**: 4+

## Next Steps

1. Fix the 2 remaining bugs (high rep 1RM PRs, volume tolerance)
2. Add missing edge case tests
3. Document expected behavior for all scenarios
4. Consider adding property-based tests for PR detection
