param(
    [int] $LaravelPort = 8000,
    [int] $ShareDelay = 5
)

$ErrorActionPreference = "Stop"

$root = Split-Path -Parent $PSScriptRoot
Set-Location $root

$processes = @()

function Resolve-CommandPath {
    param(
        [string[]] $Candidates
    )

    foreach ($candidate in $Candidates) {
        $command = Get-Command $candidate -ErrorAction SilentlyContinue

        if ($command) {
            return $command.Source
        }
    }

    return $null
}

function Start-ProjectProcess {
    param(
        [string] $Name,
        [string] $FilePath,
        [string] $Arguments,
        [string] $WorkingDirectory
    )

    Write-Host "Starting $Name..."
    $process = Start-Process `
        -FilePath $FilePath `
        -ArgumentList $Arguments `
        -WorkingDirectory $WorkingDirectory `
        -NoNewWindow `
        -PassThru

    $script:processes += $process
}

try {
    $phpPath = Resolve-CommandPath @("php.exe", "php")
    if (-not $phpPath) {
        throw "PHP executable was not found in PATH."
    }

    Start-ProjectProcess `
        -Name "Laravel" `
        -FilePath $phpPath `
        -Arguments "artisan serve --host=127.0.0.1 --port=$LaravelPort" `
        -WorkingDirectory $root

    if (Test-Path (Join-Path $root "node_modules")) {
        $npmPath = Resolve-CommandPath @("npm.cmd", "npm.exe", "npm")

        if ($npmPath) {
            Start-ProjectProcess `
                -Name "Vite" `
                -FilePath $npmPath `
                -Arguments "run dev" `
                -WorkingDirectory $root
        } else {
            Write-Host "Vite skipped: npm was not found in PATH."
        }
    }

    $workerDir = Join-Path $root "userbot_worker"
    $workerPython = Join-Path $workerDir ".venv\Scripts\python.exe"

    if (Test-Path $workerPython) {
        Start-ProjectProcess `
            -Name "Pyrogram share worker" `
            -FilePath $workerPython `
            -Arguments "worker.py share-pending --limit 5 --delay $ShareDelay" `
            -WorkingDirectory $workerDir
    } else {
        Write-Host "Pyrogram worker skipped: userbot_worker\.venv not found yet."
    }

    Write-Host ""
    Write-Host "Project is running."
    Write-Host "Dashboard: http://127.0.0.1:$LaravelPort"
    Write-Host "Press Ctrl+C to stop all started processes."

    while ($true) {
        Start-Sleep -Seconds 2
    }
} finally {
    foreach ($process in $processes) {
        if ($process -and -not $process.HasExited) {
            Write-Host "Stopping process $($process.Id)..."
            Stop-Process -Id $process.Id -Force
        }
    }
}
