SET XACT_ABORT ON;
GO

ALTER TABLE dbo.ARBK_LIST ADD
    MENAXHERI nvarchar(255) NULL,
    NUMRI_TELEFONIT nvarchar(50) NULL;
GO

ALTER TABLE dbo.business_import_runs DROP CONSTRAINT CK_business_import_type;
ALTER TABLE dbo.business_import_runs ADD CONSTRAINT CK_business_import_type
    CHECK(source_type IN('ALL','WOMEN','CLOSED','CONTACTS'));
GO

ALTER TABLE dbo.business_import_staging ADD
    manager_name nvarchar(255) NULL,
    phone_number nvarchar(50) NULL;
GO

CREATE TABLE dbo.business_units (
    id bigint IDENTITY(1,1) NOT NULL CONSTRAINT PK_business_units PRIMARY KEY,
    business_id int NOT NULL,
    unit_name nvarchar(255) NOT NULL,
    address nvarchar(500) NOT NULL,
    phone_number nvarchar(50) NULL,
    nace_code varchar(20) NOT NULL,
    nace_description nvarchar(510) NULL,
    annual_tariff decimal(18,2) NULL,
    auto_invoice bit NOT NULL CONSTRAINT DF_business_units_auto_invoice DEFAULT(0),
    is_active bit NOT NULL CONSTRAINT DF_business_units_active DEFAULT(1),
    created_by_user_id bigint NOT NULL,
    created_at datetime2(3) NOT NULL CONSTRAINT DF_business_units_created DEFAULT(SYSUTCDATETIME()),
    updated_at datetime2(3) NOT NULL CONSTRAINT DF_business_units_updated DEFAULT(SYSUTCDATETIME()),
    CONSTRAINT FK_business_units_business FOREIGN KEY(business_id) REFERENCES dbo.ARBK_LIST(REGULATION_ID),
    CONSTRAINT FK_business_units_user FOREIGN KEY(created_by_user_id) REFERENCES dbo.app_users(id),
    CONSTRAINT CK_business_units_tariff CHECK(annual_tariff IS NULL OR annual_tariff>0)
);
CREATE INDEX IX_business_units_business ON dbo.business_units(business_id,is_active,id);
GO

ALTER TABLE dbo.business_invoices DROP CONSTRAINT UQ_business_invoices_business_year;
ALTER TABLE dbo.business_invoices ADD business_unit_id bigint NULL;
ALTER TABLE dbo.business_invoices ADD CONSTRAINT FK_business_invoices_unit
    FOREIGN KEY(business_unit_id) REFERENCES dbo.business_units(id);
GO
CREATE UNIQUE INDEX UX_business_invoices_main_year ON dbo.business_invoices(business_id,fiscal_year)
    WHERE business_unit_id IS NULL;
CREATE UNIQUE INDEX UX_business_invoices_unit_year ON dbo.business_invoices(business_unit_id,fiscal_year)
    WHERE business_unit_id IS NOT NULL;
GO
