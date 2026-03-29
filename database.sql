-- Create database
CREATE DATABASE IF NOT EXISTS casms;
USE casms;

-- Users table
CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    phone VARCHAR(20),
    address TEXT,
    role ENUM('admin', 'finance', 'mechanic', 'user') DEFAULT 'user',
    profile_pic VARCHAR(255),
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Vehicles table
CREATE TABLE vehicles (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    brand VARCHAR(50) NOT NULL,
    model VARCHAR(50) NOT NULL,
    year INT NOT NULL,
    license_plate VARCHAR(20) UNIQUE NOT NULL,
    vin VARCHAR(50),
    color VARCHAR(30),
    fuel_type ENUM('Petrol', 'Diesel', 'Electric', 'Hybrid', 'CNG') DEFAULT 'Petrol',
    transmission ENUM('Manual', 'Automatic', 'CVT', 'DCT') DEFAULT 'Manual',
    is_active BOOLEAN DEFAULT TRUE,
    deleted_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Services catalog
CREATE TABLE services (
    id INT PRIMARY KEY AUTO_INCREMENT,
    service_name VARCHAR(100) NOT NULL,
    description TEXT,
    category VARCHAR(50) NOT NULL,
    price DECIMAL(10,2) NOT NULL,
    duration INT COMMENT 'Duration in minutes',
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Service bookings
CREATE TABLE bookings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    booking_number VARCHAR(20) UNIQUE NOT NULL,
    user_id INT NOT NULL,
    vehicle_id INT NOT NULL,
    service_id INT NOT NULL,
    mechanic_id INT,
    appointment_date DATE NOT NULL,
    appointment_time TIME NOT NULL,
    status ENUM('pending', 'confirmed', 'in_progress', 'completed', 'cancelled') DEFAULT 'pending',
    notes TEXT,
    estimated_cost DECIMAL(10,2),
    actual_cost DECIMAL(10,2),
    payment_status ENUM('pending', 'paid', 'refunded') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (vehicle_id) REFERENCES vehicles(id),
    FOREIGN KEY (service_id) REFERENCES services(id),
    FOREIGN KEY (mechanic_id) REFERENCES users(id)
);

-- Service history / job cards
CREATE TABLE service_history (
    id INT PRIMARY KEY AUTO_INCREMENT,
    booking_id INT NOT NULL,
    mechanic_id INT,
    service_notes TEXT,
    parts_used TEXT,
    hours_spent DECIMAL(5,2),
    completion_date DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE,
    FOREIGN KEY (mechanic_id) REFERENCES users(id)
);

-- Spare parts inventory
CREATE TABLE spare_parts (
    id INT PRIMARY KEY AUTO_INCREMENT,
    part_name VARCHAR(100) NOT NULL,
    part_number VARCHAR(50) UNIQUE,
    description TEXT,
    category VARCHAR(50),
    quantity INT DEFAULT 0,
    unit_price DECIMAL(10,2),
    reorder_level INT DEFAULT 10,
    supplier VARCHAR(100),
    location VARCHAR(50),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Parts used in services
CREATE TABLE service_parts (
    id INT PRIMARY KEY AUTO_INCREMENT,
    service_history_id INT NOT NULL,
    part_id INT NOT NULL,
    quantity INT NOT NULL,
    unit_price DECIMAL(10,2) NOT NULL,
    total_price DECIMAL(10,2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (service_history_id) REFERENCES service_history(id) ON DELETE CASCADE,
    FOREIGN KEY (part_id) REFERENCES spare_parts(id)
);

-- Invoices
CREATE TABLE invoices (
    id INT PRIMARY KEY AUTO_INCREMENT,
    invoice_number VARCHAR(20) UNIQUE NOT NULL,
    booking_id INT NOT NULL,
    user_id INT NOT NULL,
    subtotal DECIMAL(10,2) NOT NULL,
    tax DECIMAL(10,2) DEFAULT 0,
    discount DECIMAL(10,2) DEFAULT 0,
    total DECIMAL(10,2) NOT NULL,
    payment_method ENUM('cash', 'card', 'mpesa', 'bank') DEFAULT 'cash',
    payment_reference VARCHAR(100),
    status ENUM('draft', 'sent', 'paid', 'overdue', 'cancelled') DEFAULT 'draft',
    due_date DATE,
    paid_date DATETIME,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (booking_id) REFERENCES bookings(id),
    FOREIGN KEY (user_id) REFERENCES users(id)
);

-- Transactions
CREATE TABLE transactions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    transaction_number VARCHAR(50) UNIQUE NOT NULL,
    invoice_id INT,
    user_id INT NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    type ENUM('payment', 'refund', 'expense') NOT NULL,
    payment_method ENUM('cash', 'card', 'mpesa', 'bank') NOT NULL,
    reference VARCHAR(100),
    status ENUM('pending', 'completed', 'failed') DEFAULT 'completed',
    description TEXT,
    transaction_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (invoice_id) REFERENCES invoices(id),
    FOREIGN KEY (user_id) REFERENCES users(id)
);

-- Notifications
CREATE TABLE notifications (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    type ENUM('info', 'success', 'warning', 'emergency') DEFAULT 'info',
    title VARCHAR(100) NOT NULL,
    message TEXT NOT NULL,
    data JSON,
    is_read BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Permissions table
CREATE TABLE permissions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    role ENUM('admin', 'finance', 'mechanic', 'user') NOT NULL,
    permission VARCHAR(100) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_role_permission (role, permission)
);

-- User activity logs
CREATE TABLE activity_logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    action VARCHAR(100) NOT NULL,
    details TEXT,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Emergency requests
CREATE TABLE emergency_requests (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    vehicle_id INT NOT NULL,
    location TEXT NOT NULL,
    issue_type VARCHAR(100) NOT NULL,
    description TEXT,
    status ENUM('pending', 'dispatched', 'en_route', 'arrived', 'completed', 'cancelled') DEFAULT 'pending',
    assigned_mechanic_id INT,
    estimated_arrival DATETIME,
    completed_at DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (vehicle_id) REFERENCES vehicles(id),
    FOREIGN KEY (assigned_mechanic_id) REFERENCES users(id)
);

-- Insert default permissions
INSERT INTO permissions (role, permission) VALUES
-- Admin permissions
('admin', 'dashboard.view'),
('admin', 'users.manage'),
('admin', 'vehicles.view_all'),
('admin', 'vehicles.manage_all'),
('admin', 'services.manage'),
('admin', 'bookings.view_all'),
('admin', 'bookings.manage'),
('admin', 'finance.view_all'),
('admin', 'reports.generate'),
('admin', 'settings.manage'),
('admin', 'notifications.manage'),
('admin', 'spare_parts.manage'),
('admin', 'mechanics.manage'),
('admin', 'invoices.manage'),
('admin', 'transactions.view_all'),

-- Finance permissions
('finance', 'dashboard.view'),
('finance', 'finance.dashboard'),
('finance', 'transactions.view'),
('finance', 'transactions.create'),
('finance', 'invoices.manage'),
('finance', 'payments.process'),
('finance', 'reports.financial'),
('finance', 'bookings.view_all'),
('finance', 'expenses.manage'),
('finance', 'revenue.view'),

-- Mechanic permissions
('mechanic', 'dashboard.view'),
('mechanic', 'mechanic.dashboard'),
('mechanic', 'services.view_assigned'),
('mechanic', 'services.update_status'),
('mechanic', 'services.add_notes'),
('mechanic', 'spare_parts.view'),
('mechanic', 'spare_parts.request'),
('mechanic', 'vehicles.view_details'),
('mechanic', 'service_history.view_assigned'),

-- Regular user permissions
('user', 'dashboard.view'),
('user', 'vehicles.manage_own'),
('user', 'services.view'),
('user', 'services.book'),
('user', 'bookings.view_own'),
('user', 'bookings.cancel_own'),
('user', 'profile.manage'),
('user', 'notifications.view'),
('user', 'service_history.view_own'),
('user', 'spare_parts.view'),
('user', 'invoices.view_own'),
('user', 'emergency.request');

-- Insert sample services
INSERT INTO services (service_name, description, category, price, duration) VALUES
('Oil Change', 'Complete engine oil change with filter replacement', 'Maintenance', 3500.00, 30),
('Brake Pad Replacement', 'Replace front or rear brake pads', 'Repair', 4500.00, 60),
('Wheel Alignment', 'Computerized wheel alignment', 'Maintenance', 2500.00, 45),
('Engine Diagnostic', 'Complete engine diagnostic scan', 'Diagnostic', 2000.00, 30),
('AC Service', 'Air conditioning service and refill', 'Maintenance', 4000.00, 90),
('Battery Replacement', 'Car battery testing and replacement', 'Repair', 5500.00, 30),
('Tire Rotation', 'Rotate tires and balance', 'Maintenance', 1500.00, 30),
('Full Service', 'Complete vehicle service check', 'Maintenance', 12000.00, 180),
('Timing Belt Replacement', 'Replace timing belt and tensioner', 'Repair', 15000.00, 240),
('Clutch Replacement', 'Complete clutch system replacement', 'Repair', 25000.00, 300);

-- Insert sample spare parts
INSERT INTO spare_parts (part_name, part_number, category, quantity, unit_price, reorder_level) VALUES
('Oil Filter', 'OF-001', 'Filters', 50, 450.00, 10),
('Air Filter', 'AF-001', 'Filters', 40, 650.00, 8),
('Brake Pads (Front)', 'BP-F-001', 'Brakes', 30, 1800.00, 5),
('Brake Pads (Rear)', 'BP-R-001', 'Brakes', 30, 1650.00, 5),
('Spark Plug', 'SP-001', 'Engine', 100, 350.00, 20),
('Engine Oil (5L)', 'EO-5L-001', 'Lubricants', 25, 2200.00, 5),
('Coolant (5L)', 'COOL-5L', 'Cooling', 20, 1800.00, 4),
('Battery 12V', 'BAT-12V-001', 'Electrical', 15, 4500.00, 3);

-- Insert default admin user (password: admin123)
INSERT INTO users (name, email, password, role) VALUES
('System Administrator', 'admin@casms.com', '$2y$10$YourHashedPasswordHere', 'admin');

-- Note: Generate proper password hash using password_hash('admin123', PASSWORD_DEFAULT)