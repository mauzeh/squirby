# PR Test Coverage Analysis

## Current Status Summary

**Test Results**: 32 passing, 1 failing (out of 33 total)

**Resolved Issues**:
- ✅ Issue #1: Rep-specific PRs on first attempt - INTENTIONAL behavior, working correctly
- ✅ Issue #2: 1RM PR detection with incorrect math - FIXED, test rewritten with correct calculations
- ✅ Issue #3: High rep ranges (>10) - INTENTIONAL behavior, system correctly doesn't calculate 1RM for >10 reps

**Outstanding Issues**:
- ⚠️ Issue #4: Volume tolerance not working correctly - needs discussion before fixing

**Key Findings**:
1. System intentionally treats first-time rep counts as PRs (accuracy over impressiveness)
2. 1RM and rep-specific PRs are only calculated for 1-10 reps (formulas unreliable beyond that)
3. High rep sets (>10) can still achieve volume PRs
4. Volume tolerance implementation needs review

---

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

### 3. ~~High Rep Ranges (>10) Don't Support 1RM/Rep-Specific PRs~~ ✅ INTENTIONAL

**Behavior**: Lifts with >10 reps don't trigger 1RM or rep-specific PRs, only volume PRs.

**Example**:
- Previous: 100 lbs × 15 reps × 1 set = 1500 lbs volume
- Current: 110 lbs × 15 reps × 1 set = 1650 lbs volume
- **Result**: Volume PR only (no 1RM or rep-specific PR)

**Rationale**: This is intentional and correct. The `calculate1RM()` method in `BaseExerciseType.php` throws an exception for reps > 10 because 1RM formulas become unreliable at high rep ranges. High rep sets test endurance, not maximal strength. Rep-specific PRs are also limited to 1-10 reps for the same reason.

**Status**: ✅ Working as designed, test updated to reflect intentional behavior

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
   - ✅ Rep-specific PR at 11+ reps (correctly does NOT trigger - intentional)

2. **1RM PR Edge Cases**
   - ✅ 1RM PR with high reps (15+) - correctly does NOT calculate (intentional)
   - ❌ 1RM PR with very low reps (1-2)
   - ✅ 1RM PR when rep-specific is also triggered (expected behavior)

3. **Volume PR Edge Cases**
   - ❌ Volume PR with tolerance boundary testing (issue #4 - needs fixing)
   - ✅ Volume PR with varying set weights
   - ❌ Volume PR with different rep schemes

4. **PR Type Interference**
   - ✅ All three types simultaneously
   - ✅ Each type independently
   - ❌ Two types but not the third (various combinations)

5. **Tolerance Testing**
   - ❌ 1RM tolerance (0.1 lbs)
   - ❌ Rep-specific tolerance (0.1 lbs)
   - ❌ Volume tolerance (should be 0.1 lbs total? or per set?) - issue #4

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
2. ~~High rep (>10) 1RM PRs~~ ✅ Already covered, working as designed (no 1RM for >10 reps)
3. Tolerance boundary cases (especially for volume)
4. All combinations of 2 PR types (not just all 3)
5. Boundary testing at exactly 10 reps (edge of 1RM calculation limit)

### Documentation Updates

1. ✅ Clarify when rep-specific PRs trigger (first attempt IS a PR)
2. ✅ Document high rep range behavior (>10 reps don't support 1RM/rep-specific PRs)
3. ❌ Document tolerance application strategy (pending discussion on issue #4)
4. ✅ Add examples of edge cases
5. ❌ Update PR type priority documentation

## Current Test Statistics

- **Total PR-related tests**: 33
- **Passing**: 32
- **Failing**: 1 (issue #4 - volume tolerance)
- **Coverage gaps identified**: 4+

## Next Steps

1. ~~Fix issue #3 (high rep ranges)~~ ✅ COMPLETED - working as designed
2. Discuss and fix issue #4 (volume tolerance) with user
3. Add missing edge case tests
4. Document expected behavior for all scenarios
5. Consider adding property-based tests for PR detection
