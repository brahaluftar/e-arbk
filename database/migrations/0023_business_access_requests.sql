SET XACT_ABORT ON;
GO

CREATE TABLE dbo.business_access_requests (
    id bigint IDENTITY(1,1) NOT NULL CONSTRAINT PK_business_access_requests PRIMARY KEY,
    user_id bigint NOT NULL,
    business_id int NOT NULL,
    status_code varchar(20) NOT NULL CONSTRAINT DF_business_access_requests_status DEFAULT 'PENDING',
    requested_at datetime2(3) NOT NULL CONSTRAINT DF_business_access_requests_requested DEFAULT SYSUTCDATETIME(),
    reviewed_at datetime2(3) NULL,
    reviewed_by_user_id bigint NULL,
    review_note nvarchar(300) NULL,
    CONSTRAINT FK_business_access_requests_user FOREIGN KEY(user_id) REFERENCES dbo.app_users(id),
    CONSTRAINT FK_business_access_requests_business FOREIGN KEY(business_id) REFERENCES dbo.ARBK_LIST(REGULATION_ID),
    CONSTRAINT FK_business_access_requests_reviewer FOREIGN KEY(reviewed_by_user_id) REFERENCES dbo.app_users(id),
    CONSTRAINT CK_business_access_requests_status CHECK(status_code IN ('PENDING','APPROVED','REJECTED')),
    CONSTRAINT CK_business_access_requests_review CHECK(
        (status_code='PENDING' AND reviewed_at IS NULL AND reviewed_by_user_id IS NULL)
        OR (status_code IN ('APPROVED','REJECTED') AND reviewed_at IS NOT NULL AND reviewed_by_user_id IS NOT NULL)
    )
);
CREATE UNIQUE INDEX UX_business_access_requests_pending
    ON dbo.business_access_requests(user_id,business_id) WHERE status_code='PENDING';
CREATE INDEX IX_business_access_requests_review
    ON dbo.business_access_requests(status_code,requested_at) INCLUDE(user_id,business_id);
GO
