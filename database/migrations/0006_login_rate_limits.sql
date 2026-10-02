SET XACT_ABORT ON;
GO
CREATE TABLE dbo.login_rate_limits (
    action_code varchar(40) NOT NULL,
    dimension_hash binary(32) NOT NULL,
    window_started_at datetime2(3) NOT NULL,
    attempt_count int NOT NULL CONSTRAINT DF_login_rate_attempts DEFAULT(0),
    blocked_until datetime2(3) NULL,
    updated_at datetime2(3) NOT NULL CONSTRAINT DF_login_rate_updated DEFAULT(SYSUTCDATETIME()),
    CONSTRAINT PK_login_rate_limits PRIMARY KEY(action_code,dimension_hash),
    CONSTRAINT CK_login_rate_attempts CHECK(attempt_count>=0)
);
GO
CREATE INDEX IX_login_rate_limits_cleanup ON dbo.login_rate_limits(updated_at);
GO
