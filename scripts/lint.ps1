$phpFiles = Get-ChildItem -Recurse -Filter '*.php' | Where-Object { $_.FullName -notmatch '[\\/]vendor[\\/]' }
$total = $phpFiles.Count
$passed = 0
$failed = 0
$logLines = @()

$logLines += '=== RECURSIVE PHP SYNTAX LINT REPORT ==='
$logLines += 'Timestamp: ' + (Get-Date -Format 'yyyy-MM-dd HH:mm:ss')
$logLines += '----------------------------------------'

foreach ($file in $phpFiles) {
    $res = & C:\php82\php.exe -l $file.FullName 2>&1
    if ($LASTEXITCODE -eq 0) {
        $passed++
        $logLines += "[PASS] $($file.FullName)"
    } else {
        $failed++
        $logLines += "[FAIL] $($file.FullName) : $res"
    }
}

$logLines += '----------------------------------------'
$logLines += "TOTAL FILES: $total"
$logLines += "PASSED     : $passed"
$logLines += "FAILED     : $failed"

$logLines | Out-File -FilePath evidence\php_syntax_lint.raw.txt -Encoding utf8
$logLines | Write-Host
