-- Run once on existing installations, after backing up the database.
ALTER TABLE users ADD COLUMN first_name VARCHAR(60) NULL, ADD COLUMN last_name VARCHAR(60) NULL;
ALTER TABLE branches ADD COLUMN hr_capacity SMALLINT UNSIGNED NOT NULL DEFAULT 1,
 ADD COLUMN address_line VARCHAR(100) NULL, ADD COLUMN barangay VARCHAR(80) NULL,
 ADD COLUMN city VARCHAR(80) NULL, ADD COLUMN province VARCHAR(80) NULL,
 ADD COLUMN postal_code VARCHAR(4) NULL;
UPDATE users u JOIN employees e ON e.user_id=u.id SET u.first_name=e.first_name,u.last_name=e.last_name WHERE u.role='hr';
-- Retain the display address for compatibility. Existing free-text addresses are
-- deliberately not guessed: complete structured fields when editing each branch.
-- Grandfather existing staffing counts; new branches default to one active HR.
UPDATE branches b SET hr_capacity=GREATEST(1,(SELECT COUNT(*) FROM user_branch_assignments a JOIN users u ON u.id=a.user_id WHERE a.branch_id=b.id AND u.role='hr' AND u.status='active'));
