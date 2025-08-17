# Nutrition Tracker Application

## Overview

This is a simple web application built with Laravel that allows users to track their daily nutrition intake. It provides features for managing a list of ingredients and logging daily food consumption, with a focus on macro and micronutrient tracking.

## Features

-   **Daily Nutrition Logging:** Users can add daily log entries, specifying the ingredient, quantity, and unit consumed.
-   **Ingredient Management (CRUD):** A full Create, Read, Update, and Delete (CRUD) interface for managing a comprehensive list of ingredients, including their nutritional values (calories, protein, carbs, fats, sodium, iron, potassium, added sugars).
-   **Date-Based Log Viewing:** View daily log entries and calculated macro totals for specific dates, with easy navigation between days.
-   **Nutrient Totals:** Automatically calculates and displays daily totals for various macronutrients and micronutrients based on logged entries.
-   **Responsive Navigation:** A clear navigation menu allows switching between the Daily Log and Ingredient Admin sections, with the active menu item highlighted.
-   **Alphabetical Ingredient Listing:** Ingredients are displayed alphabetically for easy browsing and management.

## Technologies Used

-   **Backend:** Laravel (PHP Framework)
-   **Database:** SQLite (for simplicity in development, easily configurable for others like MySQL, PostgreSQL)
-   **Frontend:** Blade Templates (Laravel's templating engine) with basic CSS for styling.
-   **Date Handling:** Carbon (PHP API extension for DateTime)

## Setup Instructions

Follow these steps to get the application up and running on your local machine.

### Prerequisites

-   PHP >= 8.2
-   Composer
-   Node.js & npm (or Yarn)
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
    # OR yarn install
    ```

4.  **Create a copy of your environment file:**
    ```bash
    cp .env.example .env
    ```

5.  **Generate an application key:**
    ```bash
    php artisan key:generate
    ```

6.  **Configure your database:**
    By default, the application uses SQLite. Ensure the `database.sqlite` file exists in the `database` directory. If not, create it:
    ```bash
    touch database/database.sqlite
    ```
    If you wish to use a different database (e.g., MySQL), update your `.env` file accordingly.

7.  **Run database migrations and seed the database:**
    This will create the necessary tables and populate them with initial data (ingredients, units, and sample daily logs).
    ```bash
    php artisan migrate:fresh --seed
    ```

8.  **Compile frontend assets:**
    ```bash
    npm run dev
    # OR yarn dev
    ```

9.  **Start the Laravel development server:**
    ```bash
    php artisan serve
    ```

## Usage

After starting the development server, open your web browser and navigate to `http://127.0.0.1:8000` (or the address displayed in your terminal).

-   Use the navigation bar at the top to switch between **Daily Log** and **Ingredient Admin**.
-   In the **Daily Log** section, you can add new entries and view logs and macro totals for different dates.
-   In the **Ingredient Admin** section, you can manage your list of ingredients (add, edit, delete).

## Contributing

Feel free to fork the repository, make improvements, and submit pull requests. For major changes, please open an issue first to discuss what you would like to change.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).