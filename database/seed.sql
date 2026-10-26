-- Drug Checklist Healthcare System - Seed Data
-- Run this AFTER schema.sql
-- Generated with exact dataset from requirements

USE drug_checklist_system;

-- Insert demo users (safe to re-run)
INSERT IGNORE INTO users (username, email, password, first_name, last_name, role_id, status) VALUES
('pharmacist', 'pharmacist@drugchecklist.com', '$2y$10$WQR9bu8TEp9DVq1Uqrevz.nZIimuL7GBFkclV4sjf1EBfIUI07nV9e', 'John', 'Pharmacist', 2, 'active'),
('staff', 'staff@drugchecklist.com', '$2y$10$NOykc8H3ZVFpmsU82KYTUu/JKoqjH9/6MVOkTBIHF0MzwO76IP1a', 'Jane', 'Staff', 3, 'active');

-- Insert drug categories
INSERT IGNORE INTO drug_categories (category_name, description) VALUES
('Analgesics', 'Medicines used to relieve pain and reduce fever.'),
('Antibiotics', 'Medicines used to treat bacterial infections.'),
('Antifungals', 'Medicines used to treat fungal infections.'),
('Antihypertensives', 'Medicines used to control high blood pressure.'),
('Antidiabetics', 'Medicines used to manage diabetes mellitus.'),
('Gastrointestinal Drugs', 'Medicines for stomach and digestive disorders.'),
('Respiratory Drugs', 'Medicines used for asthma and other respiratory conditions.'),
('Antihistamines', 'Medicines used to treat allergies and allergic reactions.'),
('Vitamins & Supplements', 'Vitamins and minerals used to support general health.'),
('Corticosteroids', 'Medicines used to reduce inflammation and suppress immune responses.'),
('Emergency Drugs', 'Medicines used during medical emergencies.'),
('Intravenous (IV) Fluids', 'Sterile fluids administered intravenously for hydration and therapy.');

-- Insert suppliers
INSERT IGNORE INTO suppliers (supplier_name, contact_person, email, phone, address, city) VALUES
('GSK (GlaxoSmithKline)', 'Maria Santos', 'maria@gsk.com', '09171234567', '123 Health St.', 'Manila'),
('Pfizer Inc.', 'Carlos Reyes', 'carlos@pfizer.com', '09179876543', '456 Wellness Ave.', 'Quezon City'),
('Novartis Pharma', 'Ana Gonzales', 'ana@novartis.com', '09175551234', '789 Medical Rd.', 'Makati'),
('Bayer Healthcare', 'David Tan', 'david@bayer.com', '09174443322', '321 Science Dr.', 'Taguig'),
('Roche Pharmaceuticals', 'Lisa Chen', 'lisa@roche.com', '09172221133', '654 Commerce Blvd.', 'Pasig'),
('Sanofi-Aventis', 'Jose Rizal', 'jose@sanofi.com', '09176667788', '987 Pharma Ave.', 'Mandaluyong'),
('Emzor Pharmaceuticals', 'Grace Lee', 'grace@emzor.com', '09175558899', '147 Health Dr.', 'Manila'),
('Johnson & Johnson', 'Mark Tan', 'mark@jnj.com', '09174445566', '258 Medical Ave.', 'Quezon City'),
('Merck & Co.', 'Sofia Garcia', 'sofia@merck.com', '09173332211', '369 Science Blvd.', 'Taguig'),
('Novo Nordisk', 'Peter Cruz', 'peter@novonordisk.com', '09172223344', '159 Diabetes Rd.', 'Makati'),
('AstraZeneca', 'Luna Reyes', 'luna@astrazeneca.com', '09171112233', '753 Respiratory St.', 'Pasig'),
('Takeda Pharma', 'Ken Watanabe', 'ken@takeda.com', '09174447788', '951 Gastro Ave.', 'Manila'),
('UNICEF Supply', 'UNICEF PH', 'unicef@supply.org', '09170001122', 'UN Building, Taft Ave.', 'Manila'),
('UCB Pharma', 'Anna Santos', 'anna@ucb.com', '09175556677', '123 Allergy St.', 'Quezon City'),
('Neurobion (Merck)', 'Neurobion Sales', 'neurobion@merck.com', '09179998877', '456 Vitamin Dr.', 'Makati'),
('Fidson Healthcare', 'Bola Adeyemi', 'bola@fidson.com', '09178889900', '789 Supplement Rd.', 'Manila'),
('Baxter Healthcare', 'Nina Cruz', 'nina@baxter.com', '09177778899', '321 IV Fluid Ave.', 'Pasig');

-- Insert 30 drugs matching the requested dataset
INSERT IGNORE INTO drugs (
    drug_name, generic_name, brand_name, category_id, batch_number, manufacturer, supplier_id,
    quantity, min_stock, unit_price, expiry_date, manufacturing_date, storage_location,
    description, status, created_by
) VALUES
('Paracetamol 500mg Tablet', 'Paracetamol', 'Panadol', 1, 'BAT240001', 'GSK', 1, 500, 50, 8.50, '2028-06-15', '2024-06-15', 'Shelf A1', 'Used to relieve mild to moderate pain and reduce fever.', 'available', 1),
('Ibuprofen 400mg Tablet', 'Ibuprofen', 'Brufen', 1, 'BAT240002', 'Pfizer', 2, 350, 50, 10.25, '2028-03-20', '2024-03-20', 'Shelf A2', 'Non-steroidal anti-inflammatory drug used for pain and inflammation.', 'available', 1),
('Diclofenac 50mg Tablet', 'Diclofenac Sodium', 'Voltaren', 1, 'BAT240003', 'Novartis', 3, 220, 35, 12.80, '2027-11-30', '2024-11-30', 'Shelf A3', 'Used for arthritis, muscle pain, and inflammation.', 'available', 1),
('Aspirin 75mg Tablet', 'Acetylsalicylic Acid', 'Bayer Aspirin', 1, 'BAT240004', 'Bayer', 4, 180, 30, 6.90, '2028-01-18', '2024-01-18', 'Cabinet B2', 'Used for pain relief and prevention of blood clots.', 'available', 1),
('Amoxicillin 500mg Capsule', 'Amoxicillin', 'Amoxil', 2, 'BAT240005', 'GSK', 1, 420, 50, 16.40, '2028-08-25', '2024-08-25', 'Shelf B1', 'Broad-spectrum antibiotic used for bacterial infections.', 'available', 1),
('Azithromycin 500mg Tablet', 'Azithromycin', 'Zithromax', 2, 'BAT240006', 'Pfizer', 2, 160, 35, 21.50, '2027-10-10', '2024-10-10', 'Shelf B2', 'Treats respiratory, ear, and skin infections.', 'available', 1),
('Ciprofloxacin 500mg Tablet', 'Ciprofloxacin', 'Cipro', 2, 'BAT240007', 'Roche', 5, 250, 40, 18.70, '2028-05-05', '2024-05-05', 'Shelf B3', 'Used for urinary tract and bacterial infections.', 'available', 1),
('Metronidazole 400mg Tablet', 'Metronidazole', 'Flagyl', 2, 'BAT240008', 'Emzor', 7, 300, 30, 14.10, '2027-12-22', '2024-12-22', 'Shelf B4', 'Treats anaerobic bacterial and parasitic infections.', 'available', 1),
('Ceftriaxone 1g Injection', 'Ceftriaxone', 'Rocephin', 2, 'BAT240009', 'Roche', 5, 120, 20, 48.00, '2028-09-12', '2024-09-12', 'Cold Room 1', 'Injectable antibiotic for severe infections.', 'available', 1),
('Gentamicin Injection', 'Gentamicin', 'Garamycin', 2, 'BAT240010', 'Sanofi', 6, 90, 25, 32.50, '2027-07-30', '2024-07-30', 'Cold Room 1', 'Used to treat serious bacterial infections.', 'low_stock', 1),
('Fluconazole 150mg Capsule', 'Fluconazole', 'Diflucan', 3, 'BAT240011', 'Pfizer', 2, 140, 30, 19.90, '2028-04-15', '2024-04-15', 'Shelf C1', 'Treats fungal infections including candidiasis.', 'available', 1),
('Clotrimazole Cream', 'Clotrimazole', 'Canesten', 3, 'BAT240012', 'Bayer', 4, 75, 35, 11.60, '2027-09-18', '2024-09-18', 'Shelf C2', 'Topical treatment for fungal skin infections.', 'low_stock', 1),
('Ketoconazole Shampoo', 'Ketoconazole', 'Nizoral', 3, 'BAT240013', 'Johnson & Johnson', 8, 95, 25, 15.75, '2028-02-14', '2024-02-14', 'Shelf C3', 'Used for dandruff and fungal scalp infections.', 'available', 1),
('Amlodipine 5mg Tablet', 'Amlodipine', 'Norvasc', 4, 'BAT240014', 'Pfizer', 2, 280, 50, 13.20, '2029-01-08', '2024-01-08', 'Shelf D1', 'Used to control high blood pressure.', 'available', 1),
('Losartan 50mg Tablet', 'Losartan', 'Cozaar', 4, 'BAT240015', 'Novartis', 3, 230, 40, 17.30, '2028-11-11', '2024-11-11', 'Shelf D2', 'Helps lower blood pressure and protect kidney function.', 'available', 1),
('Metformin 500mg Tablet', 'Metformin', 'Glucophage', 5, 'BAT240016', 'Merck', 9, 400, 45, 9.80, '2028-07-09', '2024-07-09', 'Shelf E1', 'First-line medication for Type 2 diabetes.', 'available', 1),
('Human Insulin Injection', 'Human Insulin', 'Actrapid', 5, 'BAT240017', 'Novo Nordisk', 10, 60, 20, 88.40, '2027-06-01', '2024-06-01', 'Cold Room 1', 'Controls blood glucose levels in diabetic patients.', 'low_stock', 1),
('Omeprazole 20mg Capsule', 'Omeprazole', 'Losec', 6, 'BAT240018', 'AstraZeneca', 11, 310, 35, 11.20, '2028-08-08', '2024-08-08', 'Shelf F1', 'Reduces stomach acid and treats ulcers.', 'available', 1),
('Pantoprazole 40mg Tablet', 'Pantoprazole', 'Protonix', 6, 'BAT240019', 'Takeda', 12, 200, 30, 12.90, '2028-03-17', '2024-03-17', 'Shelf F2', 'Used for acid reflux and gastric ulcers.', 'available', 1),
('ORS Sachet', 'Oral Rehydration Salts', 'Hydralyte', 6, 'BAT240020', 'UNICEF Supply', 13, 500, 60, 5.20, '2029-05-10', '2024-05-10', 'Shelf F3', 'Replaces fluids and electrolytes lost through diarrhea.', 'available', 1),
('Salbutamol Inhaler', 'Salbutamol', 'Ventolin', 7, 'BAT240021', 'GSK', 1, 130, 25, 34.50, '2028-01-25', '2024-01-25', 'Shelf G1', 'Relieves asthma and bronchospasm symptoms.', 'available', 1),
('Budesonide Inhaler', 'Budesonide', 'Pulmicort', 7, 'BAT240022', 'AstraZeneca', 11, 80, 25, 41.80, '2027-08-14', '2024-08-14', 'Shelf G2', 'Corticosteroid inhaler for asthma management.', 'low_stock', 1),
('Cetirizine 10mg Tablet', 'Cetirizine', 'Zyrtec', 8, 'BAT240023', 'UCB Pharma', 14, 340, 35, 7.80, '2028-12-20', '2024-12-20', 'Shelf H1', 'Treats allergies and hay fever symptoms.', 'available', 1),
('Loratadine 10mg Tablet', 'Loratadine', 'Claritin', 8, 'BAT240024', 'Bayer', 4, 190, 30, 8.30, '2028-09-09', '2024-09-09', 'Shelf H2', 'Provides long-lasting allergy relief.', 'available', 1),
('Vitamin C 500mg Tablet', 'Ascorbic Acid', 'Celin', 9, 'BAT240025', 'Emzor', 7, 600, 50, 6.10, '2029-02-28', '2024-02-28', 'Shelf I1', 'Supports immune function and wound healing.', 'available', 1),
('Vitamin B Complex Tablet', 'Vitamin B Complex', 'Neurobion', 9, 'BAT240026', 'Neurobion (Merck)', 15, 270, 45, 8.60, '2028-10-15', '2024-10-15', 'Shelf I2', 'Supports nerve health and metabolism.', 'available', 1),
('Zinc Sulfate 20mg Tablet', 'Zinc Sulfate', 'Zincolak', 9, 'BAT240027', 'Fidson Healthcare', 16, 240, 25, 7.40, '2028-04-21', '2024-04-21', 'Shelf I3', 'Used to prevent and treat zinc deficiency.', 'available', 1),
('Hydrocortisone 1% Cream', 'Hydrocortisone', 'Cortef', 10, 'BAT240028', 'Pfizer', 2, 110, 20, 14.20, '2027-12-01', '2024-12-01', 'Shelf J1', 'Reduces skin inflammation and allergic reactions.', 'available', 1),
('Adrenaline Injection', 'Epinephrine', 'EpiPen', 11, 'BAT240029', 'Sanofi', 6, 40, 15, 52.00, '2027-05-28', '2024-05-28', 'Emergency Cabinet', 'Emergency treatment for severe allergic reactions (anaphylaxis).', 'low_stock', 1),
('Dextrose 5% IV Fluid', 'Dextrose Solution', 'Baxter D5W', 12, 'BAT240030', 'Baxter', 17, 150, 30, 22.10, '2028-06-30', '2024-06-30', 'Rack C3', 'Provides hydration and energy through intravenous infusion.', 'available', 1);

-- Insert initial stock transactions for all drugs
INSERT IGNORE INTO stock_transactions (drug_id, transaction_type, quantity, previous_quantity, new_quantity, unit_price, reference_number, remarks, performed_by)
SELECT id, 'stock_in', quantity, 0, quantity, unit_price, CONCAT('STKIN-', DATE_FORMAT(NOW(), '%Y%m%d'), '-', LPAD(ROW_NUMBER() OVER (ORDER BY id), 6, '0')), 'Initial stock', 1 FROM drugs;
