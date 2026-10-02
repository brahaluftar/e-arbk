SET XACT_ABORT ON;
GO

CREATE TABLE dbo.app_users (
    id bigint IDENTITY(1,1) NOT NULL CONSTRAINT PK_app_users PRIMARY KEY,
    email nvarchar(254) NOT NULL,
    normalized_email nvarchar(254) NOT NULL,
    password_hash nvarchar(255) NOT NULL,
    full_name nvarchar(200) NOT NULL,
    role_code varchar(20) NOT NULL,
    is_active bit NOT NULL CONSTRAINT DF_app_users_active DEFAULT (1),
    last_login_at datetime2(3) NULL,
    created_at datetime2(3) NOT NULL CONSTRAINT DF_app_users_created DEFAULT (SYSUTCDATETIME()),
    row_version rowversion NOT NULL,
    CONSTRAINT UQ_app_users_email UNIQUE (normalized_email),
    CONSTRAINT CK_app_users_role CHECK (role_code IN ('ADMIN','OFFICIAL','READ_ONLY'))
);
GO

CREATE TABLE dbo.business_atk_status (
    business_id int NOT NULL CONSTRAINT PK_business_atk_status PRIMARY KEY,
    status_code varchar(20) NOT NULL,
    matched_by varchar(30) NULL,
    matched_value varchar(40) NULL,
    atk_record_id int NULL,
    atk_status varchar(20) NULL,
    deactivated_at nvarchar(40) NULL,
    match_count int NOT NULL CONSTRAINT DF_business_atk_match_count DEFAULT (0),
    synchronized_at datetime2(3) NOT NULL CONSTRAINT DF_business_atk_sync DEFAULT (SYSUTCDATETIME()),
    row_version rowversion NOT NULL,
    CONSTRAINT CK_business_atk_status CHECK (status_code IN ('ACTIVE','DEACTIVATED','NEEDS_REVIEW')),
    CONSTRAINT CK_business_atk_count CHECK (match_count >= 0)
);
GO

CREATE TABLE dbo.business_nace_assignments (
    id bigint IDENTITY(1,1) NOT NULL CONSTRAINT PK_business_nace_assignments PRIMARY KEY,
    business_id int NOT NULL,
    original_nace_code varchar(20) NOT NULL,
    nace_list_id float NOT NULL,
    nace_category nvarchar(510) NOT NULL,
    tariff_snapshot money NULL,
    assignment_method varchar(10) NOT NULL,
    mapping_rule nvarchar(300) NOT NULL,
    assigned_by_user_id bigint NULL,
    assigned_at datetime2(3) NOT NULL CONSTRAINT DF_business_nace_assigned DEFAULT (SYSUTCDATETIME()),
    ended_at datetime2(3) NULL,
    ended_by_user_id bigint NULL,
    end_reason nvarchar(300) NULL,
    row_version rowversion NOT NULL,
    CONSTRAINT FK_business_nace_user FOREIGN KEY (assigned_by_user_id) REFERENCES dbo.app_users(id),
    CONSTRAINT FK_business_nace_ended_user FOREIGN KEY (ended_by_user_id) REFERENCES dbo.app_users(id),
    CONSTRAINT CK_business_nace_method CHECK (assignment_method IN ('AUTO','MANUAL')),
    CONSTRAINT CK_business_nace_period CHECK (ended_at IS NULL OR ended_at >= assigned_at)
);
GO
CREATE UNIQUE INDEX UX_business_nace_active ON dbo.business_nace_assignments(business_id) WHERE ended_at IS NULL;
CREATE INDEX IX_business_nace_method ON dbo.business_nace_assignments(assignment_method, assigned_at DESC) INCLUDE(business_id,nace_list_id,nace_category);
GO

CREATE TABLE dbo.audit_log (
    id bigint IDENTITY(1,1) NOT NULL CONSTRAINT PK_audit_log PRIMARY KEY,
    actor_type varchar(20) NOT NULL,
    actor_user_id bigint NULL,
    action_code varchar(100) NOT NULL,
    entity_type varchar(80) NOT NULL,
    entity_id nvarchar(100) NULL,
    previous_value_json nvarchar(max) NULL,
    new_value_json nvarchar(max) NULL,
    metadata_json nvarchar(max) NULL,
    created_at datetime2(3) NOT NULL CONSTRAINT DF_audit_log_created DEFAULT (SYSUTCDATETIME()),
    CONSTRAINT FK_audit_log_user FOREIGN KEY (actor_user_id) REFERENCES dbo.app_users(id),
    CONSTRAINT CK_audit_log_actor CHECK (actor_type IN ('USER','SYSTEM')),
    CONSTRAINT CK_audit_log_previous_json CHECK (previous_value_json IS NULL OR ISJSON(previous_value_json)=1),
    CONSTRAINT CK_audit_log_new_json CHECK (new_value_json IS NULL OR ISJSON(new_value_json)=1),
    CONSTRAINT CK_audit_log_metadata_json CHECK (metadata_json IS NULL OR ISJSON(metadata_json)=1)
);
GO
CREATE INDEX IX_audit_log_entity ON dbo.audit_log(entity_type,entity_id,created_at DESC);
CREATE INDEX IX_audit_log_action ON dbo.audit_log(action_code,created_at DESC);
GO

CREATE VIEW dbo.v_business_master AS
SELECT
    a.REGULATION_ID AS business_id,
    a.NRBIZ AS registration_number,
    CAST(NULL AS varchar(20)) AS fiscal_number,
    a.Emri AS legal_name,
    CAST(NULL AS nvarchar(255)) AS trade_name,
    CAST(NULL AS nvarchar(500)) AS business_address,
    a.Qyteti AS municipality,
    a.NACE_CODE_REG AS arbk_nace_code,
    a.NACEPERSHKRIMI AS arbk_nace_description,
    a.Statusi AS arbk_status,
    CAST(NULL AS nvarchar(254)) AS business_email,
    COALESCE(s.status_code, CASE WHEN a.ATK_MBYLLUR=1 THEN 'DEACTIVATED' ELSE 'ACTIVE' END) AS atk_status,
    s.deactivated_at,
    n.nace_list_id,
    n.nace_category,
    n.tariff_snapshot AS applied_tariff,
    n.assignment_method,
    n.assigned_at
FROM dbo.ARBK_LIST a
LEFT JOIN dbo.business_atk_status s ON s.business_id=a.REGULATION_ID
LEFT JOIN (
    SELECT business_id,nace_list_id,nace_category,tariff_snapshot,assignment_method,assigned_at
    FROM dbo.business_nace_assignments WHERE ended_at IS NULL
) n ON n.business_id=a.REGULATION_ID;
GO
