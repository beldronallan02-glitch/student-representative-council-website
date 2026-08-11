-- Reconstructed schema for MPPConnect.
-- No .sql export existed in the repo or on the author's machine, so this was
-- rebuilt by reading every SQL query in the PHP codebase (INSERT/UPDATE/SELECT
-- column lists). Treat this as a best-effort starting point, not a verified
-- source of truth -- if a feature errors with "Unknown column", add the
-- missing column here and re-run `docker compose down -v && docker compose up`.

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  matric_no VARCHAR(50) NULL,
  name VARCHAR(150) NOT NULL,
  email VARCHAR(190) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('student','mpp','admin') NOT NULL DEFAULT 'student',
  role_label VARCHAR(100) NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  profile_image VARCHAR(255) NULL,
  phone VARCHAR(30) NULL,
  student_id VARCHAR(50) NULL,
  department VARCHAR(150) NULL,
  program VARCHAR(150) NULL,
  year VARCHAR(20) NULL,
  bio TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS announcements (
  id INT AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(255) NOT NULL,
  excerpt TEXT NULL,
  body TEXT NULL,
  tag VARCHAR(100) NULL,
  image VARCHAR(255) NULL,
  author_id INT NULL,
  status ENUM('draft','published','archived') NOT NULL DEFAULT 'draft',
  publish_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (author_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS events (
  id INT AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(255) NOT NULL,
  excerpt TEXT NULL,
  description TEXT NULL,
  location VARCHAR(255) NULL,
  capacity INT NULL,
  start_at DATETIME NULL,
  end_at DATETIME NULL,
  image VARCHAR(255) NULL,
  author_id INT NULL,
  status ENUM('draft','published','archived','cancelled') NOT NULL DEFAULT 'draft',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (author_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS event_registrations (
  id INT AUTO_INCREMENT PRIMARY KEY,
  event_id INT NOT NULL,
  user_id INT NULL,
  participant_name VARCHAR(150) NULL,
  participant_email VARCHAR(190) NULL,
  status VARCHAR(30) NOT NULL DEFAULT 'registered',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS complaints (
  id INT AUTO_INCREMENT PRIMARY KEY,
  ticket_no VARCHAR(50) NOT NULL,
  user_id INT NULL,
  anonymous_token VARCHAR(64) NULL,
  title VARCHAR(255) NULL,
  category VARCHAR(255) NULL,
  location VARCHAR(255) NULL,
  severity ENUM('low','medium','high','urgent') NULL,
  priority ENUM('low','medium','high') NULL,
  description TEXT NULL,
  status VARCHAR(30) NOT NULL DEFAULT 'new',
  assigned_to INT NULL,
  is_anonymous TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_ticket_no (ticket_no),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
  FOREIGN KEY (assigned_to) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS complaint_images (
  id INT AUTO_INCREMENT PRIMARY KEY,
  complaint_id INT NOT NULL,
  filename VARCHAR(255) NOT NULL,
  original_name VARCHAR(255) NULL,
  mime VARCHAR(100) NULL,
  size INT NULL,
  FOREIGN KEY (complaint_id) REFERENCES complaints(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS complaint_audit (
  id INT AUTO_INCREMENT PRIMARY KEY,
  complaint_id INT NOT NULL,
  user_id INT NULL,
  action VARCHAR(50) NOT NULL,
  note TEXT NULL,
  meta TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (complaint_id) REFERENCES complaints(id) ON DELETE CASCADE,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS facilities (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(255) NOT NULL,
  description TEXT NULL,
  location VARCHAR(255) NULL,
  capacity INT NULL,
  created_by INT NULL,
  status ENUM('active','archived') NOT NULL DEFAULT 'active',
  is_archived TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS facility_bookings (
  id INT AUTO_INCREMENT PRIMARY KEY,
  facility_id INT NOT NULL,
  user_id INT NULL,
  name VARCHAR(150) NULL,
  email VARCHAR(190) NULL,
  purpose TEXT NULL,
  start_at DATETIME NOT NULL,
  end_at DATETIME NOT NULL,
  attendees INT NULL,
  status VARCHAR(30) NOT NULL DEFAULT 'pending',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (facility_id) REFERENCES facilities(id) ON DELETE CASCADE,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS feedback_prompts (
  id INT AUTO_INCREMENT PRIMARY KEY,
  event_id INT NULL,
  title VARCHAR(255) NOT NULL,
  open_at DATETIME NULL,
  close_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS feedbacks (
  id INT AUTO_INCREMENT PRIMARY KEY,
  prompt_id INT NULL,
  event_id INT NULL,
  user_id INT NULL,
  rating INT NOT NULL,
  comment TEXT NULL,
  anonymous TINYINT(1) NOT NULL DEFAULT 0,
  deleted_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (prompt_id) REFERENCES feedback_prompts(id) ON DELETE SET NULL,
  FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE SET NULL,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS feedback_images (
  id INT AUTO_INCREMENT PRIMARY KEY,
  feedback_id INT NOT NULL,
  filename VARCHAR(255) NOT NULL,
  original_name VARCHAR(255) NULL,
  mime VARCHAR(100) NULL,
  size INT NULL,
  FOREIGN KEY (feedback_id) REFERENCES feedbacks(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Legacy/simple progress-log system (still referenced by some modules).
CREATE TABLE IF NOT EXISTS progress_entries (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NULL,
  title VARCHAR(255) NOT NULL,
  description TEXT NULL,
  status ENUM('draft','published') NOT NULL DEFAULT 'draft',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS progress_images (
  id INT AUTO_INCREMENT PRIMARY KEY,
  progress_id INT NOT NULL,
  filename VARCHAR(255) NOT NULL,
  FOREIGN KEY (progress_id) REFERENCES progress_entries(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Newer progress-log system (mpp_progress -> progress_log -> progress_log_images).
CREATE TABLE IF NOT EXISTS mpp_progress (
  mppprogressid INT AUTO_INCREMENT PRIMARY KEY,
  userid INT NULL,
  start_date DATE NULL,
  end_date DATE NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (userid) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS progress_log (
  logid INT AUTO_INCREMENT PRIMARY KEY,
  mppprogressid INT NOT NULL,
  title VARCHAR(255) NOT NULL,
  description TEXT NULL,
  status ENUM('planned','in_progress','completed','delayed') NOT NULL DEFAULT 'planned',
  remarks VARCHAR(255) NULL,
  logged_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (mppprogressid) REFERENCES mpp_progress(mppprogressid) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS progress_log_images (
  imageid INT AUTO_INCREMENT PRIMARY KEY,
  logid INT NOT NULL,
  imgpath VARCHAR(255) NOT NULL,
  uploaded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (logid) REFERENCES progress_log(logid) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Seed one account per role so you can log in immediately.
-- Password for all three is: Password123!
INSERT INTO users (name, email, password_hash, role, is_active) VALUES
  ('Admin User', 'admin@example.com', '$2y$10$PDTbfo7i9FHvaCs0hb71SupKCB33mraQ16ppCjDQqc.02KmTUsCgi', 'admin', 1),
  ('MPP User', 'mpp@example.com', '$2y$10$PDTbfo7i9FHvaCs0hb71SupKCB33mraQ16ppCjDQqc.02KmTUsCgi', 'mpp', 1),
  ('Student User', 'student@example.com', '$2y$10$PDTbfo7i9FHvaCs0hb71SupKCB33mraQ16ppCjDQqc.02KmTUsCgi', 'student', 1);
