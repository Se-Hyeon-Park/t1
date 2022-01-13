@ECHO OFF

@REM parameter
SET backupRepoName=Backup Repository Url=
SET targetRepoName=Target Repository Url=

@REM get time
FOR /f %%i IN ('powershell -c "get-date -format yyyyMMddHHmmss"') DO SET timestamp=%%i
SET dirPath=backup_%timestamp%

@REM get repo address from txt file
IF NOT EXIST info.txt (
    SET errFileExist=Please check 'info.txt' exist
    goto _fileError
)
FOR /f "tokens=1,2* delims={}" %%i IN (info.txt) DO IF "%%i"=="%backupRepoName%" ( SET backupRepo=%%j ) ELSE ( IF "%%i"=="%targetRepoName%" ( SET targetRepo=%%j ))

@REM error check
:_fileError
IF "%backupRepo%"=="" (
    SET flg=T
    SET errBackupUrl=Please check Backup Repository Url
)
IF "%targetRepo%"=="" (
    SET flg=T
    SET errTargetUrl=Please check Target Repository Url
)
IF "%flg%"=="T" goto :_end 

@REM clone the origin repository
git clone %backupRepo% %dirPath%

@REM make a backup branch
cd %dirPath%
git branch %dirPath%
git checkout %dirPath%

@REM push backup repository
git remote set-url --push origin %targetRepo%
git push origin %dirPath%

:_end
ECHO %errFileExist%
ECHO %errBackupUrl%
ECHO %errTargetUrl%
pause