# Requirements Document

## Introduction

This feature adds intelligent exercise recommendation capabilities to the fitness app by creating a separate intelligence data layer for exercises. The system will track muscle groups, movement patterns, and other metadata to enable smart workout recommendations based on user activity patterns over the last 31 days, while preserving the existing Exercise model and TSV import/export functionality completely unchanged.

## Glossary

- **Exercise_Intelligence_System**: The new intelligence data layer that stores metadata about exercises
- **Exercise_Model**: The existing Exercise model that remains unchanged
- **Recommendation_Engine**: The system component that suggests exercises based on user activity patterns
- **Muscle_Group**: A categorization of individual muscles targeted by an exercise with contraction type and role specification
- **Primary_Mover**: The main muscle responsible for generating movement in an exercise
- **Largest_Muscle**: The largest muscle involved in the exercise movement
- **Contraction_Type**: The type of muscle contraction - isotonic (muscle changes length) or isometric (muscle maintains length)
- **Muscle_Role**: The function of a muscle in an exercise - primary mover, synergist, or stabilizer
- **Movement_Archetype**: The fundamental movement pattern of an exercise (e.g., push, pull, squat, hinge)
- **Recovery_Period**: The recommended time between performing exercises targeting the same muscle groups
- **Activity_Pattern**: User's exercise history over a specified time period (31 days)

## Requirements

### Requirement 1

**User Story:** As a fitness app user, I want the app to recommend exercises based on what I've worked in the last 31 days, so that I can have balanced and progressive workouts.

#### Acceptance Criteria

1. WHEN a user requests exercise recommendations, THE Exercise_Intelligence_System SHALL analyze the user's lift logs from the last 31 days
2. WHEN analyzing activity patterns, THE Recommendation_Engine SHALL identify which muscle groups have been worked and when
3. WHEN generating recommendations, THE Exercise_Intelligence_System SHALL prioritize exercises targeting under-worked muscle groups
4. WHEN considering recovery periods, THE Recommendation_Engine SHALL avoid recommending exercises for muscle groups still in recovery
5. THE Exercise_Intelligence_System SHALL provide at least 3 exercise recommendations per request

### Requirement 2

**User Story:** As a fitness app user, I want exercise intelligence data to be stored separately from basic exercise information, so that my existing TSV export/import workflow remains unchanged.

#### Acceptance Criteria

1. THE Exercise_Intelligence_System SHALL store intelligence data in a separate database table from the Exercise_Model
2. THE Exercise_Model SHALL remain completely unchanged in structure and functionality
3. WHEN exporting exercises via TSV, THE Exercise_Intelligence_System SHALL not modify the existing TSV format
4. WHEN importing exercises via TSV, THE Exercise_Intelligence_System SHALL not interfere with the existing import process
5. THE Exercise_Intelligence_System SHALL maintain referential integrity with the Exercise_Model through foreign key relationships

### Requirement 3

**User Story:** As a fitness app administrator, I want to define intelligence metadata for exercises including muscle groups and movement patterns, so that the recommendation engine has the data it needs to function.

#### Acceptance Criteria

1. THE Exercise_Intelligence_System SHALL store individual muscles with their contraction type (isotonic/isometric) and role (primary mover/synergist/stabilizer) for each exercise
2. THE Exercise_Intelligence_System SHALL identify the primary moving muscle for each exercise
3. THE Exercise_Intelligence_System SHALL identify the largest muscle involved in each exercise
4. THE Exercise_Intelligence_System SHALL categorize exercises by movement archetype (push, pull, squat, hinge, carry, core)
5. THE Exercise_Intelligence_System SHALL assign difficulty levels on a 1-5 scale
6. THE Exercise_Intelligence_System SHALL define recovery periods in hours for each exercise

### Requirement 4

**User Story:** As a fitness app user, I want the intelligence system to work with global exercises only, so that I get recommendations from the curated exercise database.

#### Acceptance Criteria

1. THE Exercise_Intelligence_System SHALL support intelligence data for global exercises only
2. THE Exercise_Intelligence_System SHALL not support intelligence data for user-specific exercises
3. WHEN generating recommendations, THE Recommendation_Engine SHALL consider only global exercises with intelligence data
4. THE Exercise_Intelligence_System SHALL ignore personal exercises when generating recommendations
5. WHEN a personal exercise is promoted to global, THE Exercise_Intelligence_System SHALL allow intelligence data to be added to the newly global exercise

### Requirement 5

**User Story:** As a fitness app developer, I want the intelligence system to be optional and non-breaking, so that the app continues to function normally even if intelligence data is missing.

#### Acceptance Criteria

1. THE Exercise_Intelligence_System SHALL function when intelligence data exists for an exercise
2. THE Exercise_Intelligence_System SHALL gracefully handle exercises without intelligence data
3. WHEN intelligence data is missing, THE Recommendation_Engine SHALL exclude those exercises from recommendations or use default values
4. THE Exercise_Model SHALL continue to function normally regardless of intelligence data presence
5. THE Exercise_Intelligence_System SHALL not cause errors or failures in existing exercise functionality