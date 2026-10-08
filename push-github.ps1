[CmdletBinding()]
param(
    [Parameter(Position = 0)]
    [string]$Message = "Update $(Get-Date -Format 'yyyy-MM-dd HH:mm')"
)

$ErrorActionPreference = 'Stop'
$ProjectDirectory = $PSScriptRoot

if (-not (Get-Command git -ErrorAction SilentlyContinue)) {
    throw 'Git nuk u gjet. Instalo Git dhe sigurohu qe komanda git eshte ne PATH.'
}

Push-Location -LiteralPath $ProjectDirectory

try {
    if (-not (Test-Path -LiteralPath '.git')) {
        throw "Kjo drejtori nuk eshte Git repository: $ProjectDirectory"
    }

    $Branch = git branch --show-current
    if ($LASTEXITCODE -ne 0 -or [string]::IsNullOrWhiteSpace($Branch)) {
        throw 'Nuk u percaktua dot dega aktive e Git-it.'
    }

    git remote get-url origin *> $null
    if ($LASTEXITCODE -ne 0) {
        throw "Remote 'origin' nuk eshte konfiguruar."
    }

    Write-Host "Dega aktive: $Branch" -ForegroundColor Cyan
    git add --all
    if ($LASTEXITCODE -ne 0) { throw 'git add deshtoi.' }

    git diff --cached --quiet
    if ($LASTEXITCODE -eq 1) {
        git commit -m $Message
        if ($LASTEXITCODE -ne 0) { throw 'git commit deshtoi.' }
    }
    elseif ($LASTEXITCODE -ne 0) {
        throw 'Kontrolli i ndryshimeve deshtoi.'
    }
    else {
        Write-Host 'Nuk ka ndryshime te reja per commit.' -ForegroundColor Yellow
    }

    $RemoteBranch = git ls-remote --heads origin $Branch
    if ($LASTEXITCODE -ne 0) { throw 'Kontrolli i GitHub deshtoi.' }

    if (-not [string]::IsNullOrWhiteSpace($RemoteBranch)) {
        git pull --rebase origin $Branch
        if ($LASTEXITCODE -ne 0) { throw 'git pull --rebase deshtoi. Zgjidh konfliktet dhe provo perseri.' }
    }

    git push --set-upstream origin $Branch
    if ($LASTEXITCODE -ne 0) { throw 'git push deshtoi.' }

    Write-Host 'Push ne GitHub perfundoi me sukses.' -ForegroundColor Green
}
finally {
    Pop-Location
}
