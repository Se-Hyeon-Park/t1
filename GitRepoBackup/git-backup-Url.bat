@ECHO OFF

@REM get time
for /f %%i in ('powershell -c "get-date -format yyyyMMddHHmmss"') do set timestamp=%%i
SET dirPath=backup_%timestamp%

@REM get the address of origin repository 
ECHO ex) https://github.com/user/originalRepo.git
SET /p repoAddr=Please input your original repository address (from): 

@REM get the address of backup repository
ECHO ex) https://github.com/user/backupRepo.git
SET /p backupAddr=Please input your backup repository address (to): 


@REM clone the origin repository
git clone %repoAddr% %dirPath%
cd %dirPath%

@REM make a backup branch
git branch %dirPath%
git checkout %dirPath%

@REM push backup repository
git remote set-url --push origin %backupAddr%
git push origin %dirPath%

pause