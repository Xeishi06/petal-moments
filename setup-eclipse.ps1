<#
.SYNOPSIS
    Converts this folder into an Eclipse PDT project.

.DESCRIPTION
    Copies the project into your Eclipse workspace and creates the Eclipse
    metadata (.project, .buildpath, .settings). It also registers the XAMPP
    PHP executable in the workspace (optional).

.EXAMPLE
    .\setup-eclipse.ps1

.EXAMPLE
    .\setup-eclipse.ps1 -Workspace "D:\eclipse-workspace"
#>
[CmdletBinding()]
param(
    [string]$Source = '',
    [string]$Workspace = (Join-Path $env:USERPROFILE 'eclipse-workspace'),
    [string]$PhpExe = 'C:\xampp\php\php.exe',
    [switch]$SkipPhpExecutable
)

$ErrorActionPreference = 'Stop'

if (-not $Source) {
    $Source = $PSScriptRoot
}
if (-not $Source) {
    $Source = Split-Path -Parent $MyInvocation.MyCommand.Path
}

$sourcePath  = (Resolve-Path -LiteralPath $Source).Path.TrimEnd('\')
$projectName = Split-Path -Leaf $sourcePath
$destPath    = Join-Path $Workspace $projectName

if (Test-Path -LiteralPath $destPath) {
    $destResolved = (Resolve-Path -LiteralPath $destPath).Path.TrimEnd('\')
    if ($sourcePath -eq $destResolved) {
        throw "This folder is already the Eclipse project. Run the script from the VS Code folder instead."
    }
}

Write-Host "Source : $sourcePath" -ForegroundColor Cyan
Write-Host "Project: $destPath" -ForegroundColor Cyan

# 1. Copy the code (skip .git and .vscode)
robocopy $sourcePath $destPath /E /XD ".git" ".vscode" /NFL /NDL /NJH /NJS /NP | Out-Null
if ($LASTEXITCODE -gt 7) {
    throw "Copy failed with robocopy exit code $LASTEXITCODE."
}

# 2. Create Eclipse project metadata
$utf8NoBom = New-Object System.Text.UTF8Encoding($false)

function Write-TextFile {
    param([string]$Path, [string]$Content)
    $parent = Split-Path -Parent $Path
    if (-not (Test-Path -LiteralPath $parent)) {
        New-Item -ItemType Directory -Path $parent -Force | Out-Null
    }
    [System.IO.File]::WriteAllText($Path, $Content, $utf8NoBom)
}

$projectXml = @"
<?xml version="1.0" encoding="UTF-8"?>
<projectDescription>
	<name>$projectName</name>
	<comment></comment>
	<projects>
	</projects>
	<buildSpec>
		<buildCommand>
			<name>org.eclipse.wst.common.project.facet.core.builder</name>
			<arguments>
			</arguments>
		</buildCommand>
		<buildCommand>
			<name>org.eclipse.wst.validation.validationbuilder</name>
			<arguments>
			</arguments>
		</buildCommand>
		<buildCommand>
			<name>org.eclipse.dltk.core.scriptbuilder</name>
			<arguments>
			</arguments>
		</buildCommand>
	</buildSpec>
	<natures>
		<nature>org.eclipse.php.core.PHPNature</nature>
		<nature>org.eclipse.wst.common.project.facet.core.nature</nature>
	</natures>
</projectDescription>
"@

$buildpathXml = @"
<?xml version="1.0" encoding="UTF-8"?>
<buildpath>
	<buildpathentry kind="src" path=""/>
	<buildpathentry kind="con" path="org.eclipse.php.core.LANGUAGE"/>
</buildpath>
"@

$facetXml = @"
<?xml version="1.0" encoding="UTF-8"?>
<faceted-project>
  <fixed facet="php.core.component"/>
  <installed facet="php.core.component" version="1"/>
  <installed facet="php.component" version="8.2"/>
</faceted-project>
"@

Write-TextFile (Join-Path $destPath '.project') $projectXml
Write-TextFile (Join-Path $destPath '.buildpath') $buildpathXml
Write-TextFile (Join-Path $destPath '.settings\org.eclipse.wst.common.project.facet.core.xml') $facetXml
Write-TextFile (Join-Path $destPath '.settings\org.eclipse.php.core.prefs') "eclipse.preferences.version=1`r`nphpVersion=php8.2`r`n"
Write-TextFile (Join-Path $destPath '.settings\org.eclipse.core.resources.prefs') "eclipse.preferences.version=1`r`nencoding/<project>=UTF-8`r`n"

# 3. Register the PHP executable in the Eclipse workspace
if (-not $SkipPhpExecutable) {
    $settingsDir = Join-Path $Workspace '.metadata\.plugins\org.eclipse.core.runtime\.settings'
    $prefsFile   = Join-Path $settingsDir 'org.eclipse.php.debug.core.prefs'

    if (-not (Test-Path -LiteralPath $PhpExe)) {
        Write-Host "PHP executable not found: $PhpExe (skipped)" -ForegroundColor Yellow
    }
    elseif (-not (Test-Path -LiteralPath $settingsDir)) {
        Write-Host "Workspace not initialized yet. Start Eclipse once, then run this script again." -ForegroundColor Yellow
    }
    else {
        $map = [ordered]@{}
        if (Test-Path -LiteralPath $prefsFile) {
            foreach ($line in Get-Content -LiteralPath $prefsFile) {
                $i = $line.IndexOf('=')
                if ($i -gt 0) { $map[$line.Substring(0, $i)] = $line.Substring($i + 1) }
            }
        }
        if (-not $map.Contains('eclipse.preferences.version')) {
            $map['eclipse.preferences.version'] = '1'
        }
        if ($map.Contains('org.eclipse.php.debug.coreinstalledPHPLocations')) {
            Write-Host "A PHP interpreter is already registered - left unchanged." -ForegroundColor Yellow
        }
        else {
            $map['org.eclipse.php.debug.coreinstalledPHPNames'] = 'XAMPP PHP'
            $map['org.eclipse.php.debug.coreinstalledPHPUniqueIds'] = 'php-exe-xampp82'
            $map['org.eclipse.php.debug.coreinstalledPHPLocations'] = $PhpExe.Replace('\', '/')
            $map['org.eclipse.php.debug.coreinstalledPHPInis'] = 'null'
            $map['org.eclipse.php.debug.coreinstalledPHPDebuggers'] = 'org.eclipse.php.debug.core.noneDebugger'
            $map['org.eclipse.php.debug.coreinstalledPHPLoadIniDefaults'] = 'false'
            $map['org.eclipse.php.debug.corephp_versions'] = 'php8.2'
            $map['org.eclipse.php.debug.coredefaultPHP'] = 'XAMPP PHP'

            $lines = foreach ($key in $map.Keys) { "$key=$($map[$key])" }
            Write-TextFile $prefsFile ($lines -join "`r`n")
            Write-Host "Registered PHP interpreter: $PhpExe" -ForegroundColor Green
        }
    }
}

Write-Host ""
Write-Host "Done. The project is ready in Eclipse:" -ForegroundColor Green
Write-Host "  $destPath" -ForegroundColor Green
Write-Host "If it is not listed: File > Import > General > Existing Projects into Workspace." -ForegroundColor Gray
Write-Host ""
Write-Host "To preview in a browser, run .\sync-to-xampp.ps1 and open http://localhost/$projectName/" -ForegroundColor Yellow
