-- CEDAR Wellness Hub: HR-assigned custom shift and scheduled break support
-- Run once in phpMyAdmin against the cedar_wellness_hub database.
ALTER TABLE schedules
  ADD COLUMN break_start TIME DEFAULT NULL AFTER shift_end,
  ADD COLUMN break_end TIME DEFAULT NULL AFTER break_start;
