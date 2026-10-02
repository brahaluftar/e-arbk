<?php
declare(strict_types=1);
namespace App\Support;

final class ProductionValidator
{
    /** @return list<string> */
    public static function errors(Config $config): array
    {
        if ($config->string('APP_ENV') !== 'production') return [];
        $errors=[];
        if (!str_starts_with($config->string('APP_URL'),'https://')) $errors[]='APP_URL must use https://.';
        if (!$config->bool('FORCE_HTTPS')) $errors[]='FORCE_HTTPS must be true.';
        if (strlen($config->string('APP_KEY'))<32 || str_starts_with($config->string('APP_KEY'),'REPLACE_')) $errors[]='APP_KEY must be a unique secret of at least 32 characters.';
        if ($config->bool('DB_TRUSTED_CONNECTION')) $errors[]='DB_TRUSTED_CONNECTION must be false on CloudClusters.';
        foreach(['DB_HOST','DB_NAME','DB_USER','DB_PASSWORD'] as $key) if ($config->string($key)==='' || str_starts_with($config->string($key),'REPLACE_')) $errors[]="$key is required.";
        if (!$config->bool('DB_ENCRYPT')) $errors[]='DB_ENCRYPT must be true.';
        if ($config->bool('APP_DEBUG')) $errors[]='APP_DEBUG must be false.';
        if ($config->string('BACKUP_MODE')==='sqlserver' && $config->string('DB_BACKUP_PATH')==='') $errors[]='DB_BACKUP_PATH is required for sqlserver backup mode.';
        if (!in_array($config->string('BACKUP_MODE'),['managed','sqlserver'],true)) $errors[]='BACKUP_MODE must be managed or sqlserver.';
        return $errors;
    }

    public static function enforce(Config $config): void
    {
        $errors=self::errors($config);
        if ($errors!==[]) throw new \RuntimeException('Invalid production configuration: '.implode(' ',$errors));
    }
}
