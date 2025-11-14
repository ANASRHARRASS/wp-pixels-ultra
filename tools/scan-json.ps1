<#
Simple PowerShell tool to scan the plugin folder for .json files and validate them.

Usage:
  powershell -ExecutionPolicy Bypass -File .\tools\scan-json.ps1

Outputs:
  - Console summary
  - Creates/overwrites scan-report.json in the plugin root
#>

$scriptDir = Split-Path -Parent $MyInvocation.MyCommand.Path
$pluginRoot = (Resolve-Path (Join-Path $scriptDir '..')).ProviderPath

Write-Host "Plugin root: $pluginRoot"
Write-Host "Scanning for .json files..."

$files = Get-ChildItem -Path $pluginRoot -Recurse -Filter *.json -File -ErrorAction SilentlyContinue

$results = [ordered]@{
    scanned_at = (Get-Date).ToString("o")
    plugin_root = $pluginRoot
    total_files = $files.Count
    valid = @()
    invalid = @()
}

foreach ($f in $files) {
    $rel = $f.FullName.Substring($pluginRoot.Length + 1)
    try {
        $content = Get-Content -Raw -LiteralPath $f.FullName -ErrorAction Stop
    } catch {
        $results.invalid += @{ file = $rel; error = "Cannot read file" }
        continue
    }

    if ([string]::IsNullOrWhiteSpace($content)) {
        $results.invalid += @{ file = $rel; error = "Empty file" }
        continue
    }

    try {
        $null = $content | ConvertFrom-Json -ErrorAction Stop
        $results.valid += $rel
    } catch {
        $results.invalid += @{ file = $rel; error = $_.Exception.Message }
    }
}

Write-Host "Total files: $($results.total_files)"
Write-Host "Valid files: $($results.valid.Count)"
Write-Host "Invalid files: $($results.invalid.Count)"

if ($results.invalid.Count -gt 0) {
    Write-Host "`nInvalid files detail:"
    foreach ($inv in $results.invalid) {
        Write-Host "- $($inv.file): $($inv.error)"
    }
}

$reportPath = Join-Path $pluginRoot 'scan-report.json'
try {
    $results | ConvertTo-Json -Depth 5 | Set-Content -LiteralPath $reportPath -Encoding UTF8
    Write-Host "`nReport written to: $reportPath"
} catch {
    Write-Host "Failed to write report to $reportPath : $_"
}
