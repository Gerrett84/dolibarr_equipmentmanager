-- Equipment Manager v5.0.0
-- Akku-Felder für Schiebetüranlagen (door_sliding)
-- Rauchmelder-Felder für Drehtür (door_swing), Feststellanlage (hold_open), Brandschutztor (fire_gate)

ALTER TABLE llx_equipmentmanager_equipment
    ADD COLUMN IF NOT EXISTS battery_install_month TINYINT UNSIGNED NULL AFTER fk_contract,
    ADD COLUMN IF NOT EXISTS battery_install_year SMALLINT UNSIGNED NULL AFTER battery_install_month,
    ADD COLUMN IF NOT EXISTS battery_replacement_cycle TINYINT UNSIGNED NULL AFTER battery_install_year,
    ADD COLUMN IF NOT EXISTS smoke_detector_install_month TINYINT UNSIGNED NULL AFTER battery_replacement_cycle,
    ADD COLUMN IF NOT EXISTS smoke_detector_install_year SMALLINT UNSIGNED NULL AFTER smoke_detector_install_month,
    ADD COLUMN IF NOT EXISTS smoke_detector_replacement_cycle TINYINT UNSIGNED NULL AFTER smoke_detector_install_year;
