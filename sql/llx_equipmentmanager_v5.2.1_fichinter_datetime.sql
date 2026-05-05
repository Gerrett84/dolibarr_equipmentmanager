-- v5.2.1: Fix dateo/datee column type date -> datetime so that time-of-day is preserved
-- MySQL/MariaDB silently drops the time part when storing into a DATE column.
-- Existing rows keep their date; time is set to 00:00:00 (unchanged behaviour).
ALTER TABLE llx_fichinter
    MODIFY COLUMN dateo datetime,
    MODIFY COLUMN datee datetime;
