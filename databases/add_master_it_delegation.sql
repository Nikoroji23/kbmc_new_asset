-- Master IT Delegation System
-- Allows primary Master IT to grant temporary delegation to other IT staff

ALTER TABLE users ADD COLUMN IF NOT EXISTS master_it_delegation_until DATETIME NULL COMMENT 'Timestamp until which this user has Master IT delegation';
ALTER TABLE users ADD COLUMN IF NOT EXISTS delegated_by_user_id INT NULL COMMENT 'User ID who granted the delegation';

-- Create index for delegation queries
CREATE INDEX IF NOT EXISTS idx_master_it_delegation ON users(role, master_it_delegation_until);
