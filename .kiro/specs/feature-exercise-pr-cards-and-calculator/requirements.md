# Requirements Document

## Feature Overview

Add PR (Personal Record) cards and a 1RM percentage calculator grid to the exercise logs page. This enhances the page from a purely historical view to an actionable training tool that helps users plan their workouts based on their personal records.

## User Stories

### As a user viewing an exercise's logs page, I want to:
1. See my heaviest lifts for different rep ranges (1 rep, 2 reps, 3 reps) at the top of the page
2. View a 1RM percentage calculator grid that shows recommended weights for different percentages (100%, 95%, 90%, 85%, 80%, 75%, 70%, 65%, 60%, 55%, 50%, 45%)
3. Use these tools to quickly determine what weight to lift during my workout
4. Have this information displayed prominently before the historical chart and log list

## Functional Requirements

### FR1: Heaviest Lifts Display
- Display the heaviest lift for 1 rep, 2 reps, and 3 reps
- Show actual weight lifted (not calculated 1RM)
- Format: "1x1: 242 lbs", "1x2: 235 lbs", "1x3: 235 lbs"
- If no data exists for a rep range, show "—" or "N/A"
- Only show for barbell exercises

### FR2: 1RM Percentage Calculator Grid
- Calculate 1RM based on the heaviest 1-rep lift (1x1)
- If no 1-rep lift exists, use the calculated 1RM from the best lift across all rep ranges
- Display a grid with 3 columns (based on 1x1, 1x2, 1x3 PRs)
- Show rows for percentages: 100%, 95%, 90%, 85%, 80%, 75%, 70%, 65%, 60%, 55%, 50%, 45%
- Calculate weights using the formula: weight = 1RM × percentage
- Round weights to nearest whole number
- Format weights consistently (e.g., "243 lbs")

### FR3: Exercise Type Compatibility
- Only show PR cards and calculator for barbell exercises
- Do not show for: dumbbell, machine, bodyweight, resistance bands, cardio, isometric holds
- Use existing exercise type strategy pattern to determine compatibility

### FR4: Data Calculation
- Query all lift logs for the current user and exercise
- For each rep range (1, 2, 3), find the lift set with the highest weight
- Calculate 1RM for each rep range using the Brzycki formula
- Brzycki formula: 1RM = weight × (36 / (37 - reps))

### FR5: Visual Design
- Place PR cards and calculator above the existing progress chart
- Use clean, readable typography
- Maintain consistency with existing mobile-entry component styles
- Ensure responsive design for mobile devices
- Use appropriate spacing and visual hierarchy

## Non-Functional Requirements

### NFR1: Performance
- Calculations should be performed efficiently without N+1 queries
- Limit queries to necessary data only

### NFR2: Maintainability
- Use existing service patterns (ComponentBuilder, exercise type strategies)
- Create reusable service for PR and calculator logic
- Follow existing code conventions and structure

### NFR3: Extensibility
- Design should allow for future additions (e.g., more rep ranges, different percentages)
- Service should be reusable in other contexts if needed

## Out of Scope

- Unit conversion (lbs/kg) - not needed per user request
- Rep scheme filtering - can be added later
- Editing PRs directly from this view
- Comparing PRs across different time periods
- Exporting calculator data

## Success Criteria

1. Users can see their top 3 PRs (1x1, 1x2, 1x3) on the exercise logs page
2. Users can reference a percentage-based calculator for workout planning
3. Feature only appears for compatible exercise types
4. Page loads without performance degradation
5. Design is consistent with existing UI patterns
6. Code follows existing architectural patterns

## Dependencies

- Existing OneRepMaxCalculatorService
- Exercise type strategy pattern
- ComponentBuilder service
- LiftLog and LiftSet models
- Exercise logs page (ExerciseController::showLogs)

## Assumptions

- Users understand 1RM concepts and percentage-based training
- The Brzycki formula is acceptable for 1RM calculations
- Three rep ranges (1, 2, 3) are sufficient for most users
- Percentage increments of 5% provide adequate granularity
