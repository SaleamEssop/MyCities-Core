#Requires -Version 5.1
# MyCities-Core — Deploy: build + up. Same as BuildDocker_Core; alias for clarity.

& (Join-Path $PSScriptRoot "BuildDocker_Core.ps1") @args
