param(
    [Parameter(Mandatory=$true)][ValidatePattern('^[0-9][A-Za-z0-9._-]+$')][string]$Version
)
$ErrorActionPreference = 'Stop'
$rootPath = Split-Path -Parent $PSScriptRoot
$bundleName = "e-arbk-code-$Version"
$bundlePath = Join-Path $rootPath "dist\$bundleName"
$archivePath = Join-Path $rootPath "dist\$bundleName.zip"
if ($Version.Contains('..') -or (Test-Path -LiteralPath $bundlePath) -or (Test-Path -LiteralPath $archivePath)) { throw 'Use a new release identifier.' }
$appPath = Join-Path $bundlePath 'app'
New-Item -ItemType Directory -Path $appPath -Force | Out-Null
foreach ($directory in @('bin','config','public','src','templates')) {
    Copy-Item -LiteralPath (Join-Path $rootPath $directory) -Destination $appPath -Recurse
}
New-Item -ItemType Directory -Path (Join-Path $appPath 'database') -Force | Out-Null
Copy-Item -LiteralPath (Join-Path $rootPath 'database\migrations') -Destination (Join-Path $appPath 'database') -Recurse
foreach ($file in @('bootstrap.php','index.php','composer.json','composer.lock','README.md','.htaccess','.env.production.example')) {
    Copy-Item -LiteralPath (Join-Path $rootPath $file) -Destination $appPath
}
New-Item -ItemType Directory -Path (Join-Path $appPath 'var\imports') -Force | Out-Null
[System.IO.File]::WriteAllText((Join-Path $appPath 'var\imports\.gitkeep'), '')
New-Item -ItemType Directory -Path (Join-Path $appPath 'var\documents') -Force | Out-Null
[System.IO.File]::WriteAllText((Join-Path $appPath 'var\documents\.gitkeep'), '')
New-Item -ItemType Directory -Path (Join-Path $appPath 'docs') -Force | Out-Null
Copy-Item -LiteralPath (Join-Path $rootPath 'docs\production-deployment.md') -Destination (Join-Path $appPath 'docs')
Copy-Item -LiteralPath (Join-Path $rootPath 'docs\deployment-bundle.md') -Destination (Join-Path $appPath 'docs')
Copy-Item -LiteralPath (Join-Path $rootPath 'docs\deployment-bundle.md') -Destination (Join-Path $bundlePath 'DEPLOYMENT.md')
[System.IO.File]::WriteAllText((Join-Path $bundlePath 'PACKAGE-TYPE'), "code-only`n", (New-Object System.Text.UTF8Encoding($false)))

# Do not package workstation connection defaults or any real environment file.
$defaultPath = Join-Path $appPath 'config\defaults.php'
$defaultsText = [System.IO.File]::ReadAllText($defaultPath)
$overrides = @{ APP_ENV='production'; APP_URL='https://arbk.kryeqyteti.net'; DB_HOST=''; DB_USER=''; DB_PASSWORD=''; DB_TRUSTED_CONNECTION='false'; DB_ENCRYPT='true'; DB_TRUST_SERVER_CERTIFICATE='false'; FORCE_HTTPS='true'; APP_KEY='' }
foreach ($entry in $overrides.GetEnumerator()) {
    $pattern = "(?m)^(\s*'" + [regex]::Escape($entry.Key) + "'\s*=>\s*)'[^']*'"
    $replacement = '${1}' + "'" + $entry.Value + "'"
    $defaultsText = [regex]::Replace($defaultsText,$pattern,$replacement)
}
[System.IO.File]::WriteAllText($defaultPath,$defaultsText,(New-Object System.Text.UTF8Encoding($false)))
& composer install --working-dir $appPath --no-dev --prefer-dist --no-interaction --no-progress --classmap-authoritative --ignore-platform-req=ext-gd
if ($LASTEXITCODE -ne 0) { throw 'Production dependency installation failed.' }
foreach ($file in @('deploy.sh','100-arbk.conf','manage-apache-vhosts.awk')) {
    $text = [System.IO.File]::ReadAllText((Join-Path $PSScriptRoot $file)).Replace("`r`n","`n")
    [System.IO.File]::WriteAllText((Join-Path $bundlePath $file),$text,(New-Object System.Text.UTF8Encoding($false)))
}
[System.IO.File]::WriteAllText((Join-Path $bundlePath 'RELEASE'),"code-$Version`n",(New-Object System.Text.UTF8Encoding($false)))
$checksums = foreach ($file in Get-ChildItem -LiteralPath $bundlePath -Recurse -File | Sort-Object FullName) {
    $relative = $file.FullName.Substring($bundlePath.Length + 1).Replace('\','/')
    (Get-FileHash -LiteralPath $file.FullName -Algorithm SHA256).Hash.ToLowerInvariant() + '  ' + $relative
}
[System.IO.File]::WriteAllText((Join-Path $bundlePath 'SHA256SUMS'),($checksums -join "`n") + "`n",(New-Object System.Text.UTF8Encoding($false)))
Add-Type -AssemblyName System.IO.Compression
Add-Type -AssemblyName System.IO.Compression.FileSystem
$zip = [System.IO.Compression.ZipFile]::Open($archivePath,[System.IO.Compression.ZipArchiveMode]::Create)
try {
    foreach ($file in Get-ChildItem -LiteralPath $bundlePath -Recurse -File) {
        $relative = $file.FullName.Substring($bundlePath.Length + 1).Replace('\','/')
        if ($relative.Contains('\') -or $relative.StartsWith('/') -or $relative.Contains('../') -or $relative -match '(^|/)\.env$') { throw "Unsafe archive entry: $relative" }
        [System.IO.Compression.ZipFileExtensions]::CreateEntryFromFile($zip,$file.FullName,$relative,[System.IO.Compression.CompressionLevel]::Optimal) | Out-Null
    }
} finally { $zip.Dispose() }
$zip = [System.IO.Compression.ZipFile]::OpenRead($archivePath)
try {
    foreach ($entry in $zip.Entries) { if ($entry.FullName.Contains('\')) { throw 'Non-POSIX ZIP path detected.' } }
    Write-Output "Archive verified: $($zip.Entries.Count) entries with POSIX paths."
} finally { $zip.Dispose() }
$hash = (Get-FileHash -LiteralPath $archivePath -Algorithm SHA256).Hash.ToLowerInvariant()
[System.IO.File]::WriteAllText($archivePath + '.sha256',$hash + '  ' + [System.IO.Path]::GetFileName($archivePath) + "`n",(New-Object System.Text.UTF8Encoding($false)))
$launcher = [System.IO.File]::ReadAllText((Join-Path $PSScriptRoot 'deploy-arbk.sh')).Replace("`r`n","`n")
[System.IO.File]::WriteAllText((Join-Path $rootPath 'dist\deploy-arbk.sh'),$launcher,(New-Object System.Text.UTF8Encoding($false)))
Write-Output "Package: $archivePath"
Write-Output "SHA256: $hash"
