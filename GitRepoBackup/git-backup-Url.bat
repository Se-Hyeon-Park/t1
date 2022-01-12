
@REM get time
for /f %%i in ('powershell -c "get-date -format yyyyMMddHHmmss"') do set DATETIME=%%i
SET timestamp=%DATETIME%
SET dirPath=backup_%timestamp%

@REM get the address of origin repository
ECHO ex) https://github.com/Se-Hyeon-Park/move-test.git
SET /p repoAddr=Please input your repository address : 

@REM clone the origin repository
git clone %repoAddr% %dirPath%
cd %dirPath%

@REM make a backup branch
git branch %dirPath%
git checkout %dirPath%

@REM push backup repository
ECHO ex) https://github.com/Se-Hyeon-Park/t1.git
SET /p backupAddr=Please input your repository address : 
git remote set-url --push origin %backupAddr%
git push origin %dirPath%

pause