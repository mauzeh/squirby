# Nutrition and Fitness Tracker

## Overview

This is a comprehensive web application built with Laravel for tracking nutrition, workouts, and body measurements. It allows users to manage ingredients, log meals, track exercises and workout programs, and monitor body composition over time. The application supports multiple users with role-based access control.

## Features

-   **Multi-User System:** Supports multiple users with admin and user roles.
-   **Nutrition Tracking:**
    -   Log daily food intake (`FoodLog`).
    -   Create and manage a library of `Ingredients` with detailed nutritional information.
    -   Assemble ingredients into `Meals` for quick logging.
    -   Calculates daily macro and micronutrient totals.
-   **Workout Logging:**
    -   Log workouts (`LiftLog`) with details for each exercise.
    -   Define and manage a list of `Exercises`, including bodyweight exercises.
    -   Track sets, reps, and weight for each exercise (`LiftSet`).
    -   Create and follow structured workout `Programs`.
-   **Body Measurement Tracking:**
    -   Log body measurements like weight, body fat, etc. (`BodyLog`).
    -   Define custom `MeasurementTypes`.
-   **CRUD Operations:** Full Create, Read, Update, and Delete functionality for all major features.
-   **Date-Based Reporting:** View logs and progress for specific dates and date ranges.

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