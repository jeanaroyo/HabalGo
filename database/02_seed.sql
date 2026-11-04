-- HabalGo : Sample Data

SET search_path TO habalgo;

-- Seed Drivers
INSERT INTO habalgo.driver (name, phone, license_no) VALUES 
('Juan Bautista', '09175550101', 'N01-23-000001'),
('Carlos Gomez', '09175550102', 'N01-23-000002')
ON CONFLICT (license_no) DO NOTHING;

-- Seed Customers (Recommended to add for testing)
INSERT INTO habalgo.customer (name, phone, email) VALUES 
('Maria Santos', '09181112233', 'maria@example.com'),
('Juan Dela Cruz', '09184445566', 'juan@example.com')
ON CONFLICT (email) DO NOTHING;