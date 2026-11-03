-- 010: per-admin session generation, bumped whenever the password changes
ALTER TABLE admins
    ADD COLUMN auth_version INT UNSIGNED NOT NULL DEFAULT 1 AFTER password_hash;
