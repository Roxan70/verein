CREATE TABLE users (
  user_id INT AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(100) UNIQUE NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('admin','organizer','viewer') DEFAULT 'admin',
  preferred_lang ENUM('de','en','hu','cs','sk') DEFAULT 'de',
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE branding (
  id TINYINT PRIMARY KEY,
  organizer_name VARCHAR(200), organizer_short VARCHAR(50), slogan VARCHAR(255), logo_path VARCHAR(255),
  primary_color VARCHAR(20), secondary_color VARCHAR(20), text_color VARCHAR(20),
  footer_line VARCHAR(255), contact_line VARCHAR(255),
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE event_types (
  type_code ENUM('TRACK','COURSING','FUNRUN') PRIMARY KEY,
  label_de VARCHAR(100),label_en VARCHAR(100),label_hu VARCHAR(100),label_cs VARCHAR(100),label_sk VARCHAR(100)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE rule_sources (
  rule_id INT AUTO_INCREMENT PRIMARY KEY,
  scope ENUM('FCI','AT','DE','HU','CZ','SK','OTHER') DEFAULT 'FCI',
  type_code ENUM('TRACK','COURSING','FUNRUN','ALL') DEFAULT 'ALL',
  lang ENUM('de','en','hu','cs','sk') DEFAULT 'de',
  title VARCHAR(200), url VARCHAR(255), summary TEXT,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE owners (
  owner_id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(200) NOT NULL,
  address VARCHAR(255) NULL, phone VARCHAR(50) NULL, email VARCHAR(120) NULL,
  country_code CHAR(2) NOT NULL DEFAULT 'AT',
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE dogs (
  dog_id INT AUTO_INCREMENT PRIMARY KEY,
  owner_id INT NOT NULL,
  name VARCHAR(200) NOT NULL, breed VARCHAR(120) NOT NULL,
  sex ENUM('R','H') NOT NULL, country_code CHAR(2) NOT NULL DEFAULT 'AT',
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (owner_id) REFERENCES owners(owner_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE events (
  event_id INT AUTO_INCREMENT PRIMARY KEY,
  type_code ENUM('TRACK','COURSING','FUNRUN') NOT NULL,
  event_date DATE NOT NULL, location VARCHAR(200) NOT NULL, country_code CHAR(2) NOT NULL DEFAULT 'AT',
  event_lang ENUM('de','en','hu','cs','sk') NOT NULL DEFAULT 'de',
  title_main VARCHAR(255) NOT NULL, title_en_small VARCHAR(255) NULL,
  temperature_c DECIMAL(4,1) NULL, temp_limit_c DECIMAL(4,1) NOT NULL DEFAULT 25.0,
  judge_count TINYINT NOT NULL DEFAULT 3, max_per_heat TINYINT NOT NULL DEFAULT 6,
  officials TEXT NULL, notes_long TEXT NULL, sponsors_text TEXT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE entries (
  entry_id INT AUTO_INCREMENT PRIMARY KEY,
  event_id INT NOT NULL, dog_id INT NOT NULL,
  class_code VARCHAR(80) NOT NULL, group_code ENUM('SOLO','FIELD') NOT NULL DEFAULT 'FIELD',
  distance_m INT NOT NULL,
  status ENUM('OK','NS','NA','DIS','V','DQ') NOT NULL DEFAULT 'OK', dq_reason VARCHAR(255) NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (event_id) REFERENCES events(event_id),
  FOREIGN KEY (dog_id) REFERENCES dogs(dog_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE heats (
  heat_id INT AUTO_INCREMENT PRIMARY KEY,
  event_id INT NOT NULL, heat_no INT NOT NULL,
  heat_type ENUM('HEAT1','HEAT2','FINAL_A','FINAL_B','FINAL_C','COURSING_RUN1','COURSING_RUN2','FUNRUN') NOT NULL,
  class_code VARCHAR(80) NOT NULL, group_code ENUM('SOLO','FIELD') NOT NULL DEFAULT 'FIELD',
  distance_m INT NOT NULL, breed VARCHAR(120) NULL,
  sex_text ENUM('R','H','MIXED') NULL, title_cached VARCHAR(255) NOT NULL,
  is_auto_final TINYINT NOT NULL DEFAULT 0,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_event_heatno(event_id,heat_no),
  FOREIGN KEY (event_id) REFERENCES events(event_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE heat_assignments (
  ha_id INT AUTO_INCREMENT PRIMARY KEY,
  event_id INT NOT NULL, heat_id INT NOT NULL, entry_id INT NOT NULL,
  start_no INT NULL, box_no INT NULL, lane_or_note VARCHAR(80) NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_heat_entry(heat_id,entry_id),
  FOREIGN KEY (event_id) REFERENCES events(event_id),
  FOREIGN KEY (heat_id) REFERENCES heats(heat_id),
  FOREIGN KEY (entry_id) REFERENCES entries(entry_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE performance (
  perf_id INT AUTO_INCREMENT PRIMARY KEY,
  event_id INT NOT NULL, heat_id INT NOT NULL, entry_id INT NOT NULL,
  time_ms INT NULL, s_points INT NULL, a_points INT NULL, e_points INT NULL, f_points INT NULL, h_points INT NULL, total_points INT NULL,
  status ENUM('OK','NS','NA','DIS','V','DQ') NOT NULL DEFAULT 'OK', dq_reason VARCHAR(255) NULL,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_perf(heat_id,entry_id),
  FOREIGN KEY (event_id) REFERENCES events(event_id),
  FOREIGN KEY (heat_id) REFERENCES heats(heat_id),
  FOREIGN KEY (entry_id) REFERENCES entries(entry_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE results (
  result_id INT AUTO_INCREMENT PRIMARY KEY,
  event_id INT NOT NULL, entry_id INT NOT NULL,
  best_track_time_ms INT NULL, sum_solo_time_ms INT NULL, final_time_ms INT NULL,
  coursing_total_points INT NULL,
  rank_overall INT NULL, rank_class INT NULL, rank_group INT NULL,
  is_counted TINYINT NOT NULL DEFAULT 1,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_result(event_id,entry_id),
  FOREIGN KEY (event_id) REFERENCES events(event_id),
  FOREIGN KEY (entry_id) REFERENCES entries(entry_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE catalog_snapshots (
  snapshot_id INT AUTO_INCREMENT PRIMARY KEY,
  event_id INT NOT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  created_by INT NULL,
  is_original TINYINT NOT NULL DEFAULT 0,
  version_no INT NULL,
  config_json MEDIUMTEXT NOT NULL,
  html_cache MEDIUMTEXT NOT NULL,
  FOREIGN KEY (event_id) REFERENCES events(event_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO event_types(type_code,label_de,label_en,label_hu,label_cs,label_sk) VALUES
('TRACK','Bahnrennen','Track Race','Pályaverseny','Dráhový závod','Dráhové preteky'),
('COURSING','Coursing','Coursing','Coursing','Coursing','Coursing'),
('FUNRUN','Funlauf','Fun Run','Fun Futam','Fun běh','Fun beh');

INSERT INTO branding(id,organizer_name,organizer_short,slogan,logo_path,primary_color,secondary_color,text_color,footer_line,contact_line)
VALUES (1,'Organizer Name','ORG','Windhound Racing','assets/logo_default.png','#1f4d7a','#e0e8ef','#222','EU Windhound Race Suite','contact@example.com');

INSERT INTO users(username,password_hash,role,preferred_lang)
VALUES ('admin','$2y$10$BQO3XQeP2WY0vR8S6YF2VeFr2P6fSx4vG4l4w3ebz8fJxqY9yCGm6','admin','de');
