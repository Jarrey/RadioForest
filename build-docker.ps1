param(
    [string]$Tag = "radioforest:latest"
)

Write-Host "Building Docker image with tag '$Tag'..."

docker build -t $Tag .
if ($LASTEXITCODE -ne 0) {
    Write-Error "Docker build failed with exit code $LASTEXITCODE"
    exit $LASTEXITCODE
}

Write-Host "Docker image built successfully: $Tag"
