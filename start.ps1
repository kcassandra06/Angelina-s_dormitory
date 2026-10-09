$projectDirectory = Split-Path -Parent $MyInvocation.MyCommand.Path
Set-Location -LiteralPath $projectDirectory
Write-Host 'Start MySQL in XAMPP, then open http://localhost:8080 — press Ctrl+C to stop.'
$uploadDirectory = Join-Path $projectDirectory 'storage\upload-tmp'
New-Item -ItemType Directory -Force -Path $uploadDirectory | Out-Null
& 'C:\xampp\php\php.exe' -d "upload_tmp_dir=$uploadDirectory" -S localhost:8080 router.php
