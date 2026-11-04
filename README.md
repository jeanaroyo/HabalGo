# HabalGo - Ride and Booking Management System

![PostgreSQL](https://img.shields.io/badge/PostgreSQL-15%2B-blue.svg)
![PHP](https://img.shields.io/badge/PHP-8.x-777BB4.svg)
![License](https://img.shields.io/badge/License-MIT-green.svg)

---

## Project Overview

**HabalGo** is a centralized relational database-driven ride-hailing and delivery management system designed to streamline customer bookings, driver assignments, vehicle operations, locations, payments, and customer feedback. Inspired by popular local ride-booking applications in the Philippines (such as Joyride, Angkas, and Move It), HabalGo enforces strong relational data integrity and real-world business rules using PostgreSQL.

- Eliminate record duplication and booking assignment conflicts.
- Centralize transactional data across customers, drivers, vehicles, locations, and payments.
- Enforce strict relational constraints and database-level validation using PostgreSQL.
- Provide an interactive 3-tier architecture with CRUD capabilities.

---

## Prerequisites
1. **PostgreSQL Server (Version 15 or higher):** Installed and running locally on port 5432.
2. **PHP (Version 8.0 or higher):** With the `pdo_pgsql` extension enabled so PHP can talk to PostgreSQL.
3. **Web Server:** Either Apache (via XAMPP/WAMP) or the PHP Built-in Web Server.
4. **Git:** For cloning and managing your repository.

---
## Installation Guide

### Step 1: Clone the Repository
```bash
git clone [https://github.com/jeanaroyo/HabalGo.git](https://github.com/jeanaroyo/HabalGo.git)
cd HabalGo 
```

### Step 2: Environment Configuration
Copy .env.example to create your local .env configuration file:
```bash
cp .env.example .env
```

Open `.env` and configure your local PostgreSQL database credentials:
```bash
DB_HOST=localhost
DB_PORT=5432
DB_NAME=grp00_habalgodb
DB_SCHEMA=habalgo
DB_USER=postgres
DB_PASS=your_postgres_password
```

Quick Reminder: Make sure to enable your pdo_pgsql extension! If not then do this steps:
1. Open XAMPP Control Panel
2. Click Apache Config
3. Go to PHP.ini
4. Search for `extension=pdo_pgsql` and `extension=pgsql` and remove the ;
5. Save and close

### Step 3: Initialize the PostgreSQL Database
1. Create Database:
```bash
CREATE DATABASE grp00_habalgodb;
```

3. Connect to the Database and Run Schema:
```bash
psql -U postgres -d grp00_habalgodb -f schema.sql
```

5. Load Seed / Sample Data:
```bash
psql -U postgres -d grp00_habalgodb -f "sample data.sql"
```

### Step 4: Run the Application

#### Option A: Using PHP Built-in Server (Easiest)
Run the following command in the project root folder:
```
php -S localhost:8000
```
Open your browser and navigate to: `http://localhost:8000`

#### Option B: Using XAMPP / htdocs
1. Move the HabalGo folder inside your xampp/htdocs/ directory.
2. Start Apache in XAMPP Control Panel.
3. Open your browser and navigate to: `http://localhost/HabalGo`
