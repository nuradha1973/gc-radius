@echo off
REM ============================================================
REM  Backup gc-radius ke GitHub
REM  Jalankan: klik 2x file ini, atau VS Code > Run Task > "Backup ke GitHub"
REM  Mode otomatis (tanpa pause): backup-github.bat --auto
REM ============================================================
setlocal
set "PATH=%LOCALAPPDATA%\Programs\PortableGit\cmd;%PATH%"
cd /d "%~dp0"

echo ============================================
echo   Backup gc-radius  -^>  GitHub
echo   Folder: %CD%
echo ============================================

where git >nul 2>&1
if errorlevel 1 (
    echo [X] Git tidak ditemukan.
    echo     Install PortableGit ke %%LOCALAPPDATA%%\Programs\PortableGit
    goto :end
)

git rev-parse --is-inside-work-tree >nul 2>&1
if errorlevel 1 (
    echo [X] Folder ini bukan git repository.
    goto :end
)

echo.
echo [1/3] Menambahkan perubahan...
git add -A

git diff --cached --quiet
if not errorlevel 1 (
    echo       Tidak ada perubahan baru.
) else (
    echo       Membuat commit...
    git -c user.name=nuradha1973 -c user.email=nuradha1973@users.noreply.github.com commit -q -m "Backup otomatis %date% %time%" 
    if errorlevel 1 (
        echo [X] Commit gagal.
        goto :end
    )
    git --no-pager log --oneline -1
)

echo.
echo [2/3] Mengirim ke origin/main...
git push origin main
if errorlevel 1 (
    echo.
    echo [X] PUSH GAGAL. Cek pesan error di atas.
    goto :end
)

echo.
echo [3/3] Selesai. Status repo:
git status -sb
git --no-pager log --oneline -3
echo.
echo [OK] Backup ke GitHub BERHASIL.
echo      https://github.com/nuradha1973/gc-radius/commits/main

:end
if /i not "%~1"=="--auto" (
    echo.
    pause
)
endlocal
