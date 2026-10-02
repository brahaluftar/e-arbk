SET XACT_ABORT ON;
GO

ALTER TABLE dbo.ARBK_LIST ADD
    EMRI_TREGTAR nvarchar(510) NULL,
    SEKTORI nvarchar(510) NULL,
    NR_PUNETOREVE int NULL,
    MADHESIA nvarchar(40) NULL,
    TOTAL_M int NULL,
    TOTAL_F int NULL,
    MUAJI varchar(20) NULL,
    DATA_SHUARJES date NULL,
    import_updated_at datetime2(3) NULL;
GO

ALTER TABLE dbo.ARBK_LIST ADD
    CONSTRAINT CK_ARBK_LIST_nr_punetoreve CHECK(NR_PUNETOREVE IS NULL OR NR_PUNETOREVE>=0),
    CONSTRAINT CK_ARBK_LIST_total_m CHECK(TOTAL_M IS NULL OR TOTAL_M>=0),
    CONSTRAINT CK_ARBK_LIST_total_f CHECK(TOTAL_F IS NULL OR TOTAL_F>=0);
GO

DECLARE @nextRegulationId bigint=(SELECT ISNULL(MAX(CONVERT(bigint,REGULATION_ID)),0)+1 FROM dbo.ARBK_LIST);
IF OBJECT_ID('dbo.arbk_regulation_id_seq','SO') IS NULL BEGIN
    DECLARE @sequenceSql nvarchar(500)=N'CREATE SEQUENCE dbo.arbk_regulation_id_seq AS bigint START WITH '+CONVERT(nvarchar(30),@nextRegulationId)+N' INCREMENT BY 1;';
    EXEC sys.sp_executesql @sequenceSql;
END;
GO
ALTER TABLE dbo.ARBK_LIST ADD CONSTRAINT DF_ARBK_LIST_REGULATION_ID_SEQ DEFAULT(NEXT VALUE FOR dbo.arbk_regulation_id_seq) FOR REGULATION_ID;
GO

CREATE TABLE dbo.business_import_runs (
    id bigint IDENTITY(1,1) NOT NULL CONSTRAINT PK_business_import_runs PRIMARY KEY,
    source_type varchar(20) NOT NULL,
    original_filename nvarchar(255) NOT NULL,
    storage_path nvarchar(500) NOT NULL,
    file_sha256 char(64) NOT NULL,
    status varchar(20) NOT NULL CONSTRAINT DF_business_import_status DEFAULT('QUEUED'),
    total_rows int NOT NULL CONSTRAINT DF_business_import_total DEFAULT(0),
    valid_rows int NOT NULL CONSTRAINT DF_business_import_valid DEFAULT(0),
    inserted_rows int NOT NULL CONSTRAINT DF_business_import_inserted DEFAULT(0),
    updated_rows int NOT NULL CONSTRAINT DF_business_import_updated DEFAULT(0),
    skipped_rows int NOT NULL CONSTRAINT DF_business_import_skipped DEFAULT(0),
    error_rows int NOT NULL CONSTRAINT DF_business_import_errors DEFAULT(0),
    error_message nvarchar(2000) NULL,
    uploaded_by_user_id bigint NOT NULL,
    created_at datetime2(3) NOT NULL CONSTRAINT DF_business_import_created DEFAULT(SYSUTCDATETIME()),
    started_at datetime2(3) NULL,
    completed_at datetime2(3) NULL,
    CONSTRAINT FK_business_import_user FOREIGN KEY(uploaded_by_user_id) REFERENCES dbo.app_users(id),
    CONSTRAINT CK_business_import_type CHECK(source_type IN('ALL','WOMEN','CLOSED')),
    CONSTRAINT CK_business_import_status CHECK(status IN('QUEUED','PROCESSING','COMPLETED','FAILED')),
    CONSTRAINT UQ_business_import_hash_type UNIQUE(file_sha256,source_type)
);
GO
CREATE INDEX IX_business_import_queue ON dbo.business_import_runs(status,created_at,id);
GO

CREATE TABLE dbo.business_import_staging (
    import_run_id bigint NOT NULL,
    source_row_number int NOT NULL,
    business_number varchar(20) NULL,
    legal_name nvarchar(510) NULL,
    trade_name nvarchar(510) NULL,
    business_type nvarchar(100) NULL,
    nace_raw nvarchar(700) NULL,
    nace_code varchar(20) NULL,
    nace_description nvarchar(510) NULL,
    sector_raw nvarchar(700) NULL,
    sector_clean nvarchar(510) NULL,
    employee_count int NULL,
    business_size nvarchar(40) NULL,
    total_m int NULL,
    total_f int NULL,
    city nvarchar(200) NULL,
    business_status nvarchar(40) NULL,
    business_year varchar(4) NULL,
    business_month varchar(20) NULL,
    closed_date date NULL,
    validation_error nvarchar(500) NULL,
    CONSTRAINT PK_business_import_staging PRIMARY KEY(import_run_id,source_row_number),
    CONSTRAINT FK_business_import_staging_run FOREIGN KEY(import_run_id) REFERENCES dbo.business_import_runs(id)
);
GO
CREATE INDEX IX_business_import_staging_business ON dbo.business_import_staging(import_run_id,business_number) INCLUDE(validation_error,nace_code);
GO
