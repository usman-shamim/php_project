# 💅 Elegance Salon Management System

A comprehensive, role-based web application designed to manage the day-to-day operations of a modern hair salon, including appointments, client records, staff scheduling, and service management.

## 🚀 Features

The system is built with secure, role-based access control to ensure staff members only interact with the tools relevant to their position.

### Public Features

  * **Public Appointment Booking:** Clients can book services with a preferred stylist directly via `public_booking.php`.
  * **Feedback Submission:** Clients and users can submit feedback via `feedback.php`.

### Role-Based Staff Portal

| Role | Key Modules (Files) | Responsibilities |
| :--- | :--- | :--- |
| **Admin** | `admin/` folder (e.g., `inventory_manage.php`, `users_manage.php`, `reports_analytics.php`) | Full control over the system: manage all staff, clients, services, inventory, and access reports/analytics. |
| **Receptionist** | `receptionist/` folder (e.g., `appointments_manage.php`, `clients_manage.php`) | Primary booking and client interface: manage appointments, create/edit client records, and handle cancellations. |
| **Stylist** | `stylist/appointments_view.php` | Dedicated view to see their personal daily/weekly schedule and manage their assigned appointments. |

-----

## 🛠️ Tech Stack

  * **Backend:** PHP (Native PHP, no framework)
  * **Database:** MySQL (using `mysqli` with prepared statements for security)
  * **Frontend:** HTML5, CSS3, JavaScript
  * **Styling:** **Bootstrap 5** (utilizing the "Creative" theme aesthetic)
  * **Security:** Password hashing (`password_verify`), Prepared Statements (SQL Injection prevention).

-----

## ⚙️ Installation and Setup

Follow these steps to set up the Salon Management System on your local machine.

### Prerequisites

You need a local web server environment that supports PHP and MySQL (e.g., **XAMPP, WAMP, MAMP, or Docker**).

### 1\. Clone the Repository

```bash
git clone [YOUR-REPOSITORY-URL-HERE]
cd elegance-salon-management
```

### 2\. Database Setup

1.  Open your database management tool (e.g., phpMyAdmin).
2.  Create a new database named `salon_management`.
3.  Import the schema and initial data by loading the `saloon.sql` file:
    ```bash
    # (Optional, run this command in your terminal if MySQL is in PATH)
    mysql -u [YOUR_DB_USER] -p salon_management < sql/saloon.sql
    ```

### 3\. Configuration

Edit the main connection file to match your local database credentials:

Open `db_connect.php` and update the constants:

```php
// db_connect.php

define('DB_SERVER', 'localhost');
define('DB_USERNAME', 'root'); // Your MySQL Username
define('DB_PASSWORD', '');    // Your MySQL Password
define('DB_NAME', 'salon_management');
```

> **Note:** The existing file is configured with secure error handling and `utf8mb4` character set. Do not modify the secure structure.

### 4\. Setup Authentication (Optional)

You must ensure that at least one user exists in the `users` table with the `admin` role and a correctly hashed password (using `password_hash()`). You can use `config/pass.php` as a utility script to generate hashes for testing.

### 5\. Run the Application

Start your Apache and MySQL services (via XAMPP, etc.). Access the application in your web browser:

  * **Staff Login:** `http://localhost/elegance-salon-management/index.php` (or `login.php`)
  * **Public Booking:** `http://localhost/elegance-salon-management/public_booking.php`

-----

## 📂 Project Structure

This overview shows the core components of the application:

```
C:.
├───admin/                  # Administration and Reporting Modules
│       inventory_manage.php # Admin: Manage products/stock
│       reports_analytics.php  # Admin: Financials/performance
│       users_manage.php     # Admin: Create/manage staff accounts
│       ...
├───assets/                 # Static Assets (Images, Icons, etc.)
│   └───img/                 # Images used for styling and user profiles
├───config/                 # Core System Configuration
│       functions.php        # Global utility functions (e.g., check_login)
│       pass.php             # Password hashing utility
├───css/                    # Styling files
│       styles.css           # Custom CSS over Bootstrap 5 (Creative Theme)
├───js/                     # Custom JavaScript files
│       scripts.js
├───receptionist/           # Receptionist Role Modules
│       appointments_manage.php
│       clients_manage.php
│       ...
├───sql/                    # Database Files
│       saloon.sql           # Database schema and initial data
├───stylist/                # Stylist Role Modules
│       appointments_view.php  # Stylist's personal schedule view
└───template/               # Shared UI Components
        footer.php           # Common footer with JS/closing tags
        header.php           # Common header with CSS/Bootstrap links
```
