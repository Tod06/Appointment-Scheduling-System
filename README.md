# UHS Appointment Scheduling System

A web-based appointment scheduling system for the University Health Service (UHS), University of Ibadan.
Group 29, Department of Computer Science.

Patients register, choose a clinic, doctor, date and free time, and follow their request until it is confirmed.
Doctors review requests and manage their schedule. Administrators manage patients, doctors and appointments,
and export reports.

## Features

| Requirement | Where it lives |
| --- | --- |
| FR1 Register and sign in | `register.php`, `login.php` (patients sign in with email or matric number) |
| FR2 View clinic and doctor schedules | `index.php`, `patient/book.php` (each doctor's clinic, days, hours and free slots) |
| FR3 Request an appointment | `patient/book.php`, checked and locked in `includes/appointments.php` |
| FR4 View, cancel or reschedule | `patient/appointments.php`, `appointment.php`, `patient/book.php?reschedule=` |
| FR5 Doctor schedule | `doctor/dashboard.php`, `doctor/calendar.php`, `doctor/appointments.php` |
| FR6 Approve or update status | `appointment.php` and the quick actions on `doctor/dashboard.php` |
| FR7 Manage patients, doctors, appointments | `admin/patients.php`, `admin/doctors.php`, `admin/appointments.php` |
| FR8 Summaries and reports | `admin/dashboard.php`, `admin/reports.php` (print and CSV export) |
| FR9 Notifications and reminders | `notifications.php`; reminders are created for approved visits within 24 hours |

Also included: week calendar views, doctor availability settings, a full status history for every appointment,
an administrator audit log, and sign-in throttling.

**Database entities (Table 3.2, Figure 3.4):** `patients` (patient_id, matric_no, name, email, phone),
`doctors` (doctor_id, name, clinic, specialty), `appointments` (appointment_id, patient_id, doctor_id, clinic_type,
appointment_date, appointment_time, status, reason) and `admins` (admin_id, name, email, password_hash).
The clinic types a patient can choose come from the clinic field of the doctors the administrator adds.

**Appointment statuses:** Pending, Approved, Rescheduled, Completed, Cancelled.

## Technology

- HTML, CSS and JavaScript (no frameworks) for the presentation layer
- PHP 8.0 or newer for the application layer
- MySQL 5.7+ or MariaDB 10.3+ for the database layer
- XAMPP for local development, InfinityFree for hosting

```
├── index.php, login.php, register.php, logout.php
├── appointment.php        One appointment, shared by all roles
├── notifications.php, account.php
├── patient/               Patient pages
├── doctor/                Doctor pages
├── admin/                 Administrator pages
├── api/slots.php          Free time slots as JSON for the booking page
├── includes/              Shared PHP: database, sign-in, rules, layout
├── assets/                CSS and JavaScript
├── database/schema.sql    Database tables
└── install.php            One-time setup
```

## Run locally with XAMPP

1. Install XAMPP and start **Apache** and **MySQL** from the XAMPP Control Panel.
2. Copy this folder into `C:\xampp\htdocs\`, so you have `C:\xampp\htdocs\Appointment-Scheduling-System`.
3. Open http://localhost/phpmyadmin, click **New**, and create a database called `uhs_appointments`.
4. In the project folder, copy `config.sample.php` to `config.php`. The defaults already match XAMPP.
5. Open http://localhost/Appointment-Scheduling-System/install.php and create the administrator account.
6. Sign in at http://localhost/Appointment-Scheduling-System/login.php as the administrator and add the doctors
   under **Doctors**, with each one's clinic, specialty, consulting days and hours, and a password.
   Patients can book once at least one doctor is added.

## Deploy to InfinityFree

1. In the InfinityFree control panel, open **MySQL Databases** and create a database.
   Note the **MySQL host**, **database name**, **username** and **password** shown there.
2. Copy `config.sample.php` to `config.php` on your computer and fill in those four values.
   Set `'base_url' => ''` because the site runs from the root of `htdocs`.
3. Upload the project files into the `htdocs` folder using the InfinityFree file manager or an FTP client
   such as FileZilla. Upload `config.php` too. It is not on GitHub, by design.
4. Visit `https://your-site.infinityfreeapp.com/install.php`, create the administrator account, then add the doctors.
   If setup cannot create the tables, import `database/schema.sql` in phpMyAdmin first, then run setup again.
5. Delete `install.php` from the server once you can sign in.

InfinityFree has no scheduled jobs and does not send email from PHP, so notifications and reminders are shown
inside the app. Reminders are created when a patient signs in or opens their dashboard.

## Security

- Passwords are hashed with `password_hash` (bcrypt) and never stored in plain text.
- Every database query uses prepared statements.
- Every form is protected with a CSRF token, and every page checks the user's role on the server.
- Patients only see their own appointments; doctors only see appointments booked with them.
- Booking locks the doctor's record while checking the slot, so two patients cannot take the same time.
- Five failed sign-ins for the same account lock it for 15 minutes.
- Sessions use HTTP-only, SameSite cookies and expire after two hours of inactivity.
- `includes/`, `database/` and `config.php` are blocked from direct access by `.htaccess`.

This is an academic project. Using it with real patient data would need an institutional privacy and security review first.

## Images

- University of Ibadan logo (`assets/img/ui-logo.png`), and the crest cut from it (`ui-crest.png`, `favicon.png`).
- Photographs from [Unsplash](https://unsplash.com), free to use under the [Unsplash License](https://unsplash.com/license):
  - `hero-doctor.jpg` by Eben Kassaye
  - `consultation.jpg` by Francisco Venâncio
  - `signin-doctor.jpg` by the National Cancer Institute
  - `register-doctor.jpg` by Patrick Amofah

## Group 29

| Matriculation number | Name | Role |
| --- | --- | --- |
| E051808 | Odunlami, Jesutofunmi | Group Leader |
| E051782 | Akanni, Oluwatimileyin | Member |
| E051788 | Adetunji, Richard | Member |
| E051793 | Adelakun, Tobi | Member |
| E051798 | Adekola, Abdulmaleek | Member |
