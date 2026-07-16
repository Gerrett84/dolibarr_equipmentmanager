-- Equipment Manager v6.0.0 - Safety Analysis (Sicherheitsanalyse)
-- Risikobeurteilung gemäß DIN EN 16005 / Maschinenrichtlinie 2006/42/EG
-- für automatische Schiebetüren (FTA-Format)

CREATE TABLE IF NOT EXISTS llx_equipmentmanager_safety_analysis (
    rowid           INT AUTO_INCREMENT PRIMARY KEY,
    fk_equipment    INT NOT NULL,
    fk_fichinter    INT NOT NULL,
    fk_user_creat   INT NOT NULL,
    date_creation   DATETIME NOT NULL,
    tms             TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    status          TINYINT DEFAULT 0,          -- 0=Entwurf, 1=Abgeschlossen

    -- Türdaten (Seite 1 Formular)
    einbauort           VARCHAR(255),
    antriebstyp         VARCHAR(100),
    durchgangshoehe     INT,                    -- mm
    durchgangsbreite    INT,                    -- mm
    bauliche_gegebenheiten TEXT,

    -- Schutzmaßnahmen (Seite 2) als JSON
    -- Struktur: {"schliesfahrt":{...}, "oeffnungsfahrt":{...}}
    form_data           TEXT,

    -- Unterschrift 1: Ersteller (vor der Arbeit)
    sig_ersteller       MEDIUMTEXT,             -- base64 PNG
    sig_ersteller_name  VARCHAR(255),
    sig_ersteller_ort   VARCHAR(100),
    sig_ersteller_date  DATE,

    -- Unterschrift 2: Monteur (nach der Arbeit)
    sig_monteur         MEDIUMTEXT,
    sig_monteur_name    VARCHAR(255),
    sig_monteur_ort     VARCHAR(100),
    sig_monteur_date    DATE,

    -- Unterschrift 3: Kunde (nach der Arbeit)
    sig_kunde           MEDIUMTEXT,
    sig_kunde_name      VARCHAR(255),
    sig_kunde_ort       VARCHAR(100),
    sig_kunde_date      DATE,

    INDEX idx_sa_equipment  (fk_equipment),
    INDEX idx_sa_fichinter  (fk_fichinter),
    INDEX idx_sa_status     (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
