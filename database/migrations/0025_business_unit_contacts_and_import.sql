SET XACT_ABORT ON;
GO
ALTER TABLE dbo.business_units ADD email nvarchar(254) NULL,manager_name nvarchar(255) NULL;
GO
ALTER TABLE dbo.business_import_runs DROP CONSTRAINT CK_business_import_type;
ALTER TABLE dbo.business_import_runs ADD CONSTRAINT CK_business_import_type CHECK(source_type IN('ALL','WOMEN','CLOSED','CONTACTS','UNITS'));
GO
ALTER TABLE dbo.business_import_staging ADD unit_name nvarchar(255) NULL,unit_address nvarchar(500) NULL,unit_email nvarchar(254) NULL,unit_manager nvarchar(255) NULL,unit_nace_code varchar(20) NULL,unit_nace_description nvarchar(510) NULL,unit_annual_tariff decimal(18,2) NULL,unit_auto_invoice bit NULL;
GO
CREATE UNIQUE INDEX UX_business_units_identity ON dbo.business_units(business_id,unit_name,address);
GO
