# Implementation Plan

- [x] 1. Create database migration for table rename
  - Write migration to rename daily_logs table to food_logs
  - Include proper rollback functionality in down() method
  - Test migration execution and rollback
  - Run php artisan test to verify no existing functionality is broken
  - _Requirements: 3.1, 3.2, 3.3, 3.4, 3.5, 6.1, 6.2, 6.3, 6.4, 6.5, 6.6_

- [x] 2. Update Model Layer
- [x] 2.1 Remove duplicate FoodLog model and rename DailyLog
  - Delete existing app/Models/FoodLog.php file
  - Rename app/Models/DailyLog.php to app/Models/FoodLog.php
  - Update class name from DailyLog to FoodLog
  - Add protected $table = 'food_logs' property
  - _Requirements: 1.1, 1.8_

- [x] 2.2 Update related model relationships
  - Update User model to replace dailyLogs() method with foodLogs()
  - Update Ingredient model to replace dailyLogs() method with foodLogs()
  - Update Unit model if it has dailyLogs() relationship method
  - _Requirements: 1.1, 1.7_

- [x] 2.3 Update model factory
  - Rename database/factories/DailyLogFactory.php to FoodLogFactory.php
  - Update factory class name and model reference
  - Update any seeder files that reference DailyLogFactory
  - Run php artisan test to verify model layer changes work correctly
  - _Requirements: 1.1, 1.7, 6.1, 6.2, 6.3, 6.4, 6.5, 6.6_

- [x] 3. Update Controller Layer
- [x] 3.1 Rename and update DailyLogController
  - Rename app/Http/Controllers/DailyLogController.php to FoodLogController.php
  - Update class name from DailyLogController to FoodLogController
  - Update all model references from DailyLog to FoodLog
  - Update all variable names from $dailyLog to $foodLog
  - _Requirements: 1.3, 1.7, 1.8_

- [x] 3.2 Update controller method implementations
  - Update index() method to use FoodLog model and food-logs routes
  - Update store() method to use FoodLog model and redirect to food-logs.index
  - Update edit() method to use FoodLog model
  - Update update() method to use FoodLog model and redirect to food-logs.index
  - Update destroy() method to use FoodLog model and redirect to food-logs.index
  - _Requirements: 1.3, 1.7, 1.8, 4.1, 4.4_

- [x] 3.3 Update custom controller methods
  - Update addMealToLog() method to use FoodLog model and food-logs routes
  - Update destroySelected() method to use FoodLog model and food-logs routes
  - Update importTsv() method to use FoodLog model and food-logs routes
  - Update export() and exportAll() methods to use FoodLog model
  - Run php artisan test to verify controller layer changes work correctly
  - _Requirements: 1.3, 1.7, 1.8, 4.1, 4.4, 6.1, 6.2, 6.3, 6.4, 6.5, 6.6_

- [x] 4. Update Route Layer
- [x] 4.1 Update web routes configuration
  - Update routes/web.php to import FoodLogController instead of DailyLogController
  - Change resource route from 'daily-logs' to 'food-logs'
  - Update default redirect from daily-logs.index to food-logs.index
  - _Requirements: 1.4, 4.1, 4.4_

- [x] 4.2 Update custom route definitions
  - Update 'daily-logs/add-meal' route to 'food-logs/add-meal'
  - Update 'daily-logs/destroy-selected' route to 'food-logs/destroy-selected'
  - Update 'daily-logs/import-tsv' route to 'food-logs/import-tsv'
  - Update 'daily-logs/export' and 'daily-logs/export-all' routes to use 'food-logs'
  - Run php artisan test to verify route layer changes work correctly
  - _Requirements: 1.4, 4.1, 4.2, 6.1, 6.2, 6.3, 6.4, 6.5, 6.6_

- [-] 5. Update View Layer
- [x] 5.1 Rename view directory and update view files
  - Rename resources/views/daily_logs/ directory to resources/views/food_logs/
  - Update all route references in view files from daily-logs to food-logs
  - Update form action URLs to use food-logs routes
  - _Requirements: 1.5, 2.1, 2.2, 2.3, 4.1_

- [-] 5.2 Update UI text and labels
  - Update page titles from "Daily Log" to "Food Log"
  - Update navigation menu items from "Daily Logs" to "Food Logs"
  - Update button text and form labels to use "Food Log" terminology
  - Update success and error messages to reference "Food Log"
  - _Requirements: 2.1, 2.2, 2.3, 2.4, 2.5_

- [ ] 5.3 Update navigation and layout files
  - Update main navigation to use food-logs.index route
  - Update any breadcrumb references to use Food Log terminology
  - Update any layout files that reference daily logs
  - Run php artisan test to verify view layer changes work correctly
  - _Requirements: 2.1, 4.4, 6.1, 6.2, 6.3, 6.4, 6.5, 6.6_

- [ ] 6. Update Service Layer
- [ ] 6.1 Update TsvImporterService
  - Update TsvImporterService to use FoodLog model instead of DailyLog
  - Update method parameters and return types to reference FoodLog
  - Update any comments and documentation to use FoodLog terminology
  - _Requirements: 1.1, 1.7, 5.1, 5.2, 5.3_

- [ ] 6.2 Update NutritionService if needed
  - Check if NutritionService references DailyLog model
  - Update any references to use FoodLog model
  - Update method signatures and documentation
  - Run php artisan test to verify service layer changes work correctly
  - _Requirements: 1.1, 1.7, 5.1, 5.2, 5.3, 6.1, 6.2, 6.3, 6.4, 6.5, 6.6_

- [ ] 7. Update Test Layer
- [ ] 7.1 Rename and update export test file
  - Rename tests/Feature/DailyLogExportTest.php to FoodLogExportTest.php
  - Update class name from DailyLogExportTest to FoodLogExportTest
  - Update all model references from DailyLog to FoodLog
  - Update all route references from daily-logs to food-logs
  - Update factory references from DailyLogFactory to FoodLogFactory
  - _Requirements: 1.6, 1.7, 1.8, 6.1, 6.2, 6.3, 6.4, 6.5_

- [ ] 7.2 Rename and update import test file
  - Rename tests/Feature/DailyLogImportTest.php to FoodLogImportTest.php
  - Update class name from DailyLogImportTest to FoodLogImportTest
  - Update all route references from daily-logs to food-logs
  - Update assertion messages to reference "Food Log"
  - _Requirements: 1.6, 1.7, 1.8, 6.1, 6.2, 6.3, 6.4, 6.5_

- [ ] 7.3 Rename and update multi-user test file
  - Rename tests/Feature/DailyLogMultiUserTest.php to FoodLogMultiUserTest.php
  - Update class name from DailyLogMultiUserTest to FoodLogMultiUserTest
  - Update all model references from DailyLog to FoodLog
  - Update all route references from daily-logs to food-logs
  - Update factory references from DailyLogFactory to FoodLogFactory
  - Update variable names from $dailyLog to $foodLog
  - _Requirements: 1.6, 1.7, 1.8, 6.1, 6.2, 6.3, 6.4, 6.5_

- [ ] 7.4 Update other test files with route references
  - Update tests/Feature/Auth/AuthenticationTest.php route reference
  - Update tests/Feature/Auth/RegistrationTest.php route reference
  - Update tests/Feature/Auth/EmailVerificationTest.php route reference
  - Update tests/Feature/Auth/PasswordConfirmationTest.php route reference
  - Update tests/Feature/ExampleTest.php route reference
  - Update tests/Feature/UserManagementTest.php route reference
  - Run php artisan test to verify all test layer changes work correctly
  - _Requirements: 1.8, 4.4, 6.1, 6.2, 6.3, 6.4, 6.5, 6.6_

- [ ] 8. Run comprehensive testing and validation
- [ ] 8.1 Execute migration and verify database changes
  - Run the table rename migration
  - Verify daily_logs table is renamed to food_logs
  - Verify all data is preserved during migration
  - Test migration rollback functionality
  - _Requirements: 3.1, 3.2, 3.3, 3.4, 3.5, 6.1, 6.2, 6.3, 6.4, 6.5, 6.6_

- [ ] 8.2 Run full test suite and fix any failures
  - Execute php artisan test to run all tests
  - Fix any failing tests related to the refactoring
  - Verify all food log functionality works correctly
  - Verify multi-user isolation still works
  - Verify import/export functionality works
  - _Requirements: 1.8, 1.9, 6.1, 6.2, 6.3, 6.4, 6.5, 6.6_

- [ ] 8.3 Manual testing of UI functionality
  - Test food log creation through web interface
  - Test food log editing and deletion
  - Test meal addition to food logs
  - Test bulk deletion functionality
  - Test import/export functionality through UI
  - Verify all navigation and links work correctly
  - _Requirements: 1.8, 2.1, 2.2, 2.3, 2.4, 2.5, 4.1, 4.2, 4.3, 4.4, 4.5_