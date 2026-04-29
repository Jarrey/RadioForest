param(
    [string]$Tag = "radioforest:latest"
)

Write-Host "Building Docker image with tag '$Tag'..."

$scriptDir = Split-Path -Parent $MyInvocation.MyCommand.Path
docker build -t $Tag -f "$scriptDir\Dockerfile" "$scriptDir\.."
if ($LASTEXITCODE -ne 0) {
    Write-Error "Docker build failed with exit code $LASTEXITCODE"
    exit $LASTEXITCODE
}

Write-Host "Docker image built successfully: $Tag"
