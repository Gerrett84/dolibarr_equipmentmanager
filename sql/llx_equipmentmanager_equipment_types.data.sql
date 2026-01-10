-- Default equipment types
-- Copyright (C) 2024-2025 Equipment Manager Module

INSERT INTO llx_equipmentmanager_equipment_types (code, label, position, active, date_creation, entity) VALUES
('door_swing', 'DoorSwing', 10, 1, NOW(), 1),
('door_sliding', 'DoorSliding', 20, 1, NOW(), 1),
('fire_door', 'FireDoor', 30, 1, NOW(), 1),
('fire_gate', 'FireGate', 35, 1, NOW(), 1),
('door_closer', 'DoorCloser', 40, 1, NOW(), 1),
('hold_open', 'HoldOpen', 50, 1, NOW(), 1),
('rws', 'RWS', 60, 1, NOW(), 1),
('rwa', 'RWA', 70, 1, NOW(), 1),
('other', 'Other', 999, 1, NOW(), 1);
