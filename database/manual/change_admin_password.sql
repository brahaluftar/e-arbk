-- Generate @PasswordHash with PHP password_hash($password, PASSWORD_DEFAULT).
-- Do not put the plaintext password in this file or in a SQL command.
-- Paste the generated hash below, then execute this script against the ARBK database.
SET NOCOUNT ON;
SET XACT_ABORT ON;

DECLARE @AdminEmail nvarchar(254) = N'REPLACE_WITH_ADMIN_EMAIL';
DECLARE @PasswordHash nvarchar(255) = N'PASTE_PHP_PASSWORD_HASH_HERE';

IF @AdminEmail = N'REPLACE_WITH_ADMIN_EMAIL' OR LTRIM(RTRIM(@AdminEmail)) = N''
    THROW 51000, 'Set @AdminEmail to the administrator account email.', 1;
IF @PasswordHash = N'PASTE_PHP_PASSWORD_HASH_HERE' OR LEN(@PasswordHash) < 40
    THROW 51001, 'Set @PasswordHash to the output of PHP password_hash().', 1;

BEGIN TRY
    BEGIN TRANSACTION;

    DECLARE @AdminId bigint;
    SELECT @AdminId = id
    FROM dbo.app_users WITH (UPDLOCK, HOLDLOCK)
    WHERE normalized_email = UPPER(LTRIM(RTRIM(@AdminEmail)))
      AND role_code = 'ADMIN';

    IF @AdminId IS NULL
        THROW 51002, 'No administrator account matches @AdminEmail.', 1;

    UPDATE dbo.app_users
    SET password_hash = @PasswordHash
    WHERE id = @AdminId
      AND role_code = 'ADMIN';

    IF @@ROWCOUNT <> 1
        THROW 51003, 'Expected to update exactly one administrator account.', 1;

    COMMIT TRANSACTION;

    SELECT id, email, role_code, is_active, N'Password hash updated.' AS result
    FROM dbo.app_users
    WHERE id = @AdminId;
END TRY
BEGIN CATCH
    IF XACT_STATE() <> 0 ROLLBACK TRANSACTION;
    THROW;
END CATCH;