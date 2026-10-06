SET XACT_ABORT ON;
GO
ALTER TABLE dbo.app_users
ADD auth_version int NOT NULL CONSTRAINT DF_app_users_auth_version DEFAULT(0);
GO
ALTER TABLE dbo.app_users
ADD CONSTRAINT CK_app_users_auth_version CHECK(auth_version>=0);
GO