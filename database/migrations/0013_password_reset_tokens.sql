SET XACT_ABORT ON;
GO
CREATE TABLE dbo.password_reset_tokens (
    id bigint IDENTITY(1,1) NOT NULL CONSTRAINT PK_password_reset_tokens PRIMARY KEY,
    user_id bigint NOT NULL,
    token_hash binary(32) NOT NULL,
    created_at datetime2(3) NOT NULL CONSTRAINT DF_password_reset_tokens_created DEFAULT(SYSUTCDATETIME()),
    expires_at datetime2(3) NOT NULL,
    consumed_at datetime2(3) NULL,
    CONSTRAINT FK_password_reset_tokens_user FOREIGN KEY(user_id) REFERENCES dbo.app_users(id),
    CONSTRAINT UQ_password_reset_tokens_hash UNIQUE(token_hash)
);
GO
CREATE INDEX IX_password_reset_tokens_user_expiry ON dbo.password_reset_tokens(user_id,expires_at);
GO