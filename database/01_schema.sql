-- HabalGo : Database Structure

CREATE SCHEMA IF NOT EXISTS habalgo;

CREATE TABLE IF NOT EXISTS habalgo.customer (
    customer_id SERIAL PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    phone VARCHAR(20) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE
);

CREATE TABLE IF NOT EXISTS habalgo.driver (
    driver_id SERIAL PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    phone VARCHAR(20) NOT NULL,
    license_no VARCHAR(50) NOT NULL UNIQUE
);

CREATE TABLE IF NOT EXISTS habalgo.vehicle (
    vehicle_id SERIAL PRIMARY KEY,
    plate_no VARCHAR(20) NOT NULL UNIQUE,
    vehicle_type VARCHAR(50) NOT NULL,
    model VARCHAR(50) NOT NULL
);

CREATE TABLE IF NOT EXISTS habalgo.driver_vehicle (
    driver_id INT NOT NULL REFERENCES habalgo.driver(driver_id) ON UPDATE CASCADE ON DELETE RESTRICT,
    vehicle_id INT NOT NULL REFERENCES habalgo.vehicle(vehicle_id) ON UPDATE CASCADE ON DELETE RESTRICT,
    assigned_from TIMESTAMP NOT NULL,
    assigned_to TIMESTAMP NULL,
    PRIMARY KEY (driver_id, vehicle_id),
    CONSTRAINT ck_assignment_dates CHECK (assigned_to IS NULL OR assigned_to >= assigned_from)
);

CREATE TABLE IF NOT EXISTS habalgo.location (
    location_id SERIAL PRIMARY KEY,
    address VARCHAR(255) NOT NULL,
    city VARCHAR(100) NOT NULL
);

CREATE TABLE IF NOT EXISTS habalgo.booking (
    booking_id SERIAL PRIMARY KEY,
    customer_id INT NOT NULL REFERENCES habalgo.customer(customer_id) ON UPDATE CASCADE ON DELETE RESTRICT,
    driver_id INT NULL REFERENCES habalgo.driver(driver_id) ON UPDATE CASCADE ON DELETE SET NULL,
    pickup_location_id INT NOT NULL REFERENCES habalgo.location(location_id) ON UPDATE CASCADE ON DELETE RESTRICT,
    dropoff_location_id INT NOT NULL REFERENCES habalgo.location(location_id) ON UPDATE CASCADE ON DELETE RESTRICT,
    booking_date TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    booking_status VARCHAR(50) NOT NULL DEFAULT 'Pending',
    CONSTRAINT ck_booking_status CHECK (booking_status IN ('Pending', 'Confirmed', 'In Progress', 'Completed', 'Cancelled'))
);

CREATE TABLE IF NOT EXISTS habalgo.payment (
    payment_id SERIAL PRIMARY KEY,
    booking_id INT NOT NULL REFERENCES habalgo.booking(booking_id) ON UPDATE CASCADE ON DELETE RESTRICT,
    amount NUMERIC(10,2) NOT NULL CHECK (amount > 0),
    method VARCHAR(50) NOT NULL,
    status VARCHAR(50) NOT NULL DEFAULT 'Pending'
);

CREATE TABLE IF NOT EXISTS habalgo.rating_feedback (
    rating_id SERIAL PRIMARY KEY,
    booking_id INT NOT NULL REFERENCES habalgo.booking(booking_id) ON UPDATE CASCADE ON DELETE RESTRICT,
    customer_id INT NOT NULL REFERENCES habalgo.customer(customer_id) ON UPDATE CASCADE ON DELETE RESTRICT,
    rating INT NOT NULL CHECK (rating BETWEEN 1 AND 5),
    feedback TEXT NULL,
    CONSTRAINT uq_rating_booking_customer UNIQUE (booking_id, customer_id)
);

CREATE INDEX IF NOT EXISTS idx_booking_customer ON habalgo.booking(customer_id);
CREATE INDEX IF NOT EXISTS idx_booking_driver ON habalgo.booking(driver_id);
CREATE INDEX IF NOT EXISTS idx_booking_date ON habalgo.booking(booking_date);
CREATE INDEX IF NOT EXISTS idx_payment_booking ON habalgo.payment(booking_id);
CREATE INDEX IF NOT EXISTS idx_rating_booking ON habalgo.rating_feedback(booking_id);