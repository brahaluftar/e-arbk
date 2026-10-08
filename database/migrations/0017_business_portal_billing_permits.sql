SET XACT_ABORT ON;
GO

ALTER TABLE dbo.app_users DROP CONSTRAINT CK_app_users_role;
ALTER TABLE dbo.app_users ADD CONSTRAINT CK_app_users_role
    CHECK (role_code IN ('ADMIN','OFFICIAL','READ_ONLY','BUSINESS'));
GO

CREATE TABLE dbo.business_user_links (
    id bigint IDENTITY(1,1) NOT NULL CONSTRAINT PK_business_user_links PRIMARY KEY,
    user_id bigint NOT NULL,
    business_id int NOT NULL,
    assigned_by_user_id bigint NOT NULL,
    assigned_at datetime2(3) NOT NULL CONSTRAINT DF_business_user_links_assigned DEFAULT SYSUTCDATETIME(),
    ended_at datetime2(3) NULL,
    ended_by_user_id bigint NULL,
    end_reason nvarchar(300) NULL,
    CONSTRAINT FK_business_user_links_user FOREIGN KEY(user_id) REFERENCES dbo.app_users(id),
    CONSTRAINT FK_business_user_links_business FOREIGN KEY(business_id) REFERENCES dbo.ARBK_LIST(REGULATION_ID),
    CONSTRAINT FK_business_user_links_assigner FOREIGN KEY(assigned_by_user_id) REFERENCES dbo.app_users(id),
    CONSTRAINT FK_business_user_links_ender FOREIGN KEY(ended_by_user_id) REFERENCES dbo.app_users(id),
    CONSTRAINT CK_business_user_links_period CHECK(ended_at IS NULL OR ended_at>=assigned_at)
);
CREATE UNIQUE INDEX UX_business_user_links_active ON dbo.business_user_links(user_id,business_id) WHERE ended_at IS NULL;
CREATE INDEX IX_business_user_links_business ON dbo.business_user_links(business_id,ended_at) INCLUDE(user_id);
GO

CREATE TABLE dbo.business_registration_invites (
    id bigint IDENTITY(1,1) NOT NULL CONSTRAINT PK_business_registration_invites PRIMARY KEY,
    email nvarchar(254) NOT NULL,
    normalized_email nvarchar(254) NOT NULL,
    token_hash binary(32) NOT NULL,
    expires_at datetime2(3) NOT NULL,
    created_by_user_id bigint NOT NULL,
    created_at datetime2(3) NOT NULL CONSTRAINT DF_business_registration_invites_created DEFAULT SYSUTCDATETIME(),
    opened_at datetime2(3) NULL,
    consumed_at datetime2(3) NULL,
    consumed_by_user_id bigint NULL,
    CONSTRAINT FK_business_registration_invites_creator FOREIGN KEY(created_by_user_id) REFERENCES dbo.app_users(id),
    CONSTRAINT FK_business_registration_invites_consumer FOREIGN KEY(consumed_by_user_id) REFERENCES dbo.app_users(id),
    CONSTRAINT UQ_business_registration_invites_token UNIQUE(token_hash),
    CONSTRAINT CK_business_registration_invites_expiry CHECK(expires_at>created_at)
);
CREATE INDEX IX_business_registration_invites_email ON dbo.business_registration_invites(normalized_email,created_at DESC);
GO

CREATE TABLE dbo.invoice_uniref_sequences (
    nace_code char(4) NOT NULL CONSTRAINT PK_invoice_uniref_sequences PRIMARY KEY,
    last_value int NOT NULL,
    updated_at datetime2(3) NOT NULL CONSTRAINT DF_invoice_uniref_sequences_updated DEFAULT SYSUTCDATETIME(),
    CONSTRAINT CK_invoice_uniref_sequences_value CHECK(last_value BETWEEN 1 AND 999999),
    CONSTRAINT CK_invoice_uniref_sequences_nace CHECK(nace_code NOT LIKE '%[^0-9A-Z]%')
);
GO

ALTER TABLE dbo.business_invoices ADD
    uniref char(16) NULL,
    status_code varchar(20) NOT NULL CONSTRAINT DF_business_invoices_status DEFAULT 'ISSUED',
    period_start date NULL,
    period_end date NULL,
    billed_months tinyint NULL,
    annual_tariff decimal(18,2) NULL,
    currency char(3) NOT NULL CONSTRAINT DF_business_invoices_currency DEFAULT 'EUR',
    business_name_snapshot nvarchar(255) NULL,
    business_address_snapshot nvarchar(500) NULL,
    fiscal_number_snapshot varchar(30) NULL,
    registration_number_snapshot varchar(20) NULL,
    registered_nace_code_snapshot varchar(20) NULL,
    tariff_nace_code_snapshot varchar(20) NULL,
    nace_description_snapshot nvarchar(510) NULL,
    pdf_storage_key nvarchar(300) NULL,
    emailed_at datetime2(3) NULL;
GO
ALTER TABLE dbo.business_invoices ADD
    CONSTRAINT CK_business_invoices_status CHECK(status_code IN ('ISSUED','PARTIALLY_PAID','PAID','CANCELLED')),
    CONSTRAINT CK_business_invoices_period CHECK(period_start IS NULL OR (period_end>=period_start AND billed_months BETWEEN 1 AND 12)),
    CONSTRAINT CK_business_invoices_uniref CHECK(uniref IS NULL OR (LEN(uniref)=16 AND uniref LIKE 'PREAH%'));
CREATE UNIQUE INDEX UX_business_invoices_uniref ON dbo.business_invoices(uniref) WHERE uniref IS NOT NULL;
GO

CREATE TABLE dbo.invoice_appeals (
    id bigint IDENTITY(1,1) NOT NULL CONSTRAINT PK_invoice_appeals PRIMARY KEY,
    invoice_id bigint NOT NULL,
    submitted_by_user_id bigint NOT NULL,
    subject nvarchar(200) NOT NULL,
    appeal_text nvarchar(max) NOT NULL,
    status_code varchar(30) NOT NULL CONSTRAINT DF_invoice_appeals_status DEFAULT 'SUBMITTED',
    coordinator_user_id bigint NULL,
    commission_reference nvarchar(100) NULL,
    review_notes nvarchar(max) NULL,
    decision_code varchar(20) NULL,
    decision_text nvarchar(max) NULL,
    submitted_at datetime2(3) NOT NULL CONSTRAINT DF_invoice_appeals_submitted DEFAULT SYSUTCDATETIME(),
    decided_at datetime2(3) NULL,
    answered_at datetime2(3) NULL,
    row_version rowversion NOT NULL,
    CONSTRAINT FK_invoice_appeals_invoice FOREIGN KEY(invoice_id) REFERENCES dbo.business_invoices(id),
    CONSTRAINT FK_invoice_appeals_submitter FOREIGN KEY(submitted_by_user_id) REFERENCES dbo.app_users(id),
    CONSTRAINT FK_invoice_appeals_coordinator FOREIGN KEY(coordinator_user_id) REFERENCES dbo.app_users(id),
    CONSTRAINT CK_invoice_appeals_status CHECK(status_code IN ('SUBMITTED','ACCEPTED','COORDINATOR','COMMISSION','UNDER_REVIEW','DECIDED','ANSWERED','REJECTED')),
    CONSTRAINT CK_invoice_appeals_decision CHECK(decision_code IS NULL OR decision_code IN ('APPROVED','PARTIALLY_APPROVED','REJECTED'))
);
CREATE INDEX IX_invoice_appeals_workflow ON dbo.invoice_appeals(status_code,submitted_at,id);
GO

CREATE TABLE dbo.invoice_appeal_events (
    id bigint IDENTITY(1,1) NOT NULL CONSTRAINT PK_invoice_appeal_events PRIMARY KEY,
    appeal_id bigint NOT NULL,
    from_status varchar(30) NULL,
    to_status varchar(30) NOT NULL,
    comment nvarchar(max) NULL,
    actor_user_id bigint NOT NULL,
    created_at datetime2(3) NOT NULL CONSTRAINT DF_invoice_appeal_events_created DEFAULT SYSUTCDATETIME(),
    CONSTRAINT FK_invoice_appeal_events_appeal FOREIGN KEY(appeal_id) REFERENCES dbo.invoice_appeals(id),
    CONSTRAINT FK_invoice_appeal_events_actor FOREIGN KEY(actor_user_id) REFERENCES dbo.app_users(id)
);
CREATE INDEX IX_invoice_appeal_events_appeal ON dbo.invoice_appeal_events(appeal_id,created_at,id);
GO

CREATE TABLE dbo.business_permits (
    id bigint IDENTITY(1,1) NOT NULL CONSTRAINT PK_business_permits PRIMARY KEY,
    business_id int NOT NULL,
    serial_number varchar(50) NOT NULL,
    issued_on date NOT NULL,
    valid_from date NOT NULL,
    valid_until date NOT NULL,
    status_code varchar(20) NOT NULL CONSTRAINT DF_business_permits_status DEFAULT 'ACTIVE',
    business_name_snapshot nvarchar(255) NOT NULL,
    business_address_snapshot nvarchar(500) NULL,
    registered_nace_code_snapshot varchar(20) NULL,
    registered_nace_activity_snapshot nvarchar(510) NULL,
    tariff_nace_code_snapshot varchar(20) NULL,
    nace_description_snapshot nvarchar(510) NULL,
    public_selector char(16) NOT NULL,
    public_secret_hash binary(32) NOT NULL,
    pdf_storage_key nvarchar(300) NULL,
    issued_by_user_id bigint NOT NULL,
    issued_at datetime2(3) NOT NULL CONSTRAINT DF_business_permits_issued DEFAULT SYSUTCDATETIME(),
    revoked_at datetime2(3) NULL,
    revoked_by_user_id bigint NULL,
    revocation_reason nvarchar(500) NULL,
    row_version rowversion NOT NULL,
    CONSTRAINT FK_business_permits_business FOREIGN KEY(business_id) REFERENCES dbo.ARBK_LIST(REGULATION_ID),
    CONSTRAINT FK_business_permits_issuer FOREIGN KEY(issued_by_user_id) REFERENCES dbo.app_users(id),
    CONSTRAINT FK_business_permits_revoker FOREIGN KEY(revoked_by_user_id) REFERENCES dbo.app_users(id),
    CONSTRAINT UQ_business_permits_serial UNIQUE(serial_number),
    CONSTRAINT UQ_business_permits_selector UNIQUE(public_selector),
    CONSTRAINT CK_business_permits_dates CHECK(valid_until>=valid_from AND valid_from>=issued_on),
    CONSTRAINT CK_business_permits_status CHECK(status_code IN ('ACTIVE','EXPIRED','REVOKED'))
);
CREATE INDEX IX_business_permits_business ON dbo.business_permits(business_id,status_code,valid_until DESC);
GO

CREATE TABLE dbo.outbound_messages (
    id bigint IDENTITY(1,1) NOT NULL CONSTRAINT PK_outbound_messages PRIMARY KEY,
    message_type varchar(40) NOT NULL,
    recipient_email nvarchar(254) NOT NULL,
    subject nvarchar(300) NOT NULL,
    related_entity_type varchar(50) NULL,
    related_entity_id bigint NULL,
    status_code varchar(20) NOT NULL CONSTRAINT DF_outbound_messages_status DEFAULT 'QUEUED',
    graph_message_id nvarchar(300) NULL,
    created_by_user_id bigint NULL,
    queued_at datetime2(3) NOT NULL CONSTRAINT DF_outbound_messages_queued DEFAULT SYSUTCDATETIME(),
    sent_at datetime2(3) NULL,
    failed_at datetime2(3) NULL,
    failure_message nvarchar(1000) NULL,
    CONSTRAINT FK_outbound_messages_creator FOREIGN KEY(created_by_user_id) REFERENCES dbo.app_users(id),
    CONSTRAINT CK_outbound_messages_status CHECK(status_code IN ('QUEUED','SENT','FAILED'))
);
CREATE INDEX IX_outbound_messages_report ON dbo.outbound_messages(status_code,queued_at DESC);
GO

CREATE TABLE dbo.message_action_links (
    id bigint IDENTITY(1,1) NOT NULL CONSTRAINT PK_message_action_links PRIMARY KEY,
    message_id bigint NOT NULL,
    action_code varchar(50) NOT NULL,
    selector char(16) NOT NULL,
    secret_hash binary(32) NOT NULL,
    target_path nvarchar(500) NOT NULL,
    expires_at datetime2(3) NULL,
    first_opened_at datetime2(3) NULL,
    completed_at datetime2(3) NULL,
    open_count int NOT NULL CONSTRAINT DF_message_action_links_open_count DEFAULT 0,
    CONSTRAINT FK_message_action_links_message FOREIGN KEY(message_id) REFERENCES dbo.outbound_messages(id),
    CONSTRAINT UQ_message_action_links_selector UNIQUE(selector),
    CONSTRAINT CK_message_action_links_count CHECK(open_count>=0)
);
GO

CREATE TABLE dbo.message_events (
    id bigint IDENTITY(1,1) NOT NULL CONSTRAINT PK_message_events PRIMARY KEY,
    message_id bigint NOT NULL,
    action_link_id bigint NULL,
    event_code varchar(30) NOT NULL,
    actor_user_id bigint NULL,
    ip_hash binary(32) NULL,
    user_agent_hash binary(32) NULL,
    created_at datetime2(3) NOT NULL CONSTRAINT DF_message_events_created DEFAULT SYSUTCDATETIME(),
    metadata_json nvarchar(max) NULL,
    CONSTRAINT FK_message_events_message FOREIGN KEY(message_id) REFERENCES dbo.outbound_messages(id),
    CONSTRAINT FK_message_events_link FOREIGN KEY(action_link_id) REFERENCES dbo.message_action_links(id),
    CONSTRAINT FK_message_events_actor FOREIGN KEY(actor_user_id) REFERENCES dbo.app_users(id),
    CONSTRAINT CK_message_events_code CHECK(event_code IN ('QUEUED','SENT','FAILED','LINK_OPENED','ACTION_COMPLETED')),
    CONSTRAINT CK_message_events_json CHECK(metadata_json IS NULL OR ISJSON(metadata_json)=1)
);
CREATE INDEX IX_message_events_report ON dbo.message_events(event_code,created_at DESC) INCLUDE(message_id,action_link_id);
GO
