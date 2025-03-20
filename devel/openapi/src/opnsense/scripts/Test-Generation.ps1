#! /usr/bin/pwsh

[CmdletBinding()]
param
(
    [string]$SpecFile,
    [string]$OutPath,
    [switch]$Clear
)

# npm install -g autorest

if (-not ($SpecFile -and $OutPath))
{
    $RepoBase = $PSScriptRoot
    while (-not (Test-Path ("$RepoBase/.git")))
    {
        $RepoBase = Split-Path $RepoBase
    }
}

if (-not $SpecFile)
{
    $SpecFile = "$RepoBase/devel/openapi/src/opnsense/scripts/openapi.yml" | Resolve-Path
}

openapi-spec-validator $SpecFile
if (-not $?)
{
    throw "Failed validation"
}

if (-not $OutPath)
{
    $OutPath = "$RepoBase/../powershell/"
    $null = New-Item $OutPath -Force -ItemType Directory -ErrorAction Stop
    $OutPath = $OutPath | Resolve-Path
}

$v = if ($VerbosePreference) {"--verbose"}
$d = if ($DebugPreference) {"--verbose"}
$clear = if ($Clear) {"--clear-output-folder"}
autorest --powershell --input-file:$SpecFile --output-folder:$OutPath $v $d
