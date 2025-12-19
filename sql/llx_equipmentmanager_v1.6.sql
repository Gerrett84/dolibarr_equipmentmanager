-- Equipment Manager v1.6 - Serviceberichte Integration
-- Tabellen für Equipment-Details und Material-Verbuchung

-- Tabelle 1: Equipment Details (Berichtstexte)
CREATE TABLE IF NOT EXISTS llx_equipmentmanager_intervention_detail (
    rowid INT AUTO_INCREMENT PRIMARY KEY,
    fk_intervention INT NOT NULL,
    fk_equipment INT NOT NULL,
    
    -- Berichtsfelder
    report_text TEXT,
    work_done TEXT,
    issues_found TEXT,
    recommendations TEXT,
    notes TEXT,
    
    -- Zeit-Tracking NEU
    work_date DATE,
    work_duration INT DEFAULT 0,
    
    -- Metadaten
    date_creation DATETIME NOT NULL,
    tms TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    fk_user_creat INT,
    fk_user_modif INT,
    
    -- Indices
    INDEX idx_intervention (fk_intervention),
    INDEX idx_equipment (fk_equipment),
    UNIQUE KEY uk_intervention_equipment (fk_intervention, fk_equipment)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabelle 2: Material-Verbuchung
CREATE TABLE IF NOT EXISTS llx_equipmentmanager_intervention_material (
    rowid INT AUTO_INCREMENT PRIMARY KEY,
    fk_intervention INT NOT NULL,
    fk_equipment INT NOT NULL,
    fk_product INT DEFAULT NULL,
    
    -- Material-Details
    material_name VARCHAR(255) NOT NULL,
    material_description TEXT,
    quantity DECIMAL(10,2) NOT NULL DEFAULT 1,
    unit VARCHAR(50) DEFAULT 'Stk',
    unit_price DECIMAL(10,2) DEFAULT 0,
    total_price DECIMAL(10,2) DEFAULT 0,
    
    -- Zusatzinfos
    serial_number VARCHAR(255),
    notes TEXT,
    
    -- Metadaten
    date_creation DATETIME NOT NULL,
    tms TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    fk_user_creat INT,
    fk_user_modif INT,
    
    -- Indices
    INDEX idx_intervention (fk_intervention),
    INDEX idx_equipment (fk_equipment),
    INDEX idx_product (fk_product)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Prüfen der Erstellung
SELECT 
    'intervention_detail' as table_name,
    COUNT(*) as row_count 
FROM llx_equipmentmanager_intervention_detail
UNION ALL
SELECT 
    'intervention_material' as table_name,
    COUNT(*) as row_count 
FROM llx_equipmentmanager_intervention_material;