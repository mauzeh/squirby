# Implementation Plan

- [x] 1. Create database foundation for exercise intelligence
  - Create migration for exercise_intelligence table with proper constraints and indexes
  - Include JSON fields for muscle_data, foreign key to exercises table, and enum constraints
  - _Requirements: 2.1, 2.5, 3.1, 3.2, 3.3, 4.1_

- [x] 2. Implement ExerciseIntelligence model and relationships
  - [x] 2.1 Create ExerciseIntelligence model with proper fillable fields and casts
    - Define fillable array with all intelligence fields
    - Set up JSON casting for muscle_data array
    - Configure integer casting for difficulty_level and recovery_hours
    - _Requirements: 2.1, 3.1, 3.2, 3.3_

  - [x] 2.2 Implement model relationships and scopes
    - Add belongsTo relationship to Exercise model
    - Create scopeForGlobalExercises to filter by global exercises only
    - Add scopeByMovementArchetype and scopeByCategory for filtering
    - _Requirements: 2.5, 4.1, 4.3_

  - [x] 2.3 Add helper methods for muscle data analysis
    - Implement getPrimaryMoverMuscles() to extract primary mover muscles from JSON
    - Create getSynergistMuscles() and getStabilizerMuscles() methods
    - Add getIsotonicMuscles() and getIsometricMuscles() methods
    - _Requirements: 3.1, 3.2, 3.3_

- [x] 3. Extend Exercise model with intelligence relationship
  - [x] 3.1 Add intelligence relationship to Exercise model
    - Create hasOne relationship to ExerciseIntelligence
    - Add hasIntelligence() helper method to check if intelligence data exists
    - Implement scopeWithIntelligence to filter exercises that have intelligence data
    - _Requirements: 2.1, 2.5, 5.2_

- [x] 4. Create user activity analysis system
  - [x] 4.1 Implement UserActivityAnalysis value object
    - Create class with readonly properties for muscle workload, movement archetypes, recent exercises
    - Add getMuscleWorkloadScore() method to calculate muscle usage intensity
    - Implement getArchetypeFrequency() to track movement pattern frequency
    - Add wasExerciseRecentlyPerformed() and getDaysSinceLastWorkout() methods
    - _Requirements: 1.1, 1.2_

  - [x] 4.2 Create ActivityAnalysisService for lift log analysis
    - Implement analyzeLiftLogs() method to process user's last 31 days of activity
    - Create calculateMuscleWorkload() to determine muscle usage from exercise intelligence data
    - Add identifyMovementPatterns() to track archetype frequency
    - Implement findRecentExercises() to identify recently performed exercises
    - _Requirements: 1.1, 1.2, 1.4_

- [x] 5. Build recommendation engine core logic
  - [x] 5.1 Create RecommendationEngine service class
    - Implement getRecommendations() main method that returns exercise suggestions
    - Create analyzeUserActivity() method that uses ActivityAnalysisService
    - Add findUnderworkedMuscles() to identify muscles that need attention
    - Implement filterByRecovery() to respect recovery periods
    - _Requirements: 1.1, 1.3, 1.4, 1.5_

  - [x] 5.2 Implement exercise scoring and ranking algorithm
    - Create scoreExercises() method that ranks exercises based on user needs
    - Implement muscle balance scoring to prioritize underworked muscle groups
    - Add movement archetype diversity scoring to encourage varied movement patterns
    - Create difficulty progression logic based on user's recent exercise difficulty levels
    - _Requirements: 1.3, 1.5_

- [x] 6. Create intelligence management interface
  - [x] 6.1 Build ExerciseIntelligenceController for CRUD operations
    - Implement index() method to list exercises with their intelligence data
    - Create create() and store() methods for adding intelligence to global exercises
    - Add edit() and update() methods for modifying existing intelligence data
    - Implement destroy() method for removing intelligence data
    - _Requirements: 3.1, 3.2, 3.3, 4.1, 4.2_

  - [x] 6.2 Create form validation and request classes
    - Build StoreExerciseIntelligenceRequest with validation rules for all fields
    - Validate muscle_data JSON structure and muscle name consistency
    - Add validation for movement archetype and category enums
    - Implement difficulty level range validation (1-5)
    - _Requirements: 3.1, 3.2, 3.3, 3.4, 3.5_

- [x] 7. Build recommendation user interface
  - [x] 7.1 Create RecommendationController for user-facing features
    - Implement index() method to display recommendations page
    - Create api() method for AJAX-based recommendation requests
    - Add filtering options for movement archetype and difficulty level
    - _Requirements: 1.5, 5.1_

  - [x] 7.2 Design recommendation display views
    - Create recommendations index view showing suggested exercises
    - Display muscle groups targeted, movement archetype, and difficulty level
    - Add reasoning for why each exercise was recommended
    - Include links to exercise details and logging functionality
    - _Requirements: 1.5, 5.1_

- [x] 8. Create intelligence management views
  - [x] 8.1 Build intelligence data management interface
    - Create index view listing all global exercises and their intelligence status
    - Design create/edit forms for intelligence data with muscle selection interface
    - Add muscle role and contraction type selection for each muscle
    - Implement primary mover and largest muscle selection dropdowns
    - _Requirements: 3.1, 3.2, 3.3, 4.1_

  - [x] 8.2 Add intelligence data to exercise views
    - Extend exercise show view to display intelligence information when available
    - Add intelligence summary to exercise index view for global exercises
    - Create partial view for displaying muscle involvement details
    - _Requirements: 5.1, 5.2_

- [x] 9. Implement routes and middleware
  - [x] 9.1 Add routes for intelligence management
    - Create resource routes for ExerciseIntelligenceController
    - Add routes for recommendation system
    - Implement admin middleware for intelligence management routes
    - _Requirements: 4.1, 4.2_

  - [x] 9.2 Add API routes for AJAX functionality
    - Create API routes for getting recommendations
    - Add routes for muscle data lookup and validation
    - Implement routes for exercise intelligence quick lookup
    - _Requirements: 1.5, 5.1_

- [x] 10. Add database seeders for initial data
  - [x] 10.1 Create intelligence data seeder for common exercises
    - Seed intelligence data for basic compound movements (squats, deadlifts, bench press)
    - Add data for common isolation exercises (bicep curls, tricep extensions)
    - Include bodyweight exercises (push-ups, pull-ups, planks)
    - _Requirements: 3.1, 3.2, 3.3, 4.1_

- [ ] 11. Write comprehensive tests
  - [ ] 11.1 Create unit tests for models and services
    - Test ExerciseIntelligence model relationships and helper methods
    - Write tests for UserActivityAnalysis calculations
    - Test RecommendationEngine scoring algorithms
    - _Requirements: 1.1, 1.2, 1.3, 2.5, 3.1_

  - [ ] 11.2 Write integration tests for recommendation system
    - Test complete recommendation workflow from user activity to suggestions
    - Verify muscle workload calculations with real exercise data
    - Test recovery period filtering logic
    - _Requirements: 1.1, 1.4, 1.5_

  - [ ] 11.3 Create feature tests for controllers and views
    - Test intelligence management CRUD operations
    - Verify admin-only access to intelligence management
    - Test recommendation display and filtering
    - _Requirements: 4.1, 4.2, 5.1_