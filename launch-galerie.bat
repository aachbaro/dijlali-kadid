@echo off
REM Galerie Djilali Kadid - lanceur de la copie locale
REM Creer un raccourci vers ce fichier sur le bureau pour lancer le site local.

setlocal EnableDelayedExpansion
cd /d "%~dp0local"

set "COMPOSE=docker-compose.local.yml"
set "URL=http://localhost:8090"
set "DOCKER_EXE=C:\Program Files\Docker\Docker\Docker Desktop.exe"

if not exist "%COMPOSE%" (
    echo Impossible de trouver %COMPOSE% dans %CD%
    pause
    exit /b 1
)

echo Verification de Docker...
docker info >nul 2>nul
if not errorlevel 1 goto dockerok

echo Docker Desktop n'est pas demarre, lancement...
if not exist "%DOCKER_EXE%" (
    echo Docker Desktop introuvable. Installe-le ou lance-le a la main.
    pause
    exit /b 1
)
start "" "%DOCKER_EXE%"
echo Attente du moteur Docker, cela peut prendre une minute...
set /a TRIES=0

:waitdocker
ping -n 6 127.0.0.1 >nul
docker info >nul 2>nul
if not errorlevel 1 goto dockerok
set /a TRIES+=1
if !TRIES! GEQ 36 goto dockerfail
goto waitdocker

:dockerfail
echo.
echo Docker n'a pas demarre apres 3 minutes.
echo Lance Docker Desktop a la main puis relance ce script.
pause
exit /b 1

:dockerok
echo Docker est actif.

echo Demarrage du site local...
docker compose -f "%COMPOSE%" up -d
if errorlevel 1 (
    echo Echec du demarrage des conteneurs.
    pause
    exit /b 1
)

echo Attente de la reponse du site...
set /a WAIT=0

:waitsite
ping -n 4 127.0.0.1 >nul
curl -s -o nul -m 5 "%URL%"
if not errorlevel 1 goto siteok
set /a WAIT+=1
if !WAIT! GEQ 40 goto sitefail
goto waitsite

:sitefail
echo.
echo Le site ne repond pas apres 2 minutes.
echo Journaux : docker compose -f local\%COMPOSE% logs --tail 50
pause
exit /b 1

:siteok
echo.
echo   =====================================================
echo    Site local demarre
echo.
echo    Site           : %URL%
echo    Administration : %URL%/wp-admin
echo.
echo    adam    / demo   (administrateur)
echo    djilali / demo   (client galerie)
echo   =====================================================
echo.
echo   Pour arreter : docker compose -f local\%COMPOSE% down
echo.

start "" "%URL%"
ping -n 11 127.0.0.1 >nul
