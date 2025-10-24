# Requirements Document

## Introduction

This specification outlines the complete removal of all TSV (Tab-Separated Values) functionality from the application, including user-facing import features and TSV-based seeders. The goal is to simplify the application architecture, reduce maintenance overhead, and replace complex CSV/TSV-based seeders with minimal hardcoded datasets that provide essential functionality for development and testing.

## Glossary

- **User_Facing_TSV_Features**: TSV import functionality accessible through web controllers and UI forms
- **TSV_Services**: All TSV processing services including TsvImporterService, IngredientTsvProcessorService, and ProgramTsvImporterService
- **TsvImporterService**: The main service class handling user TSV imports across multiple data types
- **IngredientTsvProcessorService**: Core TSV processing utility used by both user imports and seeders
- **ProgramTsvImporterService**: Specialized service for program TSV imports
- **CSV_Based_Seeders**: Seeders that read from CSV files and use TSV processing services
- **Hardcoded_Seeders**: Replacement seeders with minimal, hardcoded datasets
- **TSV_Import_Routes**: Web routes that handle TSV import requests
- **TSV_Import_Forms**: UI forms that allow users to submit TSV data
- **Production_Middleware**: Middleware that restricts TSV imports in production environments

## Requirements

### Requirement 1

**User Story:** As a system administrator, I want to remove all TSV import functionality, so that the application architecture is simplified and maintenance overhead is reduced.

#### Acceptance Criteria

1. WHEN accessing any controller TSV import endpoint, THE System SHALL return a 404 error
2. WHEN viewing any data management page, THE System SHALL NOT display TSV import forms
3. WHEN the application starts, THE System SHALL NOT register TSV import routes
4. THE System SHALL remove all TSV processing services
5. THE System SHALL remove IngredientTsvProcessorService and related TSV utilities

### Requirement 2

**User Story:** As a developer, I want to replace TSV-based seeders with hardcoded minimal datasets, so that seeding is faster and doesn't depend on external CSV files or complex processing logic.

#### Acceptance Criteria

1. WHEN running IngredientSeeder, THE System SHALL create ingredients using hardcoded data arrays
2. WHEN running GlobalExercisesSeeder, THE System SHALL create exercises using hardcoded data arrays
3. THE System SHALL provide a minimal but functional set of ingredients for development
4. THE System SHALL provide a minimal but functional set of exercises for development
5. THE System SHALL complete seeding faster than CSV/TSV-based approaches

### Requirement 3

**User Story:** As a developer, I want to completely remove all TSV-related code, so that the codebase is clean and doesn't contain unused functionality.

#### Acceptance Criteria

1. WHEN reviewing controller classes, THE System SHALL NOT contain importTsv methods
2. WHEN reviewing route definitions, THE System SHALL NOT contain TSV import routes
3. WHEN reviewing view templates, THE System SHALL NOT contain TSV import forms
4. THE System SHALL remove all TSV service classes
5. THE System SHALL remove all TSV-related test files
6. THE System SHALL remove TSV import middleware classes
7. THE System SHALL remove CSV files used by seeders

### Requirement 4

**User Story:** As a system administrator, I want to ensure no TSV import functionality is accessible through the web interface, so that security and simplicity are maintained.

#### Acceptance Criteria

1. WHEN attempting to access removed TSV routes, THE System SHALL return appropriate HTTP error responses
2. WHEN reviewing the application UI, THE System SHALL show no TSV import options
3. THE System SHALL remove all TSV import validation rules from controllers
4. THE System SHALL remove TSV import success/error message handling
5. THE System SHALL clean up any TSV-related configuration or environment checks in controllers

### Requirement 5

**User Story:** As a developer, I want to ensure the hardcoded seeders provide sufficient data for development and testing, so that the application remains functional after TSV removal.

#### Acceptance Criteria

1. THE System SHALL provide at least 10 common ingredients with complete nutritional data
2. THE System SHALL provide at least 20 common exercises covering major muscle groups
3. THE System SHALL include both bodyweight and weighted exercises in the exercise dataset
4. THE System SHALL include ingredients with different units (grams, pieces, tablespoons, etc.)
5. THE System SHALL maintain the same seeder interface so existing workflows continue to work