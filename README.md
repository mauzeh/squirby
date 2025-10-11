# Nutrition and Fitness Tracker

## Overview

This is a comprehensive web application built with Laravel for tracking nutrition, workouts, and body measurements. It allows users to manage ingredients, log meals, track exercises and workout programs, and monitor body composition over time. The application supports multiple users with role-based access control, featuring a two-tier exercise management system that provides both a global library of exercises and personal exercise lists for each user.

## Core Features

### Dashboard
- The main entry point for logged-in users, providing a summary of their daily activity, including food logs, workouts, and measurements.

### Nutrition Tracking
- **Daily Food Logging:** Log daily food intake with the `FoodLog` feature, which allows you to track ingredients and quantities for each meal.
- **Ingredient Library:** Manage a personal library of `Ingredients` with detailed nutritional information, including calories, protein, carbohydrates, fats, and other micronutrients.
- **Meal Creation:** Assemble ingredients into `Meals` for quick and easy logging of frequently eaten foods.
- **Automated Calculations:** The system automatically calculates daily totals for calories and macronutrients based on your food logs.

### Workout Logging
- **Detailed Workout Logs:** Log your workouts with `LiftLog`, which captures details for each exercise, including comments and the date of the workout.
- **Set and Rep Tracking:** Track individual sets, reps, and weight for each exercise with `LiftSet`.
- **Progression Analysis:** View detailed logs for each exercise, including charts that visualize your progression over time.
- **One-Rep Max Calculation:** The application automatically calculates your one-rep max (1RM) for lifts to help you track your strength gains.

### Training Programs
- **Structured Programs:** Create and manage structured workout `Programs` to plan your training in advance.
- **Program Details:** Define exercises, sets, reps, and comments for each entry in your program.
- **Prioritization:** Prioritize programs to organize your daily workout view, ensuring you focus on the most important exercises.

### Body Measurement Tracking
- **Comprehensive Body Logs:** Log various body measurements, such as weight, body fat percentage, and waist circumference, with the `BodyLog` feature.
- **Custom Measurement Types:** Create and manage custom `MeasurementTypes` to track any metric you're interested in, providing flexibility for your personal health goals.

### Exercise Management
- **Two-Tier Exercise System:**
    - **Global Exercises:** A curated library of exercises managed by administrators, available to all users. This ensures a consistent and high-quality dataset.
    - **Personal Exercises:** Users can create their own custom exercises for personal use, allowing for flexibility and customization.
- **Admin Promotion:** Administrators have the ability to promote personal exercises to global status, making them available to the entire community.

### User and Admin Roles
- **Multi-User Environment:** The application is designed for multiple users, with role-based access control to manage permissions.
- **Admin Role:** Administrators can manage users, global exercises, and have access to all data within the application. They also have the ability to impersonate users for support and testing purposes.
- **User Role:** Regular users can manage their own data, including food logs, lift logs, personal exercises, and more.

### Data Import/Export
- **TSV Support:** Import and export data in TSV (Tab-Separated Values) format for a variety of data types, including:
    - Food Logs
    - Ingredients
    - Body Logs
    - Exercises
    - Lift Logs
    - Programs
- **Environment-Specific:** This feature is restricted to development and testing environments to ensure the security and integrity of production data.

## Technologies Used

-   **Backend:** Laravel (PHP Framework)
-   **Database:** SQLite (default), MySQL, or PostgreSQL.
-   **Frontend:**
    -   Blade Templates
    -   Vite
    -   Tailwind CSS
    -   PostCSS
    -   JavaScript
-   **Date Handling:** Carbon

## Setup Instructions

Follow these steps to get the application up and running on your local machine.

### Prerequisites

-   PHP >= 8.2
-   Composer
-   Node.js & npm
-   Git

### Installation

1.  **Clone the repository:**
    ```bash
    git clone <repository_url>
    cd nutrition
    ```

2.  **Install Composer dependencies:**
    ```bash
    composer install
    ```

3.  **Install Node.js dependencies:**
    ```bash
    npm install
    ```

4.  **Create your environment file:**
    ```bash
    cp .env.example .env
    ```

5.  **Generate an application key:**
    ```bash
    php artisan key:generate
    ```

6.  **Configure your database:**
    The application uses SQLite by default. Create the database file:
    ```bash
    touch database/database.sqlite
    ```
    Update your `.env` file if you want to use a different database like MySQL.

7.  **Run database migrations and seeders:**
    This will create all necessary tables and populate them with initial data.
    ```bash
    php artisan migrate:fresh --seed
    ```

8.  **Compile frontend assets:**
    ```bash
    npm run dev
    ```

9.  **Start the Laravel development server:**
    ```bash
    php artisan serve
    ```

## Usage

Once the server is running, access the application at `http://127.0.0.1:8000`. You can register a new user account and start logging your nutrition, workouts, and measurements.

## Contributing

Feel free to fork the repository, make improvements, and submit pull requests. For major changes, please open an issue first to discuss what you would like to change.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
