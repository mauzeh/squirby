# Requirements Document

## Introduction

This feature allows users to configure whether they see global exercises in the lift-logs mobile entry interface. The setting will be integrated into the existing user profile settings page, giving users control over their exercise selection experience.

## Glossary

- **Global_Exercise**: An exercise that is available to all users in the system, not created by the individual user
- **User_Exercise**: An exercise created by a specific user for their own use
- **Mobile_Entry_Interface**: The mobile-optimized interface for logging lift workouts
- **Profile_Settings**: The user configuration page accessible at /profile
- **Exercise_Visibility_Setting**: A user preference that controls whether global exercises appear in exercise selection interfaces

## Requirements

### Requirement 1

**User Story:** As a user, I want to configure whether I see global exercises in the mobile lift entry interface, so that I can customize my exercise selection experience based on my preferences.

#### Acceptance Criteria

1. WHEN a user accesses the profile settings page, THE Profile_Settings SHALL display a toggle option for global exercise visibility
2. WHEN a user enables the global exercise visibility setting, THE Mobile_Entry_Interface SHALL display both User_Exercise and Global_Exercise options in exercise selection
3. WHEN a user disables the global exercise visibility setting, THE Mobile_Entry_Interface SHALL display only User_Exercise options in exercise selection
4. WHEN a user changes the global exercise visibility setting, THE Profile_Settings SHALL persist the preference to the user's profile
5. THE Profile_Settings SHALL load the current global exercise visibility preference when the page is accessed

### Requirement 2

**User Story:** As a user, I want my global exercise visibility preference to be remembered across sessions, so that I don't have to reconfigure it every time I use the application.

#### Acceptance Criteria

1. WHEN a user logs out and logs back in, THE Mobile_Entry_Interface SHALL respect the previously saved global exercise visibility setting
2. WHEN a user updates their global exercise visibility preference, THE Profile_Settings SHALL immediately save the change to the database
3. THE Profile_Settings SHALL provide visual feedback when the global exercise visibility setting is successfully updated

### Requirement 3

**User Story:** As a new user, I want a sensible default for global exercise visibility, so that I have a good initial experience without needing to configure settings immediately.

#### Acceptance Criteria

1. WHEN a new user account is created, THE Profile_Settings SHALL set the global exercise visibility to enabled by default
2. WHEN a user has never configured the global exercise visibility setting, THE Mobile_Entry_Interface SHALL behave as if global exercises are enabled
3. THE Profile_Settings SHALL clearly indicate the current state of the global exercise visibility setting