param(
  [string]$Node1Path = "C:\kynbiot-v2.3",
  [string]$Node2Host = "100.67.122.99",
  [string]$Node2User = "Administrator",
  [string]$Node2Path = "C:\kynbiot-v2.3"
)

$ErrorActionPreference = "Stop"

Write-Host "[KYNBIOT] Generating secrets..."
$jwt = -join ((33..126) | Get-Random -Count 64 | ForEach-Object {[char]$_})
$dbPass = -join ((33..126) | Get-Random -Count 32 | ForEach-Object {[char]$_})

$envContent = @"
JWT_SECRET=$jwt
POSTGRES_PASSWORD=$dbPass
GROQ_API_KEY=
CORS_ORIGIN=*
"@

Set-Content -Path (Join-Path $Node1Path ".env") -Value $envContent -Encoding UTF8

Write-Host "[KYNBIOT] Starting Node 1 stack..."
Push-Location $Node1Path
docker compose -f docker-compose.gateway.yml --env-file .env up -d --build
Pop-Location

Write-Host "[KYNBIOT] Configuring Node 2 remotely..."
$session = New-PSSession -HostName $Node2Host -UserName $Node2User
Invoke-Command -Session $session -ScriptBlock {
  param($Path)
  Set-Location $Path
  docker compose -f docker-compose.compute.yml up -d --build
} -ArgumentList $Node2Path
Remove-PSSession $session

Write-Host "[KYNBIOT] Running health checks..."
Start-Sleep -Seconds 15
try {
  $health = Invoke-RestMethod -Uri "http://100.67.122.46:3100/api/health" -Method Get
  $health | ConvertTo-Json -Depth 5 | Write-Host
  Write-Host "[KYNBIOT] Installation complete."
}
catch {
  Write-Warning "Health check failed: $($_.Exception.Message)"
  exit 1
}
