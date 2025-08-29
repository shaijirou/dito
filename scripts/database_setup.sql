-- Create database
CREATE DATABASE IF NOT EXISTS child_tracking_system;
USE child_tracking_system;

-- Users table (teachers, parents, admin)
CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    phone VARCHAR(20) NOT NULL,
    role ENUM('admin', 'teacher', 'parent') NOT NULL,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Children table
CREATE TABLE children (
    id INT PRIMARY KEY AUTO_INCREMENT,
    student_id VARCHAR(20) UNIQUE NOT NULL,
    first_name VARCHAR(50) NOT NULL,
    last_name VARCHAR(50) NOT NULL,
    date_of_birth DATE NOT NULL,
    grade VARCHAR(10) NOT NULL,
    photo VARCHAR(255),
    device_id VARCHAR(100),
    emergency_contact VARCHAR(20),
    medical_info TEXT,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Parent-Child relationships
CREATE TABLE parent_child (
    id INT PRIMARY KEY AUTO_INCREMENT,
    parent_id INT NOT NULL,
    child_id INT NOT NULL,
    relationship ENUM('mother', 'father', 'guardian') NOT NULL,
    FOREIGN KEY (parent_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (child_id) REFERENCES children(id) ON DELETE CASCADE,
    UNIQUE KEY unique_parent_child (parent_id, child_id)
);

-- Teacher-Child assignments
CREATE TABLE teacher_child (
    id INT PRIMARY KEY AUTO_INCREMENT,
    teacher_id INT NOT NULL,
    child_id INT NOT NULL,
    class_name VARCHAR(50),
    assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (teacher_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (child_id) REFERENCES children(id) ON DELETE CASCADE
);

-- Geofences (safe zones)
CREATE TABLE geofences (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    center_lat DECIMAL(10, 8) NOT NULL,
    center_lng DECIMAL(11, 8) NOT NULL,
    radius INT NOT NULL, -- in meters
    description TEXT,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Location tracking
CREATE TABLE location_tracking (
    id INT PRIMARY KEY AUTO_INCREMENT,
    child_id INT NOT NULL,
    latitude DECIMAL(10, 8) NOT NULL,
    longitude DECIMAL(11, 8) NOT NULL,
    accuracy FLOAT,
    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    battery_level INT,
    inside_geofence BOOLEAN DEFAULT TRUE,
    FOREIGN KEY (child_id) REFERENCES children(id) ON DELETE CASCADE,
    INDEX idx_child_timestamp (child_id, timestamp),
    INDEX idx_timestamp (timestamp)
);

-- Missing child cases
CREATE TABLE missing_cases (
    id INT PRIMARY KEY AUTO_INCREMENT,
    case_number VARCHAR(20) UNIQUE NOT NULL,
    child_id INT NOT NULL,
    reported_by INT NOT NULL,
    status ENUM('active', 'resolved', 'cancelled') DEFAULT 'active',
    priority ENUM('low', 'medium', 'high', 'critical') DEFAULT 'medium',
    description TEXT,
    last_seen_location VARCHAR(255),
    last_seen_time TIMESTAMP,
    resolved_at TIMESTAMP NULL,
    resolved_by INT NULL,
    resolution_notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (child_id) REFERENCES children(id) ON DELETE CASCADE,
    FOREIGN KEY (reported_by) REFERENCES users(id),
    FOREIGN KEY (resolved_by) REFERENCES users(id)
);

-- Alerts and notifications
CREATE TABLE alerts (
    id INT PRIMARY KEY AUTO_INCREMENT,
    case_id INT,
    child_id INT NOT NULL,
    alert_type ENUM('missing', 'geofence_exit', 'low_battery', 'emergency') NOT NULL,
    message TEXT NOT NULL,
    severity ENUM('info', 'warning', 'critical') DEFAULT 'warning',
    sent_to TEXT, -- JSON array of user IDs
    sms_sent BOOLEAN DEFAULT FALSE,
    email_sent BOOLEAN DEFAULT FALSE,
    status ENUM('pending', 'sent', 'failed') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (case_id) REFERENCES missing_cases(id) ON DELETE CASCADE,
    FOREIGN KEY (child_id) REFERENCES children(id) ON DELETE CASCADE
);

-- SMS logs
CREATE TABLE sms_logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    phone_number VARCHAR(20) NOT NULL,
    message TEXT NOT NULL,
    status ENUM('pending', 'sent', 'failed') DEFAULT 'pending',
    response TEXT,
    sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- System settings
CREATE TABLE settings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    setting_key VARCHAR(100) UNIQUE NOT NULL,
    setting_value TEXT,
    description TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Insert default settings
INSERT INTO settings (setting_key, setting_value, description) VALUES
('school_name', 'Calumala Elementary School', 'School name'),
('sms_api_key', '', 'SMS service API key'),
('sms_sender_id', 'CALUMALA', 'SMS sender ID'),
('default_geofence_radius', '100', 'Default geofence radius in meters'),
('emergency_contact', '+1234567890', 'Emergency contact number');

-- Insert default admin user (password: admin123)
INSERT INTO users (username, email, password, full_name, phone, role) VALUES
('admin', 'admin@calumala.edu', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'System Administrator', '+1234567890', 'admin');
