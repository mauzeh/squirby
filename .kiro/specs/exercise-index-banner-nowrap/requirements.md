# Requirements Document

## Introduction

This feature addresses a visual issue on the exercise index page where banner badges displaying "Global" or user names can wrap to multiple lines when the text contains spaces. This creates an inconsistent and unprofessional appearance, particularly for user names that contain spaces. The solution will ensure these badges always display on a single line for better visual consistency and readability.

## Requirements

### Requirement 1

**User Story:** As a user viewing the exercise index page, I want the exercise badges ("Global" or user names) to always display on a single line inline to the left of the exercise name, so that the interface looks clean and professional regardless of the length or content of user names.

#### Acceptance Criteria

1. WHEN a user views the exercise index page THEN all exercise badges SHALL display on a single line without wrapping
2. WHEN an exercise badge contains a user name with spaces THEN the badge SHALL NOT break the text across multiple lines
3. WHEN an exercise badge displays "Global" THEN it SHALL remain on a single line (already working but must be preserved)
4. WHEN the badge text is too long for the available space THEN it SHALL use appropriate CSS overflow handling rather than wrapping
5. WHEN an exercise badge is displayed THEN it SHALL appear inline to the left of the exercise name rather than after it

### Requirement 2

**User Story:** As a user with a long name containing spaces, I want my name to display properly in exercise badges positioned to the left of the exercise name, so that the interface remains visually consistent and my name is clearly identifiable.

#### Acceptance Criteria

1. WHEN a user has a name longer than the typical badge width THEN the badge SHALL handle the overflow gracefully
2. WHEN a user name contains multiple words THEN all words SHALL remain on the same line within the badge
3. WHEN the badge text overflows THEN it SHALL either truncate with ellipsis or expand the badge width as appropriate
4. WHEN the badge is positioned to the left of the exercise name THEN there SHALL be appropriate spacing between the badge and the exercise name

### Requirement 3

**User Story:** As a developer maintaining the exercise index page, I want the badge styling to be consistent and maintainable, so that future changes don't accidentally reintroduce the wrapping issue or positioning problems.

#### Acceptance Criteria

1. WHEN implementing the fix THEN the solution SHALL use CSS best practices for preventing text wrapping and proper inline positioning
2. WHEN the badge styles are updated THEN they SHALL maintain visual consistency with the existing design while improving the layout
3. WHEN viewing the page on different screen sizes THEN the badges SHALL maintain their no-wrap behavior and left positioning across all responsive breakpoints
4. WHEN the badge is repositioned to the left THEN the overall table layout SHALL remain intact and readable