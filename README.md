# Internship Result Management System

A web-based Internship Result Management System built with PHP and MySQL for the
COMP1044 coursework.

Features:

- Role-based login for `Admin` and `Assessor` accounts
- Admin management of student records, internship assignments, and assessor accounts
- Assessor result entry with the eight fixed faculty weightages and an auto-calculated final mark
- Result viewing with search and filter support for both roles

## Main files

- `index.php` — login page (session-based authentication, CSRF protected).
- `student_management.php` — student profile and internship assignment page.
- `user_management.php` — admin page to create, edit, and delete assessor accounts.
- `result_entry.php` — assessment entry page with automatic final mark calculation.
- `results.php` — result viewing page for admin and assessors.
- `includes/bootstrap.php` — shared helpers (database, session, CSRF, role checks).
- `includes/layout.php` — shared header, navigation, and footer partials.
- `assets/styles.css` — stylesheet applied across every page.
- `config.php` — database connection settings.

The MySQL schema lives in `COMP1044_database.sql` at the root of the submission folder.

## Default accounts

- Admin: `admin01` / `Admin@123`
- Assessor: `assessor01` / `Assess@123`
- Assessor: `assessor02` / `Assess@123`

## MAMP run steps

1. Copy the project folder into the MAMP web root (for example `C:\MAMP\htdocs\database`).
2. Start `Apache` and `MySQL` in MAMP.
3. Open `phpMyAdmin` from MAMP and import `COMP1044_database.sql`.
4. Open `config.php` and confirm the database settings match your MAMP setup.
   Default MAMP values are usually `host=localhost`, `port=3306`, `username=root`, `password=root`.
5. In the browser, open `http://localhost:8888/database/index.php` (adjust the path to match the folder you copied into `htdocs`).
