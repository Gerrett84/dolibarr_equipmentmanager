-- v5.2.4: Add optional work start/end time for time-range based entry mode
ALTER TABLE llx_equipmentmanager_intervention_detail
    ADD COLUMN work_start_time TIME NULL AFTER work_date,
    ADD COLUMN work_end_time   TIME NULL AFTER work_start_time;
