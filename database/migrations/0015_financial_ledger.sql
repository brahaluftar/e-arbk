SET XACT_ABORT ON;
GO
CREATE TABLE dbo.business_invoices (
    id bigint IDENTITY(1,1) NOT NULL CONSTRAINT PK_business_invoices PRIMARY KEY,
    business_id int NOT NULL,
    fiscal_year smallint NOT NULL,
    invoice_number nvarchar(80) NOT NULL,
    amount decimal(18,2) NOT NULL,
    issued_on date NOT NULL,
    due_on date NULL,
    notes nvarchar(500) NULL,
    created_by_user_id bigint NOT NULL,
    created_at datetime2(3) NOT NULL CONSTRAINT DF_business_invoices_created DEFAULT(SYSUTCDATETIME()),
    CONSTRAINT FK_business_invoices_business FOREIGN KEY(business_id) REFERENCES dbo.ARBK_LIST(REGULATION_ID),
    CONSTRAINT FK_business_invoices_user FOREIGN KEY(created_by_user_id) REFERENCES dbo.app_users(id),
    CONSTRAINT UQ_business_invoices_business_year UNIQUE(business_id,fiscal_year),
    CONSTRAINT UQ_business_invoices_number UNIQUE(invoice_number),
    CONSTRAINT CK_business_invoices_year CHECK(fiscal_year BETWEEN 2000 AND 2100),
    CONSTRAINT CK_business_invoices_amount CHECK(amount>0),
    CONSTRAINT CK_business_invoices_due CHECK(due_on IS NULL OR due_on>=issued_on)
);
GO
CREATE INDEX IX_business_invoices_year_date ON dbo.business_invoices(fiscal_year,issued_on) INCLUDE(business_id,amount);
GO
CREATE TABLE dbo.business_invoice_payments (
    id bigint IDENTITY(1,1) NOT NULL CONSTRAINT PK_business_invoice_payments PRIMARY KEY,
    invoice_id bigint NOT NULL,
    amount decimal(18,2) NOT NULL,
    paid_on date NOT NULL,
    reference nvarchar(100) NULL,
    notes nvarchar(500) NULL,
    recorded_by_user_id bigint NOT NULL,
    created_at datetime2(3) NOT NULL CONSTRAINT DF_business_invoice_payments_created DEFAULT(SYSUTCDATETIME()),
    CONSTRAINT FK_business_invoice_payments_invoice FOREIGN KEY(invoice_id) REFERENCES dbo.business_invoices(id),
    CONSTRAINT FK_business_invoice_payments_user FOREIGN KEY(recorded_by_user_id) REFERENCES dbo.app_users(id),
    CONSTRAINT CK_business_invoice_payments_amount CHECK(amount>0)
);
GO
CREATE INDEX IX_business_invoice_payments_date ON dbo.business_invoice_payments(paid_on,invoice_id) INCLUDE(amount);
GO