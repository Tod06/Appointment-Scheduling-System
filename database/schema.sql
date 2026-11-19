-- UHS Appointment Scheduling System
-- Database schema and sample data (MySQL 5.7+ / MariaDB 10.3+)
--
-- Import this file with phpMyAdmin (Import tab) into an empty database,
-- or let install.php run it for you. Re-running it is safe: tables are only
-- created when missing and sample rows are skipped when they already exist.

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS clinics (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(120) NOT NULL,
  description VARCHAR(255) NOT NULL DEFAULT '',
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_clinic_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS patients (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  matric_no VARCHAR(30) NULL,
  name VARCHAR(120) NOT NULL,
  email VARCHAR(190) NOT NULL,
  phone VARCHAR(20) NOT NULL DEFAULT '',
  password_hash VARCHAR(255) NOT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_patient_email (email),
  UNIQUE KEY uq_patient_matric (matric_no)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS doctors (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  clinic_id INT UNSIGNED NOT NULL,
  name VARCHAR(120) NOT NULL,
  specialty VARCHAR(120) NOT NULL DEFAULT '',
  email VARCHAR(190) NOT NULL,
  phone VARCHAR(20) NOT NULL DEFAULT '',
  password_hash VARCHAR(255) NULL,
  work_days VARCHAR(20) NOT NULL DEFAULT '1,2,3,4,5',
  start_time TIME NOT NULL DEFAULT '08:00:00',
  end_time TIME NOT NULL DEFAULT '16:00:00',
  slot_minutes SMALLINT UNSIGNED NOT NULL DEFAULT 30,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_doctor_email (email),
  KEY idx_doctor_clinic (clinic_id),
  CONSTRAINT fk_doctor_clinic FOREIGN KEY (clinic_id) REFERENCES clinics (id) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS admins (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(120) NOT NULL,
  email VARCHAR(190) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_admin_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS appointments (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  patient_id INT UNSIGNED NOT NULL,
  doctor_id INT UNSIGNED NOT NULL,
  appointment_date DATE NOT NULL,
  appointment_time TIME NOT NULL,
  reason VARCHAR(500) NOT NULL DEFAULT '',
  status ENUM('Pending','Approved','Cancelled','Rescheduled','Completed') NOT NULL DEFAULT 'Pending',
  doctor_note VARCHAR(500) NOT NULL DEFAULT '',
  reminder_sent TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_appt_doctor_slot (doctor_id, appointment_date, appointment_time),
  KEY idx_appt_patient (patient_id, appointment_date),
  KEY idx_appt_status_date (status, appointment_date),
  CONSTRAINT fk_appt_patient FOREIGN KEY (patient_id) REFERENCES patients (id) ON DELETE CASCADE,
  CONSTRAINT fk_appt_doctor FOREIGN KEY (doctor_id) REFERENCES doctors (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS appointment_history (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  appointment_id INT UNSIGNED NOT NULL,
  from_status VARCHAR(20) NULL,
  to_status VARCHAR(20) NOT NULL,
  from_date DATE NULL,
  from_time TIME NULL,
  to_date DATE NULL,
  to_time TIME NULL,
  actor_role VARCHAR(10) NOT NULL,
  actor_id INT UNSIGNED NOT NULL,
  note VARCHAR(500) NOT NULL DEFAULT '',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_history_appt (appointment_id),
  KEY idx_history_created (created_at),
  CONSTRAINT fk_history_appt FOREIGN KEY (appointment_id) REFERENCES appointments (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS notifications (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_role VARCHAR(10) NOT NULL,
  user_id INT UNSIGNED NOT NULL,
  title VARCHAR(160) NOT NULL,
  body VARCHAR(500) NOT NULL DEFAULT '',
  link VARCHAR(255) NOT NULL DEFAULT '',
  is_read TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_notif_user (user_role, user_id, is_read)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS audit_log (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  admin_id INT UNSIGNED NULL,
  action VARCHAR(60) NOT NULL,
  entity VARCHAR(40) NOT NULL,
  entity_id INT UNSIGNED NULL,
  details VARCHAR(500) NOT NULL DEFAULT '',
  ip VARCHAR(45) NOT NULL DEFAULT '',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_audit_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS login_attempts (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  identifier VARCHAR(190) NOT NULL,
  ip VARCHAR(45) NOT NULL,
  attempted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_attempt_ident (identifier, attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Sample clinics
INSERT IGNORE INTO clinics (name, description) VALUES
  ('General Outpatient', 'Consultations for general illness, check-ups and referrals.'),
  ('Dental Clinic', 'Dental examinations, cleaning, fillings and extractions.'),
  ('Eye Clinic', 'Eye examinations, vision tests and prescriptions.'),
  ('Physiotherapy', 'Assessment and treatment of injuries and mobility problems.'),
  ('Mental Health and Counselling', 'Confidential counselling and mental health support.'),
  ('Women''s Health', 'Family planning, antenatal care and gynaecology.');

-- Sample doctors. They have no password until one is set in install.php
-- or by an administrator, so they cannot sign in before then.
INSERT IGNORE INTO doctors (clinic_id, name, specialty, email, work_days, start_time, end_time, slot_minutes)
  SELECT id, 'Dr. Adaeze Okonkwo', 'General Practitioner', 'a.okonkwo@uhs.test', '1,2,3,4,5', '08:00:00', '16:00:00', 30 FROM clinics WHERE name = 'General Outpatient';
INSERT IGNORE INTO doctors (clinic_id, name, specialty, email, work_days, start_time, end_time, slot_minutes)
  SELECT id, 'Dr. Babatunde Alade', 'General Practitioner', 'b.alade@uhs.test', '1,3,5', '09:00:00', '15:00:00', 20 FROM clinics WHERE name = 'General Outpatient';
INSERT IGNORE INTO doctors (clinic_id, name, specialty, email, work_days, start_time, end_time, slot_minutes)
  SELECT id, 'Dr. Funmilayo Ogunleye', 'Dental Surgeon', 'f.ogunleye@uhs.test', '2,4', '08:30:00', '14:30:00', 30 FROM clinics WHERE name = 'Dental Clinic';
INSERT IGNORE INTO doctors (clinic_id, name, specialty, email, work_days, start_time, end_time, slot_minutes)
  SELECT id, 'Dr. Ibrahim Musa', 'Ophthalmologist', 'i.musa@uhs.test', '1,2,3,4,5', '09:00:00', '15:00:00', 30 FROM clinics WHERE name = 'Eye Clinic';
INSERT IGNORE INTO doctors (clinic_id, name, specialty, email, work_days, start_time, end_time, slot_minutes)
  SELECT id, 'Dr. Kemi Adeyemi', 'Physiotherapist', 'k.adeyemi@uhs.test', '1,2,3,4,5', '08:00:00', '14:00:00', 45 FROM clinics WHERE name = 'Physiotherapy';
INSERT IGNORE INTO doctors (clinic_id, name, specialty, email, work_days, start_time, end_time, slot_minutes)
  SELECT id, 'Dr. Chinedu Eze', 'Clinical Psychologist', 'c.eze@uhs.test', '1,2,4', '10:00:00', '16:00:00', 60 FROM clinics WHERE name = 'Mental Health and Counselling';
INSERT IGNORE INTO doctors (clinic_id, name, specialty, email, work_days, start_time, end_time, slot_minutes)
  SELECT id, 'Dr. Aisha Bello', 'Obstetrician and Gynaecologist', 'a.bello@uhs.test', '2,3,5', '08:00:00', '13:00:00', 30 FROM clinics WHERE name = 'Women''s Health';
