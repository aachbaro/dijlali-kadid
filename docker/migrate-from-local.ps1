# migrate-from-local.ps1
# Exporte le site depuis Local by Flywheel et envoie tout sur le home server
#
# Usage : .\migrate-from-local.ps1

$phpBin  = "C:\Users\adama\AppData\Roaming\Local\lightning-services\php-8.2.29+0\bin\win64\php.exe"
$phpIni  = "C:\Users\adama\AppData\Roaming\Local\run\_w98268l-\conf\php-cli\php.ini"
$wpCli   = "C:\Users\adama\AppData\Local\Programs\Local\resources\extraResources\bin\wp-cli\wp-cli.phar"
$wpPath  = "C:\Users\adama\Local Sites\art-sell\app\public"
$server  = "pascuans@192.168.1.14"
$srvPath = "/home/pascuans/roadToDev/pascuans/site-wordpress-woocommerce"
$sshKey  = "$env:USERPROFILE\.ssh\id_ed25519"
$export  = "$PSScriptRoot\export"

# ── 1. Dossier d'export ────────────────────────────────────
if (Test-Path $export) { Remove-Item $export -Recurse -Force }
New-Item -ItemType Directory $export | Out-Null

# ── 2. Export base de données ──────────────────────────────
Write-Host "`n[1/4] Export base de données..." -ForegroundColor Cyan
& $phpBin -c $phpIni $wpCli db export "$export\dump.sql" --path=$wpPath
if ($LASTEXITCODE -ne 0) { Write-Error "Echec export DB"; exit 1 }

# Remplace l'URL locale par le domaine de prod
(Get-Content "$export\dump.sql" -Raw) `
    -replace 'http://art-sell\.local', 'https://galerie-djilali-kadid.com' |
    Set-Content "$export\dump.sql" -Encoding utf8
Write-Host "  OK : dump.sql — URL remplacée par https://galerie-djilali-kadid.com" -ForegroundColor Green

# ── 3. Archive wp-content ──────────────────────────────────
Write-Host "`n[2/4] Archive wp-content (thèmes, plugins, uploads)..." -ForegroundColor Cyan
$wpContent = "$wpPath\wp-content"
if (-not (Test-Path $wpContent)) { Write-Error "wp-content introuvable : $wpContent"; exit 1 }

# Utilise tar si disponible (Windows 10+), sinon Compress-Archive
$tarAvailable = Get-Command tar -ErrorAction SilentlyContinue
if ($tarAvailable) {
    tar -czf "$export\wp-content.tar.gz" -C $wpPath wp-content
} else {
    Compress-Archive -Path $wpContent -DestinationPath "$export\wp-content.zip" -Force
    Write-Host "  (tar non disponible, archive zip créée)" -ForegroundColor Yellow
}
Write-Host "  OK : archive wp-content créée" -ForegroundColor Green

# ── 4. Envoie sur le home server ───────────────────────────
Write-Host "`n[3/4] Envoi sur le home server ($server)..." -ForegroundColor Cyan
ssh -i $sshKey $server "mkdir -p $srvPath/docker/export"
scp -i $sshKey "$export\dump.sql" "${server}:${srvPath}/docker/export/dump.sql"
if (Test-Path "$export\wp-content.tar.gz") {
    scp -i $sshKey "$export\wp-content.tar.gz" "${server}:${srvPath}/docker/export/wp-content.tar.gz"
} else {
    scp -i $sshKey "$export\wp-content.zip" "${server}:${srvPath}/docker/export/wp-content.zip"
}
Write-Host "  OK : fichiers envoyés" -ForegroundColor Green

# ── 5. Instructions finales ────────────────────────────────
Write-Host "`n[4/4] Sur le home server, lance :" -ForegroundColor Cyan
Write-Host "  ssh -i $sshKey $server" -ForegroundColor White
Write-Host "  cd $srvPath" -ForegroundColor White
Write-Host "  bash docker/deploy.sh" -ForegroundColor White
Write-Host "`nMigration prête !" -ForegroundColor Green
