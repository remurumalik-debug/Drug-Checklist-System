-- Drug Checklist Healthcare System - Database Schema
-- MySQL 8+ / MariaDB 10+

CREATE DATABASE IF NOT EXISTS drug_checklist_system
CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE drug_checklist_system;

-- Roles table
CREATE TABLE roles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    role_name VARCHAR(50) NOT NULL UNIQUE,
    description VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Users table
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    first_name VARCHAR(50) NOT NULL,
    last_name VARCHAR(50) NOT NULL,
    phone VARCHAR(20),
    role_id INT NOT NULL,
    status ENUM('active', 'inactive', 'suspended') DEFAULT 'active',
    last_login DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE RESTRICT ON UPDATE CASCADE,
    INDEX idx_users_role (role_id),
    INDEX idx_users_status (status)
) ENGINE=InnoDB;

-- Drug categories table
CREATE TABLE drug_categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    category_name VARCHAR(100) NOT NULL UNIQUE,
    description TEXT,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Suppliers table
CREATE TABLE suppliers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    supplier_name VARCHAR(150) NOT NULL,
    contact_person VARCHAR(100),
    email VARCHAR(100),
    phone VARCHAR(20),
    address TEXT,
    city VARCHAR(100),
    state VARCHAR(100),
    zip_code VARCHAR(20),
    country VARCHAR(100) DEFAULT 'Philippines',
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_supplier_name (supplier_name)
) ENGINE=InnoDB;

-- Drugs table
CREATE TABLE drugs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    drug_code VARCHAR(50) DEFAULT NULL,
    drug_name VARCHAR(200) NOT NULL,
    generic_name VARCHAR(200),
    brand_name VARCHAR(200),
    category_id INT,
    dosage_form VARCHAR(100),
    strength VARCHAR(100),
    batch_number VARCHAR(100) NOT NULL,
    manufacturer VARCHAR(200),
    supplier_id INT,
    quantity INT NOT NULL DEFAULT 0,
    min_stock INT NOT NULL DEFAULT 10,
    unit_price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    expiry_date DATE NOT NULL,
    manufacturing_date DATE,
    storage_location VARCHAR(100),
    storage_condition VARCHAR(100),
    description TEXT,
    barcode VARCHAR(100),
    status ENUM('available', 'low_stock', 'out_of_stock', 'expired', 'discontinued') DEFAULT 'available',
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES drug_categories(id) ON DELETE SET NULL ON UPDATE CASCADE,
    FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON DELETE SET NULL ON UPDATE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE,
    UNIQUE KEY uq_drug_code (drug_code),
    INDEX idx_drug_name (drug_name),
    INDEX idx_batch_number (batch_number),
    INDEX idx_expiry_date (expiry_date),
    INDEX idx_status (status),
    INDEX idx_category (category_id),
    INDEX idx_supplier (supplier_id)
) ENGINE=InnoDB;

-- Stock transactions table
CREATE TABLE stock_transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    drug_id INT NOT NULL,
    transaction_type ENUM('stock_in', 'stock_out', 'adjustment') NOT NULL,
    quantity INT NOT NULL,
    previous_quantity INT NOT NULL DEFAULT 0,
    new_quantity INT NOT NULL DEFAULT 0,
    unit_price DECIMAL(10,2),
    reference_number VARCHAR(100),
    remarks TEXT,
    performed_by INT NOT NULL,
    transaction_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (drug_id) REFERENCES drugs(id) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (performed_by) REFERENCES users(id) ON DELETE RESTRICT ON UPDATE CASCADE,
    INDEX idx_stock_drug (drug_id),
    INDEX idx_stock_type (transaction_type),
    INDEX idx_stock_date (transaction_date)
) ENGINE=InnoDB;

-- Drug checklists table
CREATE TABLE drug_checklists (
    id INT AUTO_INCREMENT PRIMARY KEY,
    drug_id INT NOT NULL,
    checklist_status ENUM('available', 'low_stock', 'out_of_stock', 'expired', 'damaged') NOT NULL,
    remarks TEXT,
    checked_by INT NOT NULL,
    checked_date DATE NOT NULL,
    checked_time TIME NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (drug_id) REFERENCES drugs(id) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (checked_by) REFERENCES users(id) ON DELETE RESTRICT ON UPDATE CASCADE,
    INDEX idx_checklist_drug (drug_id),
    INDEX idx_checklist_date (checked_date),
    INDEX idx_checklist_status (checklist_status)
) ENGINE=InnoDB;

-- Notifications table
CREATE TABLE notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    notification_type ENUM('expiry_alert', 'low_stock', 'out_of_stock', 'expired', 'system', 'info') NOT NULL,
    title VARCHAR(200) NOT NULL,
    message TEXT NOT NULL,
    related_drug_id INT,
    is_read TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (related_drug_id) REFERENCES drugs(id) ON DELETE SET NULL ON UPDATE CASCADE,
    INDEX idx_notif_user (user_id),
    INDEX idx_notif_read (is_read),
    INDEX idx_notif_date (created_at)
) ENGINE=InnoDB;

-- Audit logs table
CREATE TABLE audit_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    action VARCHAR(100) NOT NULL,
    entity_type VARCHAR(50),
    entity_id INT,
    description TEXT,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE,
    INDEX idx_audit_user (user_id),
    INDEX idx_audit_action (action),
    INDEX idx_audit_date (created_at)
) ENGINE=InnoDB;

-- System settings table
CREATE TABLE system_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) NOT NULL UNIQUE,
    setting_value TEXT NOT NULL,
    description VARCHAR(255),
    updated_by INT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB;

-- Session timeout tracking
CREATE TABLE user_sessions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    session_token VARCHAR(255) NOT NULL UNIQUE,
    ip_address VARCHAR(45),
    user_agent TEXT,
    last_activity DATETIME NOT NULL,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE ON UPDATE CASCADE,
    INDEX idx_session_token (session_token),
    INDEX idx_session_user (user_id)
) ENGINE=InnoDB;

-- Insert default roles
INSERT INTO roles (role_name, description) VALUES
('admin', 'Full system access - manage users, drugs, categories, reports, and settings'),
('pharmacist', 'Manage drug inventory, perform checklists, handle stock transactions'),
('staff', 'View inventory, search drugs, view checklist status, receive notifications');

-- Insert default admin user (password: Admin123!)
-- Password hash generated with password_hash('Admin123!', PASSWORD_DEFAULT)
INSERT INTO users (username, email, password, first_name, last_name, role_id, status) VALUES
('admin', 'admin@drugchecklist.com', '$2y$10$7GTCGG6eE.Hfc3Jd8llWoewEGh395EMfA/alWmfm2sGxow3n/RXDC', 'System', 'Administrator', 1, 'active');

-- Insert default system settings
INSERT INTO system_settings (setting_key, setting_value, description) VALUES
('system_name', 'Drug Checklist Healthcare System', 'Name of the system'),
('system_logo', '', 'System logo path'),
('session_timeout', '3600', 'Session timeout in seconds'),
('expiry_alert_days', '30', 'Days before expiry to trigger alert'),
('low_stock_threshold', '10', 'Default low stock threshold'),
('timezone', 'Asia/Manila', 'System timezone'),
('currency', '₱', 'Currency symbol'),
('date_format', 'Y-m-d', 'Date format'),
('company_address', '', 'Company address'),
('company_phone', '', 'Company phone number');
