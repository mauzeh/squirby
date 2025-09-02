## Implementation Plan: Advanced Access Control & ACL Management

**Goal:** Implement a flexible and robust role-based access control (RBAC) system to manage user permissions.

**Phase 1: Core ACL Implementation**

1.  **Integrate `spatie/laravel-permission` Package:**
    *   **Action:** Install and configure the `spatie/laravel-permission` package.
    *   **Considerations:**
        *   Publish the package's migrations and configuration files.
        *   Update the `User` model to use the `HasRoles` trait.

2.  **Define Initial Roles and Permissions:**
    *   **Action:** Update the existing seeders to define the initial roles (e.g., `admin`, `athlete`) and permissions.
    *   **Considerations:**
        *   Permissions should be granular and follow a consistent naming convention (e.g., `resource.action`).
        *   The `admin` role should be assigned all permissions by default.

3.  **Implement Middleware for Route Protection:**
    *   **Action:** Apply the `role` and `permission` middleware from the `spatie/laravel-permission` package to the application's routes.
    *   **Considerations:**
        *   Protect admin-only routes with `role:admin`.
        *   Protect other routes with appropriate permission middleware.

**Phase 2: Admin Panel for ACL Management**

1.  **Create Admin Panel for Role Management:**
    *   **Action:** Create a new section in the application for administrators to manage roles.
    *   **Considerations:**
        *   Implement CRUD functionality for roles.
        *   Allow assigning permissions to roles.

2.  **Create Admin Panel for User Management:**
    *   **Action:** Create a new section in the application for administrators to manage users.
    *   **Considerations:**
        *   Implement CRUD functionality for users.
        *   Allow assigning roles to users.

**Phase 3: User Impersonation**

1.  **Implement User Impersonation:**
    *   **Action:** Add a feature to allow administrators to log in as any other user.
    *   **Considerations:**
        *   This can be implemented using a package like `lab404/laravel-impersonate`.
        *   Add a button to the user management panel to impersonate a user.
        *   Display a clear indicator when an admin is impersonating another user.
        *   Provide a way to stop impersonating and return to the admin account.

**Permissions List:**

*   `users.view`
*   `users.create`
*   `users.update`
*   `users.delete`
*   `roles.view`
*   `roles.create`
*   `roles.update`
*   `roles.delete`
*   `permissions.view`
*   `permissions.assign`
*   `daily-logs.view`
*   `daily-logs.create`
*   `daily-logs.update`
*   `daily-logs.delete`
*   `meals.view`
*   `meals.create`
*   `meals.update`
*   `meals.delete`
*   `ingredients.view`
*   `ingredients.create`
*   `ingredients.update`
*   `ingredients.delete`
*   `workouts.view`
*   `workouts.create`
*   `workouts.update`
*   `workouts.delete`
*   `exercises.view`
*   `exercises.create`
*   `exercises.update`
*   `exercises.delete`
*   `measurement-logs.view`
*   `measurement-logs.create`
*   `measurement-logs.update`
*   `measurement-logs.delete`
*   `measurement-types.view`
*   `measurement-types.create`
*   `measurement-types.update`
*   `measurement-types.delete`
*   `impersonate.start`
*   `impersonate.stop`

**UI/UX Considerations:**

*   All new features should be integrated into the existing UI, views, and Blade templates.
*   The user experience should be seamless and consistent with the rest of the application.
*   Admin features should be clearly separated and only visible to users with the `admin` role.

**Testing Strategy:**

To ensure the robustness and correctness of the ACL implementation, a comprehensive testing strategy will be employed, focusing on both positive (allowed actions) and negative (forbidden actions) scenarios for each permission.

*   **Unit Tests:**
    *   Test that roles and permissions can be created and assigned correctly.
    *   Test that the `HasRoles` trait is working as expected on the `User` model.
    *   Test any custom logic related to roles and permissions.

*   **Feature Tests (Granular Permission Testing Needs):**

    For every permission, we need to ensure:
    *   A user *with* the specific permission can successfully perform the action(s) associated with that permission.
    *   A user *without* the specific permission *cannot* perform the action(s) associated with that permission (typically resulting in a 403 Forbidden HTTP status code).

    **A. User Management Permissions (`users.*`)**
    *   **`users.view`**:
        *   **Positive:** Test that a user assigned `users.view` can access:
            *   `GET /admin/users` (admin.users.index)
            *   `GET /admin/users/{user}` (admin.users.show - if implemented)
        *   **Negative:** Test that a user *without* `users.view` cannot access these routes (assert 403).
    *   **`users.create`**:
        *   **Positive:** Test that a user assigned `users.create` can access:
            *   `GET /admin/users/create` (admin.users.create)
            *   `POST /admin/users` (admin.users.store)
        *   **Negative:** Test that a user *without* `users.create` cannot access these routes (assert 403).
    *   **`users.update`**:
        *   **Positive:** Test that a user assigned `users.update` can access:
            *   `GET /admin/users/{user}/edit` (admin.users.edit)
            *   `PUT/PATCH /admin/users/{user}` (admin.users.update)
        *   **Negative:** Test that a user *without* `users.update` cannot access these routes (assert 403).
    *   **`users.delete`**:
        *   **Positive:** Test that a user assigned `users.delete` can access:
            *   `DELETE /admin/users/{user}` (admin.users.destroy)
        *   **Negative:** Test that a user *without* `users.delete` cannot access this route (assert 403).

    **B. Role Management Permissions (`roles.*`)**
    *   **`roles.view`**:
        *   **Positive:** Test that a user assigned `roles.view` can access:
            *   `GET /admin/roles` (admin.roles.index)
            *   `GET /admin/roles/{role}` (admin.roles.show - if implemented)
        *   **Negative:** Test that a user *without* `roles.view` cannot access these routes (assert 403).
    *   **`roles.create`**:
        *   **Positive:** Test that a user assigned `roles.create` can access:
            *   `GET /admin/roles/create` (admin.roles.create)
            *   `POST /admin/roles` (admin.roles.store)
        *   **Negative:** Test that a user *without* `roles.create` cannot access these routes (assert 403).
    *   **`roles.update`**:
        *   **Positive:** Test that a user assigned `roles.update` can access:
            *   `GET /admin/roles/{role}/edit` (admin.roles.edit)
            *   `PUT/PATCH /admin/roles/{role}` (admin.roles.update)
        *   **Negative:** Test that a user *without* `roles.update` cannot access these routes (assert 403).
    *   **`roles.delete`**:
        *   **Positive:** Test that a user assigned `roles.delete` can access:
            *   `DELETE /admin/roles/{role}` (admin.roles.destroy)
        *   **Negative:** Test that a user *without* `roles.delete` cannot access this route (assert 403).

    **C. Permission Management Permissions (`permissions.*`)**
    *   **`permissions.view`**:
        *   **Positive:** Test that a user assigned `permissions.view` can access:
            *   `GET /admin/permissions` (admin.permissions.index)
            *   `GET /admin/permissions/{permission}` (admin.permissions.show - if implemented)
        *   **Negative:** Test that a user *without* `permissions.view` cannot access these routes (assert 403).
    *   **`permissions.assign`**:
        *   **Positive:** Test that a user assigned `permissions.assign` can access:
            *   `GET /admin/permissions/create` (admin.permissions.create)
            *   `POST /admin/permissions` (admin.permissions.store)
            *   `GET /admin/permissions/{permission}/edit` (admin.permissions.edit)
            *   `PUT/PATCH /admin/permissions/{permission}` (admin.permissions.update)
            *   `DELETE /admin/permissions/{permission}` (admin.permissions.destroy)
        *   **Negative:** Test that a user *without* `permissions.assign` cannot access these routes (assert 403).

    **D. Daily Log Permissions (`daily-logs.*`)**
    *   **`daily-logs.view`**:
        *   **Positive:** Test that a user assigned `daily-logs.view` can access:
            *   `GET /daily-logs` (daily-logs.index)
            *   `GET /daily-logs/type/{measurementType}` (daily-logs.show-by-type)
        *   **Negative:** Test that a user *without* `daily-logs.view` cannot access these routes (assert 403).
    *   **`daily-logs.create`**:
        *   **Positive:** Test that a user assigned `daily-logs.create` can access:
            *   `GET /daily-logs/create` (daily-logs.create - if implemented)
            *   `POST /daily-logs` (daily-logs.store)
            *   `POST /daily-logs/add-meal` (daily-logs.add-meal)
            *   `POST /daily-logs/import-tsv` (daily-logs.import-tsv)
        *   **Negative:** Test that a user *without* `daily-logs.create` cannot access these routes (assert 403).
    *   **`daily-logs.update`**:
        *   **Positive:** Test that a user assigned `daily-logs.update` can access:
            *   `GET /daily-logs/{daily_log}/edit` (daily-logs.edit)
            *   `PUT/PATCH /daily-logs/{daily_log}` (daily-logs.update)
        *   **Negative:** Test that a user *without* `daily-logs.update` cannot access these routes (assert 403).
    *   **`daily-logs.delete`**:
        *   **Positive:** Test that a user assigned `daily-logs.delete` can access:
            *   `DELETE /daily-logs/{daily_log}` (daily-logs.destroy)
            *   `POST /daily-logs/destroy-selected` (daily-logs.destroy-selected)
        *   **Negative:** Test that a user *without* `daily-logs.delete` cannot access these routes (assert 403).

    **E. Meal Permissions (`meals.*`)**
    *   **`meals.view`**:
        *   **Positive:** Test that a user assigned `meals.view` can access:
            *   `GET /meals` (meals.index)
        *   **Negative:** Test that a user *without* `meals.view` cannot access this route (assert 403).
    *   **`meals.create`**:
        *   **Positive:** Test that a user assigned `meals.create` can access:
            *   `GET /meals/create` (meals.create - if implemented)
            *   `POST /meals` (meals.store)
            *   `POST /meals/create-from-logs` (meals.create-from-logs)
        *   **Negative:** Test that a user *without* `meals.create` cannot access these routes (assert 403).
    *   **`meals.update`**:
        *   **Positive:** Test that a user assigned `meals.update` can access:
            *   `GET /meals/{meal}/edit` (meals.edit)
            *   `PUT/PATCH /meals/{meal}` (meals.update)
        *   **Negative:** Test that a user *without* `meals.update` cannot access these routes (assert 403).
    *   **`meals.delete`**:
        *   **Positive:** Test that a user assigned `meals.delete` can access:
            *   `DELETE /meals/{meal}` (meals.destroy)
        *   **Negative:** Test that a user *without* `meals.delete` cannot access this route (assert 403).

    **F. Ingredient Permissions (`ingredients.*`)**
    *   **`ingredients.view`**:
        *   **Positive:** Test that a user assigned `ingredients.view` can access:
            *   `GET /ingredients` (ingredients.index)
        *   **Negative:** Test that a user *without* `ingredients.view` cannot access this route (assert 403).
    *   **`ingredients.create`**:
        *   **Positive:** Test that a user assigned `ingredients.create` can access:
            *   `GET /ingredients/create` (ingredients.create - if implemented)
            *   `POST /ingredients` (ingredients.store)
        *   **Negative:** Test that a user *without* `ingredients.create` cannot access these routes (assert 403).
    *   **`ingredients.update`**:
        *   **Positive:** Test that a user assigned `ingredients.update` can access:
            *   `GET /ingredients/{ingredient}/edit` (ingredients.edit)
            *   `PUT/PATCH /ingredients/{ingredient}` (ingredients.update)
        *   **Negative:** Test that a user *without* `ingredients.update` cannot access these routes (assert 403).
    *   **`ingredients.delete`**:
        *   **Positive:** Test that a user assigned `ingredients.delete` can access:
            *   `DELETE /ingredients/{ingredient}` (ingredients.destroy)
        *   **Negative:** Test that a user *without* `ingredients.delete` cannot access this route (assert 403).

    **G. Workout Permissions (`workouts.*`)**
    *   **`workouts.view`**:
        *   **Positive:** Test that a user assigned `workouts.view` can access:
            *   `GET /workouts` (workouts.index)
            *   `GET /workouts/{workout}` (workouts.show)
        *   **Negative:** Test that a user *without* `workouts.view` cannot access these routes (assert 403).
    *   **`workouts.create`**:
        *   **Positive:** Test that a user assigned `workouts.create` can access:
            *   `GET /workouts/create` (workouts.create - if implemented)
            *   `POST /workouts` (workouts.store)
            *   `POST /workouts/import-tsv` (workouts.import-tsv)
        *   **Negative:** Test that a user *without* `workouts.create` cannot access these routes (assert 403).
    *   **`workouts.update`**:
        *   **Positive:** Test that a user assigned `workouts.update` can access:
            *   `GET /workouts/{workout}/edit` (workouts.edit)
            *   `PUT/PATCH /workouts/{workout}` (workouts.update)
        *   **Negative:** Test that a user *without* `workouts.update` cannot access these routes (assert 403).
    *   **`workouts.delete`**:
        *   **Positive:** Test that a user assigned `workouts.delete` can access:
            *   `DELETE /workouts/{workout}` (workouts.destroy)
            *   `POST /workouts/destroy-selected` (workouts.destroy-selected)
        *   **Negative:** Test that a user *without* `workouts.delete` cannot access these routes (assert 403).

    **H. Exercise Permissions (`exercises.*`)**
    *   **`exercises.view`**:
        *   **Positive:** Test that a user assigned `exercises.view` can access:
            *   `GET /exercises` (exercises.index)
            *   `GET /exercises/{exercise}` (exercises.show)
            *   `GET /exercises/{exercise}/logs` (exercises.show-logs)
        *   **Negative:** Test that a user *without* `exercises.view` cannot access these routes (assert 403).
    *   **`exercises.create`**:
        *   **Positive:** Test that a user assigned `exercises.create` can access:
            *   `GET /exercises/create` (exercises.create - if implemented)
            *   `POST /exercises` (exercises.store)
        *   **Negative:** Test that a user *without* `exercises.create` cannot access these routes (assert 403).
    *   **`exercises.update`**:
        *   **Positive:** Test that a user assigned `exercises.update` can access:
            *   `GET /exercises/{exercise}/edit` (exercises.edit)
            *   `PUT/PATCH /exercises/{exercise}` (exercises.update)
        *   **Negative:** Test that a user *without* `exercises.update` cannot access these routes (assert 403).
    *   **`exercises.delete`**:
        *   **Positive:** Test that a user assigned `exercises.delete` can access:
            *   `DELETE /exercises/{exercise}` (exercises.destroy)
        *   **Negative:** Test that a user *without* `exercises.delete` cannot access this route (assert 403).

    **I. Measurement Log Permissions (`measurement-logs.*`)**
    *   **`measurement-logs.view`**:
        *   **Positive:** Test that a user assigned `measurement-logs.view` can access:
            *   `GET /measurement-logs` (measurement-logs.index)
            *   `GET /measurement-logs/type/{measurementType}` (measurement-logs.show-by-type)
        *   **Negative:** Test that a user *without* `measurement-logs.view` cannot access these routes (assert 403).
    *   **`measurement-logs.create`**:
        *   **Positive:** Test that a user assigned `measurement-logs.create` can access:
            *   `GET /measurement-logs/create` (measurement-logs.create - if implemented)
            *   `POST /measurement-logs` (measurement-logs.store)
            *   `POST /measurement-logs/import-tsv` (measurement-logs.import-tsv)
        *   **Negative:** Test that a user *without* `measurement-logs.create` cannot access these routes (assert 403).
    *   **`measurement-logs.update`**:
        *   **Positive:** Test that a user assigned `measurement-logs.update` can access:
            *   `GET /measurement-logs/{measurement_log}/edit` (measurement-logs.edit)
            *   `PUT/PATCH /measurement-logs/{measurement_log}` (measurement-logs.update)
        *   **Negative:** Test that a user *without* `measurement-logs.update` cannot access these routes (assert 403).
    *   **`measurement-logs.delete`**:
        *   **Positive:** Test that a user assigned `measurement-logs.delete` can access:
            *   `DELETE /measurement-logs/{measurement_log}` (measurement-logs.destroy)
            *   `POST /measurement-logs/destroy-selected` (measurement-logs.destroy-selected)
        *   **Negative:** Test that a user *without* `measurement-logs.delete` cannot access these routes (assert 403).

    **J. Measurement Type Permissions (`measurement-types.*`)**
    *   **`measurement-types.view`**:
        *   **Positive:** Test that a user assigned `measurement-types.view` can access:
            *   `GET /measurement-types` (measurement-types.index)
        *   **Negative:** Test that a user *without* `measurement-types.view` cannot access this route (assert 403).
    *   **`measurement-types.create`**:
        *   **Positive:** Test that a user assigned `measurement-types.create` can access:
            *   `GET /measurement-types/create` (measurement-types.create - if implemented)
            *   `POST /measurement-types` (measurement-types.store)
        *   **Negative:** Test that a user *without* `measurement-types.create` cannot access these routes (assert 403).
    *   **`measurement-types.update`**:
        *   **Positive:** Test that a user assigned `measurement-types.update` can access:
            *   `GET /measurement-types/{measurement_type}/edit` (measurement-types.edit)
            *   `PUT/PATCH /measurement-types/{measurement_type}` (measurement-types.update)
        *   **Negative:** Test that a user *without* `measurement-types.update` cannot access these routes (assert 403).
    *   **`measurement-types.delete`**:
        *   **Positive:** Test that a user assigned `measurement-types.delete` can access:
            *   `DELETE /measurement-types/{measurement_type}` (measurement-types.destroy)
        *   **Negative:** Test that a user *without* `measurement-types.delete` cannot access this route (assert 403).

    **K. Impersonation Permissions (`impersonate.*`)**
    *   **`impersonate.start`**:
        *   **Positive:** Test that a user with `impersonate.start` can access `GET /impersonate/{user_id}` (or the POST route if that's how it's triggered).
        *   **Negative:** Test that a user *without* `impersonate.start` cannot access this route (assert 403).
    *   **`impersonate.stop`**:
        *   **Positive:** Test that a user who is currently impersonating can access `GET /impersonate/leave`.
        *   **Negative:** Test that a user who is *not* impersonating cannot access this route (e.g., asserts redirect to home or 403).
