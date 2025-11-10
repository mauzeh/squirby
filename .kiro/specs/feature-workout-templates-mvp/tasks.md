# Implementation Plan

- [x] 1. Create database schema and models
  - Create migration for workout_templates table with user_id, name, description, timestamps
  - Create migration for workout_template_exercises pivot table with workout_template_id, exercise_id, order, timestamps
  - Create WorkoutTemplate model with relationships to User and Exercise
  - Create WorkoutTemplateExercise pivot model with relationships
  - Add scopes to WorkoutTemplate model (forUser)
  - Add authorization methods to WorkoutTemplate model (canBeEditedBy, canBeDeletedBy)
  - _Requirements: 1.1, 1.2, 1.3, 1.4, 1.5_

- [x] 2. Create policy for authorization
  - Create WorkoutTemplatePolicy with view, create, update, delete methods
  - Register policy in AuthServiceProvider
  - Ensure users can only access their own templates
  - _Requirements: 2.1, 2.2, 2.3, 2.4, 3.1, 3.2, 3.3, 3.4, 3.5, 4.1, 4.2, 4.3_

- [x] 3. Create controller with CRUD operations
  - Create WorkoutTemplateController with index, create, store, show, edit, update, destroy methods
  - Implement index() to list all user templates with exercise counts
  - Implement create() to show template creation form with available exercises
  - Implement store() to save new template with exercises and order
  - Implement show() to display single template with exercises
  - Implement edit() to show template edit form with current data
  - Implement update() to modify template name, description, and exercises
  - Implement destroy() to delete template
  - Use DB transactions for template + exercises operations
  - Add proper validation for all inputs
  - _Requirements: 1.1, 1.2, 1.3, 1.4, 1.5, 2.1, 2.2, 2.3, 2.4, 3.1, 3.2, 3.3, 3.4, 3.5, 4.1, 4.2, 4.3_

- [x] 4. Define routes
  - Add resource routes for workout-templates in web.php
  - Ensure routes are protected with auth middleware
  - _Requirements: 1.1, 2.1, 3.1, 4.1_

- [x] 5. Create template list view (index)
  - Create index.blade.php to display all user templates
  - Show template name, description (truncated), exercise count, creation date
  - Add View, Edit, Delete action buttons for each template
  - Include "Create New Template" button
  - Display empty state message when no templates exist
  - Use responsive card or table layout
  - _Requirements: 2.1, 2.2, 2.3, 2.4_

- [x] 6. Create template show view
  - Create show.blade.php to display single template details
  - Show template name and full description
  - List all exercises in order with exercise names
  - Add Edit and Delete action buttons
  - Include back to list button
  - _Requirements: 2.1, 2.2, 2.3_

- [ ] 7. Create template form views (create and edit)
  - Create create.blade.php for new template form
  - Create edit.blade.php for editing existing template
  - Create _form.blade.php partial for shared form elements
  - Add name and description input fields
  - Implement exercise selection using server-side form submission
  - Add up/down buttons for reordering exercises (POST requests)
  - Display selected exercises with order numbers
  - Show validation errors
  - Add Save and Cancel buttons
  - Ensure pure server-side rendering with no JavaScript
  - _Requirements: 1.1, 1.2, 1.3, 1.4, 1.5, 3.1, 3.2, 3.3, 3.4, 3.5, 5.1, 5.2, 5.3, 5.4, 6.1, 6.2, 6.3, 6.4, 6.5_

- [x] 8. Integrate exercise selection system
  - Reuse existing exercise selection UI from mobile lift forms
  - Ensure exercise picker respects user's exercise visibility settings
  - Display exercises with proper names and aliases
  - Allow inline exercise creation if needed
  - _Requirements: 6.1, 6.2, 6.3, 6.4, 6.5_

- [x] 9. Add navigation links
  - Add "Templates" link to main navigation menu
  - Ensure link is visible to authenticated users
  - _Requirements: 2.1_

- [ ] 10. Add success/error messaging
  - Implement flash messages for create, update, delete operations
  - Display validation errors on form pages
  - Show user-friendly error messages for authorization failures
  - _Requirements: 1.5, 3.5, 4.3_
