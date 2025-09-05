# Implementation Plan: User Administration UI

**Goal:** Create a simple UI for administrators to manage users and their roles, with a design and user experience consistent with the existing exercises management interface.

## Phase 1: Foundational Role Management

1.  **Create User Roles (Migration & Model):**
    *   **Action:**
        *   Create a `roles` table with `id` and `name` (e.g., 'Admin', 'Athlete').
        *   Create a `role_user` pivot table to establish a many-to-many relationship between `users` and `roles`.
        *   Update the `User` model to include a `roles()` relationship.
        *   Create a `Role` model.
    *   **Considerations:**
        *   Seed the `roles` table with the 'Admin' and 'Athlete' roles.
        *   By default, new users should be assigned the 'Athlete' role.

2.  **Develop Admin Middleware:**
    *   **Action:** Create a middleware `IsAdmin` to restrict access to admin-only sections. The middleware will check if the authenticated user has the 'Admin' role.
    *   **Testing:** Write a test to ensure the middleware correctly grants and denies access.

## Phase 2: User Management UI

1.  **Create User Controller:**
    *   **Action:** Generate a new `UserController` to handle the display and management of users. This will be protected by the `IsAdmin` middleware.
    *   **Methods:**
        *   `index`: List all users with their roles.
        *   `edit`: Show a form to edit a user's roles.
        *   `update`: Handle the form submission to update roles.

2.  **Build the User Index View:**
    *   **Action:** Create a Blade view `admin/users/index.blade.php`.
    *   **UI/UX:**
        *   Mimic the layout of the `exercises.index` view.
        *   Display a table of users with columns for Name, Email, and Roles.
        *   Include an 'Edit' button for each user.
        *   Add a link in the main navigation for 'User Admin', visible only to admins.

3.  **Build the User Edit View:**
    *   **Action:** Create a Blade view `admin/users/edit.blade.php`.
    *   **UI/UX:**
        *   Display the user's name and email (read-only).
        *   Provide a multi-select dropdown or checkboxes to assign roles ('Admin', 'Athlete').
        *   Include a 'Save' button.

## Phase 3: Testing and Integration

1.  **Write Feature Tests:**
    *   **Action:** Create a `UserManagementTest` feature test.
    *   **Test Cases:**
        *   An admin can view the user list.
        *   A non-admin (Athlete) is redirected from the user list.
        *   An admin can access the edit page for a user.
        *   An admin can assign and unassign roles to a user.
        *   The default role for a new user is 'Athlete'.

2.  **Update Seeders:**
    *   **Action:** Modify the `UserSeeder` to assign the 'Admin' role to the default admin user.

**Testing Strategy:**

*   **Feature Tests:** Focus on end-to-end flows, ensuring that an admin can perform all management tasks and that a non-admin cannot.
*   **Manual Testing:** Perform a manual check of the UI to ensure it is consistent with the exercises UI and that the user experience is intuitive.
