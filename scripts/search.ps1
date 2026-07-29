$searchTopics = @(
    @{ Name = 'TODO comments'; Pattern = 'TODO' },
    @{ Name = 'FIXME comments'; Pattern = 'FIXME' },
    @{ Name = 'Hard-coded passwords'; Pattern = 'password\s*=\s*[''"][^''"]+[''"]' },
    @{ Name = 'Secrets and private keys'; Pattern = 'BEGIN\s+PRIVATE\s+KEY' },
    @{ Name = 'Raw National ID logging'; Pattern = 'national_id.*log' },
    @{ Name = 'Unsafe SQL concatenation'; Pattern = '->query\([^)]*\$' },
    @{ Name = 'Direct unsafe superglobal access in views'; Pattern = '\$_(GET|POST|REQUEST)\[' },
    @{ Name = 'Unescaped output in views'; Pattern = '<\?=\s*\$_(GET|POST)' },
    @{ Name = 'Debug statements (var_dump/print_r/dd)'; Pattern = '\b(var_dump|print_r|dd)\(' },
    @{ Name = 'State-changing GET routes'; Pattern = 'GET.*/(delete|update|remove|destroy)' },
    @{ Name = 'Missing CSRF enforcement on POST'; Pattern = 'POST.*no_csrf' },
    @{ Name = 'Local absolute paths'; Pattern = '[C-Z]:\\[^\\]+\\' },
    @{ Name = 'PHP 8.1 references'; Pattern = '8\.1' },
    @{ Name = 'Platform requirement bypass flags'; Pattern = 'ignore-platform-req' }
)

$files = Get-ChildItem -Recurse -File | Where-Object { $_.FullName -notmatch '[\\/](vendor|\.git|logs|evidence)[\\/]' -and $_.Name -ne 'git-diff.patch' -and $_.Name -ne 'lint.ps1' -and $_.Name -ne 'search.ps1' }
$outLines = [System.Collections.Generic.List[string]]::new()
$outLines.Add('=== PROJECT-WIDE SECURITY & QUALITY SEARCH RESULTS ===')
$outLines.Add('Timestamp: ' + (Get-Date -Format 'yyyy-MM-dd HH:mm:ss'))
$outLines.Add('-------------------------------------------------------')

foreach ($topic in $searchTopics) {
    $outLines.Add("Search Topic: $($topic['Name'])")
    $matchedFiles = [System.Collections.Generic.List[string]]::new()
    foreach ($file in $files) {
        $content = Get-Content -Path $file.FullName -Raw
        if ($content -match $topic['Pattern']) {
            $matchedFiles.Add("  - $($file.FullName)")
        }
    }
    if ($matchedFiles.Count -eq 0) {
        $outLines.Add('  Results: 0 matches found (CLEAN).')
    } else {
        $outLines.Add("  Results: $($matchedFiles.Count) matches found:")
        foreach ($m in $matchedFiles) {
            $outLines.Add($m)
        }
    }
    $outLines.Add('-------------------------------------------------------')
}

$outLines | Out-File -FilePath evidence\project_searches.raw.txt -Encoding utf8
$outLines | Write-Host
