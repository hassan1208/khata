-- Khata database schema (MySQL / MariaDB)
-- setup.php ye file khud chala deta hai, ya aap phpMyAdmin mein import kar sakte hain.

CREATE TABLE IF NOT EXISTS users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(50) NOT NULL UNIQUE,
  email VARCHAR(150) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS settings (
  skey VARCHAR(64) PRIMARY KEY,
  svalue TEXT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS login_otps (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  code_hash VARCHAR(255) NOT NULL,
  expires_at DATETIME NOT NULL,
  attempts TINYINT NOT NULL DEFAULT 0,
  used TINYINT NOT NULL DEFAULT 0,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX (user_id),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS login_attempts (
  id INT AUTO_INCREMENT PRIMARY KEY,
  ip VARCHAR(45) NOT NULL,
  attempted_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX (ip, attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------- Personal cash ----------

CREATE TABLE IF NOT EXISTS incomes (
  id INT AUTO_INCREMENT PRIMARY KEY,
  income_month DATE NOT NULL,           -- kis maheenay ki salary (1st of month)
  received_on DATE NOT NULL,
  type VARCHAR(30) NOT NULL DEFAULT 'Salary',
  amount DECIMAL(14,2) NOT NULL,
  note VARCHAR(255) DEFAULT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX (received_on)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS expense_categories (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(80) NOT NULL UNIQUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS expenses (
  id INT AUTO_INCREMENT PRIMARY KEY,
  exp_date DATE NOT NULL,
  category_id INT DEFAULT NULL,
  amount DECIMAL(14,2) NOT NULL,
  note VARCHAR(255) DEFAULT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX (exp_date),
  FOREIGN KEY (category_id) REFERENCES expense_categories(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS investments (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  description VARCHAR(255) DEFAULT NULL,
  start_date DATE NOT NULL,
  status ENUM('active','closed') NOT NULL DEFAULT 'active',
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- invest   = paisa lagaya / installment di  (cash kam)
-- profit   = profit mila                    (cash barha)
-- withdraw = asal raqam wapas li            (cash barha)
CREATE TABLE IF NOT EXISTS investment_entries (
  id INT AUTO_INCREMENT PRIMARY KEY,
  investment_id INT NOT NULL,
  entry_date DATE NOT NULL,
  type ENUM('invest','profit','withdraw') NOT NULL,
  amount DECIMAL(14,2) NOT NULL,
  note VARCHAR(255) DEFAULT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX (investment_id, entry_date),
  FOREIGN KEY (investment_id) REFERENCES investments(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS loan_people (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  phone VARCHAR(40) DEFAULT NULL,
  note VARCHAR(255) DEFAULT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- gave      = maine udhar diya         (cash kam, wo mujhe dega)
-- got_back  = usne wapas kiya          (cash barha)
-- took      = maine udhar liya         (cash barha, mujhe dena hai)
-- paid_back = maine wapas kiya         (cash kam)
CREATE TABLE IF NOT EXISTS loan_entries (
  id INT AUTO_INCREMENT PRIMARY KEY,
  person_id INT NOT NULL,
  entry_date DATE NOT NULL,
  type ENUM('gave','got_back','took','paid_back') NOT NULL,
  amount DECIMAL(14,2) NOT NULL,
  note VARCHAR(255) DEFAULT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX (person_id, entry_date),
  FOREIGN KEY (person_id) REFERENCES loan_people(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Hath se cash theek karna (e.g. ginti mein farq)
CREATE TABLE IF NOT EXISTS cash_adjustments (
  id INT AUTO_INCREMENT PRIMARY KEY,
  adj_date DATE NOT NULL,
  amount DECIMAL(14,2) NOT NULL,          -- + ya -
  note VARCHAR(255) DEFAULT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------- Rent (alag module, cash se koi taluq nahi) ----------

CREATE TABLE IF NOT EXISTS properties (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  address VARCHAR(255) DEFAULT NULL,
  note VARCHAR(255) DEFAULT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS tenancies (
  id INT AUTO_INCREMENT PRIMARY KEY,
  property_id INT NOT NULL,
  tenant_name VARCHAR(120) NOT NULL,
  phone VARCHAR(40) DEFAULT NULL,
  cnic VARCHAR(20) DEFAULT NULL,
  start_date DATE NOT NULL,                -- kab rent pr diya
  billing_start DATE NOT NULL,             -- kis maheenay se hisab shuru (1st of month)
  opening_due DECIMAL(14,2) NOT NULL DEFAULT 0,  -- pichla baqaya (purane kirayedar k liye)
  due_day TINYINT NOT NULL DEFAULT 5,
  advance_amount DECIMAL(14,2) NOT NULL DEFAULT 0,
  advance_date DATE DEFAULT NULL,
  agreement_months INT DEFAULT NULL,
  increment_type ENUM('percent','fixed') NOT NULL DEFAULT 'percent',
  increment_value DECIMAL(10,2) NOT NULL DEFAULT 10,
  increment_every INT NOT NULL DEFAULT 12,  -- har kitne maheenay baad
  note TEXT,
  status ENUM('active','vacated') NOT NULL DEFAULT 'active',
  vacate_date DATE DEFAULT NULL,
  billing_end DATE DEFAULT NULL,           -- aakhri maheena jiska kiraya banta hai
  deduction_amount DECIMAL(14,2) NOT NULL DEFAULT 0,
  deduction_note VARCHAR(255) DEFAULT NULL,
  refund_amount DECIMAL(14,2) NOT NULL DEFAULT 0,
  refund_date DATE DEFAULT NULL,
  vacate_note TEXT,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX (property_id, status),
  FOREIGN KEY (property_id) REFERENCES properties(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Kiraye ki history: har increment aik naya row
CREATE TABLE IF NOT EXISTS rent_revisions (
  id INT AUTO_INCREMENT PRIMARY KEY,
  tenancy_id INT NOT NULL,
  effective_month DATE NOT NULL,
  rent_amount DECIMAL(14,2) NOT NULL,
  note VARCHAR(255) DEFAULT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY (tenancy_id, effective_month),
  FOREIGN KEY (tenancy_id) REFERENCES tenancies(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS rent_payments (
  id INT AUTO_INCREMENT PRIMARY KEY,
  tenancy_id INT NOT NULL,
  pay_date DATE NOT NULL,
  amount DECIMAL(14,2) NOT NULL,
  method VARCHAR(40) DEFAULT 'Cash',
  note VARCHAR(255) DEFAULT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX (tenancy_id, pay_date),
  FOREIGN KEY (tenancy_id) REFERENCES tenancies(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS tenancy_files (
  id INT AUTO_INCREMENT PRIMARY KEY,
  tenancy_id INT NOT NULL,
  title VARCHAR(120) NOT NULL,
  file_name VARCHAR(255) NOT NULL,
  original_name VARCHAR(255) DEFAULT NULL,
  mime VARCHAR(100) DEFAULT NULL,
  uploaded_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (tenancy_id) REFERENCES tenancies(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS property_expenses (
  id INT AUTO_INCREMENT PRIMARY KEY,
  property_id INT NOT NULL,
  exp_date DATE NOT NULL,
  amount DECIMAL(14,2) NOT NULL,
  note VARCHAR(255) DEFAULT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (property_id) REFERENCES properties(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO expense_categories (name) VALUES
 ('Ghar ka Sauda'),('Bijli'),('Gas'),('Pani'),('Internet / Mobile'),('Petrol'),
 ('School Fees'),('Dawai / Doctor'),('Kapray'),('Bahar Khana'),('Committee'),('Mutafarriq');

INSERT IGNORE INTO settings (skey, svalue) VALUES
 ('opening_cash','0'),('opening_cash_date', CURDATE()),('otp_enabled','1'),
 ('smtp_host',''),('smtp_port','587'),('smtp_encryption','tls'),('smtp_user',''),
 ('smtp_pass',''),('smtp_from_email',''),('smtp_from_name','Khata');
