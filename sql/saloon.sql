-- 1. users Table (Authentication & Roles)
CREATE TABLE users (
    user_id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    email VARCHAR(100) UNIQUE,
    role ENUM('admin', 'receptionist', 'stylist') NOT NULL DEFAULT 'stylist',
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);


-- 2. clients Table (Client Management)
CREATE TABLE clients (
    client_id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    phone VARCHAR(20) UNIQUE,
    email VARCHAR(100) UNIQUE,
    preferences TEXT, -- Specific stylist, allergies, etc.
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 3. services Table (Service Catalog)
CREATE TABLE services (
    service_id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    duration_minutes INT NOT NULL,
    price DECIMAL(10, 2) NOT NULL,
    is_active BOOLEAN DEFAULT TRUE
);

-- 4. Initial Admin User
-- run this to get the hash for the password "admin123":
-- <?php
-- echo password_hash("admin123", PASSWORD_DEFAULT);
-- // Example output: $2y$10$wTf4J.rE0o5QpY... (your hash will be different)
-- ?>
INSERT INTO users (username, password_hash, email, role) VALUES 
('admin', '$2y$10$n0l3HINTgAcCAJoTGu1N2ObO9rKWUDARKhNo6mEqrJRHmh.Q3jKAi', 'admin@salon.com', 'admin');


-- staff Table (Staff Details)
CREATE TABLE staff (
    staff_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNIQUE NOT NULL, -- Links to the users table
    commission_rate DECIMAL(5, 2) DEFAULT 0.00, -- e.g., 10.50 for 10.5%
    phone VARCHAR(20),
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
);

-- appointments Table (The Core Booking Table)
CREATE TABLE appointments (
    app_id INT AUTO_INCREMENT PRIMARY KEY,
    client_id INT NOT NULL,
    staff_id INT NOT NULL,
    service_id INT NOT NULL,
    start_time DATETIME NOT NULL,
    end_time DATETIME NOT NULL,
    status ENUM('Booked', 'Confirmed', 'Cancelled', 'Completed') NOT NULL DEFAULT 'Booked',
    booking_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (client_id) REFERENCES clients(client_id) ON DELETE RESTRICT, -- Prevent deleting a client with appointments
    FOREIGN KEY (staff_id) REFERENCES staff(staff_id) ON DELETE RESTRICT,
    FOREIGN KEY (service_id) REFERENCES services(service_id) ON DELETE RESTRICT
);

-- Since a staff member needs to exist to take an appointment, let's create a Stylist user and link them to the staff table.

-- Create a Stylist User: (Assume the Stylist's password hash is the same as the admin for now, or generate a new one).
INSERT INTO users (username, password_hash, email, role) VALUES 
('stylist_anna', '$2y$10$n0l3HINTgAcCAJoTGu1N2ObO9rKWUDARKhNo6mEqrJRHmh.Q3jKAi', 'anna@salon.com', 'stylist');


-- Add Initial Services:
INSERT INTO services (name, description, duration_minutes, price) VALUES 
('Haircut & Style', 'Professional wash, cut, and blow dry.', 60, 50.00),
('Manicure', 'Nail shaping, cuticle care, and polish.', 45, 30.00),
('Deep Tissue Facial', 'Cleansing, exfoliation, and mask.', 75, 85.00);


-- suppliers Table (Source Tracking)
CREATE TABLE suppliers (
    supplier_id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    contact_person VARCHAR(100),
    phone VARCHAR(20),
    email VARCHAR(100)
);


-- inventory Table (Stock & Cost Tracking)
CREATE TABLE inventory (
    item_id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    supplier_id INT,
    stock_level INT NOT NULL DEFAULT 0,
    low_stock_threshold INT NOT NULL DEFAULT 5,
    unit_cost DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    last_restock_date DATE,
    FOREIGN KEY (supplier_id) REFERENCES suppliers(supplier_id) ON DELETE SET NULL
);

-- Add a Supplier:
INSERT INTO suppliers (name, contact_person, phone, email) VALUES 
('ProHair Distributors', 'Sarah Lee', '555-1234', 'sales@prohair.com');

-- Add Inventory Items: (Replace [SUPPLIER_ID_1] with the actual ID inserted above)
INSERT INTO inventory (name, supplier_id, stock_level, low_stock_threshold, unit_cost) VALUES 
('Professional Shampoo (L)', 1 , 25, 10, 15.50),
('Color Developer (10 Vol)', 1 , 8, 15, 8.75),
('Disposable Gloves (Box)', NULL, 40, 5, 5.00);


-- payments Table (Sales Record)
-- This table records the money received, linking the payment directly to the service provided via the appointment ID.
CREATE TABLE payments (
    payment_id INT AUTO_INCREMENT PRIMARY KEY,
    app_id INT UNIQUE NOT NULL, -- Ensure one payment per appointment
    amount_paid DECIMAL(10, 2) NOT NULL,
    payment_method ENUM('Cash', 'Card', 'Mobile Pay') NOT NULL,
    payment_date DATETIME DEFAULT CURRENT_TIMESTAMP,
    invoice_number VARCHAR(50) UNIQUE, -- For official tracking
    FOREIGN KEY (app_id) REFERENCES appointments(app_id) ON DELETE RESTRICT
);

-- . Update Appointments Status
-- We need to add the ability to mark an appointment as Completed before a payment can be processed.


-- feedback Table
CREATE TABLE feedback (
    feedback_id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100),
    email VARCHAR(100),
    rating INT, -- Optional: 1 to 5 scale
    comments TEXT NOT NULL,
    submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- We will create a new file, admin/reports_analytics.php, focusing on advanced SELECT queries using SUM(), COUNT(), and GROUP BY.

-- staff_schedules Table
CREATE TABLE staff_schedules (
    schedule_id INT AUTO_INCREMENT PRIMARY KEY,
    staff_id INT NOT NULL,
    day_of_week ENUM('Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun') NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    FOREIGN KEY (staff_id) REFERENCES staff(staff_id) ON DELETE CASCADE,
    UNIQUE KEY unique_schedule (staff_id, day_of_week)
);

-- commissions Table
CREATE TABLE commissions (
    commission_id INT AUTO_INCREMENT PRIMARY KEY,
    app_id INT UNIQUE NOT NULL, -- Link directly to the appointment
    staff_id INT NOT NULL,
    commission_amount DECIMAL(10, 2) NOT NULL,
    payment_status ENUM('Pending', 'Paid') NOT NULL DEFAULT 'Pending',
    commission_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (app_id) REFERENCES appointments(app_id) ON DELETE RESTRICT,
    FOREIGN KEY (staff_id) REFERENCES staff(staff_id) ON DELETE RESTRICT
);

-- notifications Table
CREATE TABLE notifications (
    notification_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL, -- NULL for system-wide notifications (e.g., Low Stock)
    related_id INT NULL, -- e.g., the app_id for an appointment reminder
    type ENUM('Appointment', 'Inventory', 'Payment', 'System') NOT NULL,
    message TEXT NOT NULL,
    is_read BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- inserting stylist anna into staff table
INSERT INTO staff (user_id, commission_rate) 
VALUES (2, 15.00); 
-- We assume a 15% commission rate for this example.