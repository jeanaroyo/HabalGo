-- HabalGo - Activity 4: Database Roles and Access Control (Least Privilege)


-- 1. Create Group Roles (NOLOGIN groups to bundle permissions)
CREATE ROLE app_runtime_grp NOLOGIN;
CREATE ROLE app_readonly_grp NOLOGIN;

-- 2. Create Application Login Accounts 
-- Note: Replace placeholders with secure local passwords when deploying
CREATE ROLE habalgo_app_user LOGIN PASSWORD 'HabalGoSecurePass2026!';
CREATE ROLE habalgo_readonly_user LOGIN PASSWORD 'HabalGoReadOnly2026!';

-- 3. Assign Login Accounts to Group Roles
GRANT app_runtime_grp TO habalgo_app_user;
GRANT app_readonly_grp TO habalgo_readonly_user;

-- 4. Grant Database Connection Access
GRANT CONNECT ON DATABASE grp00_habalgodb TO app_runtime_grp, app_readonly_grp;

-- 5. Grant Schema Usage Access
-- Must be executed while connected to the grp00_habalgodb database
GRANT USAGE ON SCHEMA habalgo TO app_runtime_grp, app_readonly_grp;

-- 6. Grant Object-Level Privileges for Runtime Application Users
-- App user needs full CRUD (SELECT, INSERT, UPDATE, DELETE) on tables
GRANT SELECT, INSERT, UPDATE, DELETE ON ALL TABLES IN SCHEMA habalgo TO app_runtime_grp;

-- Grant usage on auto-incrementing serial sequences so inserts work properly
GRANT USAGE, SELECT ON ALL SEQUENCES IN SCHEMA habalgo TO app_runtime_grp;

-- 7. Grant Object-Level Privileges for Read-Only / Auditor Accounts
-- Read-only user can only view records (SELECT)
GRANT SELECT ON ALL TABLES IN SCHEMA habalgo TO app_readonly_grp;

-- 8. Ensure Future Tables in the Schema Automatically Inherit These Permissions
ALTER DEFAULT PRIVILEGES IN SCHEMA habalgo 
    GRANT SELECT, INSERT, UPDATE, DELETE ON TABLES TO app_runtime_grp;

ALTER DEFAULT PRIVILEGES IN SCHEMA habalgo 
    GRANT USAGE, SELECT ON SEQUENCES TO app_runtime_grp;

ALTER DEFAULT PRIVILEGES IN SCHEMA habalgo 
    GRANT SELECT ON TABLES TO app_readonly_grp;