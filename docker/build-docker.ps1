param(
    [string]$Tag = "radioforest:latest"
)

$scriptDir = Split-Path -Parent $MyInvocation.MyCommand.Path
$repoRoot = Resolve-Path "$scriptDir\.."
$indexDev = Join-Path $repoRoot "index.dev.php"
$indexPhp = Join-Path $repoRoot "index.php"

Write-Host "Checking source build status..."

if (-Not (Test-Path $indexPhp) -or (Test-Path $indexDev -and (Get-Item $indexDev).LastWriteTime -gt (Get-Item $indexPhp).LastWriteTime)) {
    Write-Host "Building index.php from index.dev.php..."
    Push-Location $repoRoot
    node build.js
    if ($LASTEXITCODE -ne 0) {
        Write-Error "Failed to build index.php using node build.js"
        exit $LASTEXITCODE
    }
    Pop-Location
}

Write-Host "Building Docker image with tag '$Tag'..."

docker build -t $Tag -f "$scriptDir\Dockerfile" "$repoRoot"
if ($LASTEXITCODE -ne 0) {
    Write-Error "Docker build failed with exit code $LASTEXITCODE"
    exit $LASTEXITCODE
}

Write-Host "Docker image built successfully: $Tag"
