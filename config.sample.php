<?php
/*
 * Copy this file to config.php and fill in your own values.
 * config.php is ignored by Git so your database password never reaches GitHub.
 *
 * XAMPP (local):  host 'localhost', user 'root', empty password.
 * InfinityFree:   use the MySQL host, database name, username and password
 *                 shown under "MySQL Databases" in the InfinityFree control panel.
 */
return [
    'db' => [
        'host' => 'localhost',
        'port' => 3306,
        'name' => 'uhs_appointments',
        'user' => 'root',
        'pass' => '',
    ],

    // Leave as null to detect the folder automatically.
    // Set it only if links break, e.g. '/Appointment-Scheduling-System' on XAMPP or '' on InfinityFree.
    'base_url' => null,

    'app_name' => 'UHS Appointments',
    'org_name' => 'University Health Service, University of Ibadan',
    'timezone' => 'Africa/Lagos',

    // How far ahead patients can book, in days.
    'booking_window_days' => 30,

    // Patients cannot cancel or reschedule an appointment this close to its start time (hours).
    'change_cutoff_hours' => 2,

    // Show detailed errors. Keep false on a public server.
    'debug' => false,
];
