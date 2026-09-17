CREATE DATABASE IF NOT EXISTS sharp_society_barber
CHARACTER SET utf8mb4
COLLATE utf8mb4_polish_ci;

USE sharp_society_barber;


CREATE TABLE users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL,
    surname VARCHAR(50) NOT NULL,
    email VARCHAR(100) NOT NULL  UNIQUE,
    password VARCHAR(255) NOT NULL,
    phone VARCHAR(20) NOT NULL,
    role ENUM('client', 'employee', 'admin') NOT NULL DEFAULT 'client',
    active BOOLEAN NOT NULL DEFAULT TRUE,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE service_categories (
    id INT   UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT
);

CREATE TABLE services (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    category_id INT UNSIGNED NOT NULL,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    duration INT UNSIGNED NOT NULL,
    price  DECIMAL(10,2) NOT NULL,
    acrive BOOLEAN NOT NULL  DEFAULT TRUE,

    CONSTRAINT fk_services_category
    FOREIGN KEY (category_id)
    references service_categories(id)
    ON UPDATE CASCADE
    ON DELETE RESTRICT
);

CREATE TABLE employees (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL UNIQUE,
    description TEXT,
    acrive BOOLEAN NOT NULL DEFAULT TRUE,

    CONSTRAINT fk_services_user
        FOREIGN KEY (user_id)
        references users(id)
        ON UPDATE CASCADE
        ON DELETE CASCADE
);

CREATE TABLE employee_services (
    employee_id INT UNSIGNED NOT NULL,
    service_id INT UNSIGNED NOT NULL,

    PRIMARY KEY (employee_id, service_id),

    CONSTRAINT fk_employee_services_employee
        FOREIGN KEY (employee_id)
        REFERENCES employees(id)
        ON UPDATE CASCADE
        ON DELETE CASCADE,

    CONSTRAINT fk_employee_services_service
        FOREIGN KEY (service_id)
        REFERENCES services(id)
        ON UPDATE CASCADE
        ON DELETE CASCADE
);

CREATE TABLE employee_availability (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    employee_id INT UNSIGNED NOT NULL,
    day_of_week TINYINT UNSIGNED NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,

    CONSTRAINT fk_availability_employee
        FOREIGN KEY (employee_id)
        REFERENCES employees(id)
        ON UPDATE CASCADE
        ON DELETE CASCADE,

    CONSTRAINT chk_day_of_week
        CHECK (day_of_week BETWEEN 1 AND 7),

    CONSTRAINT chk_working_hours
        CHECK (start_time < end_time)
);

CREATE TABLE reservations (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    employee_id INT UNSIGNED NOT NULL,
    service_id INT UNSIGNED NOT NULL,
    reservation_date DATE NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    status ENUM(
        'pending',
        'confirmed',
        'completed',
        'cancelled'
    ) NOT NULL DEFAULT 'pending',
    comment TEXT,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_reservations_user
        FOREIGN KEY (user_id)
        REFERENCES users(id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,

    CONSTRAINT fk_reservations_employee
        FOREIGN KEY (employee_id)
        REFERENCES employees(id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,

    CONSTRAINT fk_reservations_service
        FOREIGN KEY (service_id)
        REFERENCES services(id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,

    CONSTRAINT chk_reservation_time
        CHECK (start_time < end_time)
);

CREATE INDEX idx_reservations_user
ON reservations(user_id);

CREATE INDEX idx_reservations_employee_date
ON reservations(employee_id, reservation_date);

CREATE INDEX idx_reservations_status
ON reservations(status);

CREATE INDEX idx_services_category
ON services(category_id);

INSERT INTO service_categories (name, description) VALUES
('Strzyżenie', 'Usługi związane ze strzyżeniem włosów'),
('Broda', 'Usługi związane z pielęgnacją i stylizacją brody'),
('Pakiety', 'Połączone usługi strzyżenia włosów i brody'),
('Premium', 'Rozbudowane usługi barberskie premium');

INSERT INTO services
(category_id, name, description, duration, price)
VALUES
(1, 'Classic Cut', 'Klasyczne męskie strzyżenie włosów', 45, 70.00),
(1, 'Skin Fade', 'Strzyżenie z płynnym cieniowaniem', 60, 90.00),
(1, 'Buzz Cut', 'Krótkie strzyżenie maszynką', 30, 55.00),
(2, 'Beard Trim', 'Trymowanie i stylizacja brody', 30, 50.00),
(2, 'Beard Premium', 'Kompleksowa pielęgnacja i stylizacja brody', 45, 70.00),
(3, 'Hair & Beard', 'Strzyżenie włosów oraz stylizacja brody', 75, 120.00),
(3, 'Father & Son', 'Pakiet strzyżenia dla ojca i syna', 90, 150.00),
(4, 'Executive Package', 'Kompleksowa usługa premium: włosy i broda', 90, 170.00);

INSERT INTO users
(name, surname, email, password, phone, role, active)
VALUES
('Adam', 'Kowalski', 'admin@sharpsociety.pl',
'$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC6k4XQZ3b8y0Q7h7j5K',
'500100100', 'admin', TRUE),

('Jan', 'Nowak', 'jan@sharpsociety.pl',
'$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC6k4XQZ3b8y0Q7h7j5K',
'500200200', 'employee', TRUE),

('Michal', 'Wisniewski', 'michal@sharpsociety.pl',
'$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC6k4XQZ3b8y0Q7h7j5K',
'500300300', 'employee', TRUE),

('Piotr', 'Wojcik', 'piotr@sharpsociety.pl',
'$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC6k4XQZ3b8y0Q7h7j5K',
'500400400', 'client', TRUE),

('Kamil', 'Lewandowski', 'kamil@sharpsociety.pl',
'$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC6k4XQZ3b8y0Q7h7j5K',
'500500500', 'client', TRUE);

INSERT INTO employees
(user_id, description)
VALUES
(2, 'Barber specjalizujący się w klasycznych strzyżeniach i skin fade.'),
(3, 'Barber specjalizujący się w stylizacji brody oraz usługach premium.');

INSERT INTO employee_services (employee_id, service_id) VALUES
(1, 1),
(1, 2),
(1, 3),
(1, 6),
(1, 7),

(2, 1),
(2, 4),
(2, 5),
(2, 6),
(2, 8);

INSERT INTO employee_availability
(employee_id, day_of_week, start_time, end_time)
VALUES
(1, 1, '09:00:00', '17:00:00'),
(1, 2, '09:00:00', '17:00:00'),
(1, 3, '12:00:00', '20:00:00'),
(1, 4, '12:00:00', '20:00:00'),
(1, 5, '09:00:00', '17:00:00');

INSERT INTO employee_availability
(employee_id, day_of_week, start_time, end_time)
VALUES
(1, 1, '09:00:00', '17:00:00'),
(1, 2, '09:00:00', '17:00:00'),
(1, 3, '12:00:00', '20:00:00'),
(1, 4, '12:00:00', '20:00:00'),
(1, 5, '09:00:00', '17:00:00');

INSERT INTO reservations
(user_id, employee_id, service_id, reservation_date,
 start_time, end_time, status, comment)
VALUES
(4, 1, 1, '2026-09-22',
 '10:00:00', '10:45:00', 'confirmed',
 'Pierwsza wizyta'),

(5, 2, 4, '2026-09-23',
 '14:00:00', '14:30:00', 'pending',
 'Prośba o krótszą brodę'),

(4, 2, 5, '2026-09-25',
 '16:00:00', '16:45:00', 'completed',
 NULL),

(5, 1, 2, '2026-09-26',
 '11:00:00', '12:00:00', 'cancelled',
 'Klient odwołał wizytę');