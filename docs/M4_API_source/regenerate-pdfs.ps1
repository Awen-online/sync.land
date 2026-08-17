# Regenerate M4 PDFs from the .md sources in this folder.
# Uses docs-template.html (masthead + right-metadata) and docs-print.css.
# Requires: Pandoc + Chrome installed at default locations.

$here     = Split-Path $MyInvocation.MyCommand.Path -Parent
$target   = Split-Path $here -Parent | Join-Path -ChildPath 'M4_API'
$css      = Join-Path $here 'docs-print.css'
$template = Join-Path $here 'docs-template.html'
$header   = Join-Path $here 'syncland-header.png'
# Chrome hands off to an already-running user session instead of rendering,
# so prefer Edge (same engine, normally idle) and fall back to Chrome.
$chrome   = 'C:\Program Files (x86)\Microsoft\Edge\Application\msedge.exe'
if (-not (Test-Path $chrome)) { $chrome = 'C:\Program Files\Google\Chrome\Application\chrome.exe' }

if (-not (Test-Path $target))   { New-Item -ItemType Directory -Path $target | Out-Null }
if (-not (Test-Path $css))      { Write-Host "docs-print.css missing"; exit 1 }
if (-not (Test-Path $template)) { Write-Host "docs-template.html missing"; exit 1 }
if (-not (Test-Path $header))   { Write-Host "syncland-header.png missing"; exit 1 }
if (-not (Test-Path $chrome))   { Write-Host "Chrome not at default path"; exit 1 }

$milestone       = 'Milestone 4 - Marketplace Updates & API Launch'
$reportingPeriod = '2026-06-23 to 2026-08-17'
$issued          = 'August 17, 2026'

$docs = @(
    @{ md = 'SyncLand_Project_Status_Report_M4.md';   title = 'Project Status Report';    subtitle = 'ACCEPTANCE CRITERIA STATUS & DELIVERABLES' },
    @{ md = 'SyncLand_User_Feedback_Report_M4.md';    title = 'User Feedback Report';     subtitle = 'POST-LAUNCH FEEDBACK CHANNELS & RESPONSES' },
    @{ md = 'SyncLand_Marketplace_Updates_M4.md';     title = 'Marketplace Updates';      subtitle = 'CHANGELOG SINCE M3 & CATEGORIZED SHIPPED WORK' },
    @{ md = 'SyncLand_API_Documentation_M4.md';       title = 'API Documentation';        subtitle = 'REST API SURFACE & SPECIFICATION' },
    @{ md = 'SyncLand_OBS_Player_Architecture_M4.md'; title = 'OBS Player Architecture';  subtitle = 'DOCK & STREAMER - ARCHITECTURE AND DATA FLOW' },
    @{ md = 'SyncLand_Project_Timeline_M4.md';        title = 'Project Timeline';        subtitle = 'MILESTONE SCHEDULE, ACTUAL DELIVERY & REMAINING SCOPE' }
)

$i = 0
foreach ($doc in $docs) {
    $i++
    $md = Join-Path $here $doc.md
    if (-not (Test-Path $md)) { Write-Host "SKIP: $($doc.md)"; continue }
    $base = [System.IO.Path]::GetFileNameWithoutExtension($doc.md)
    # Intermediate HTML lives in TEMP so its path has no spaces (Chrome argv
    # splits on unquoted spaces and misreads the URL as multiple targets).
    $html = Join-Path $env:TEMP "m4-$base.html"
    $pdf  = Join-Path $target "$base.pdf"
    $udd  = Join-Path $env:TEMP "chrome-pdf-$i-$(Get-Random)"

    Write-Host "[$i/$($docs.Count)] $($doc.md)"

    & pandoc $md `
        --standalone `
        --template=$template `
        --embed-resources `
        --resource-path=$here `
        --css=$css `
        --metadata "title=$($doc.title)" `
        --metadata "subtitle=$($doc.subtitle)" `
        --metadata "milestone=$milestone" `
        --metadata "reporting_period=$reportingPeriod" `
        --metadata "date=$issued" `
        --output=$html 2>&1 | Out-Null

    # Wrap PDF path in quotes so Chrome doesn't split on spaces in "My Drive"
    $htmlUrl = "file:///" + ($html -replace '\\','/')
    Start-Process -Wait -NoNewWindow -FilePath $chrome -ArgumentList @(
        '--headless=new', '--disable-gpu', '--no-pdf-header-footer',
        "--user-data-dir=`"$udd`"",
        "--print-to-pdf=`"$pdf`"",
        $htmlUrl
    ) 2>$null

    Start-Sleep -Milliseconds 400
    if (Test-Path $pdf) {
        Write-Host ("   OK: {0} kB" -f [math]::Round((Get-Item $pdf).Length/1024,1))
    } else {
        Write-Host "   FAILED"
    }
    # Clean up the intermediate HTML in the source folder
    if (Test-Path $html) { Remove-Item $html -Force -ErrorAction SilentlyContinue }
}

Write-Host ""
Write-Host "=== M4_API contents ==="
Get-ChildItem $target -File | Sort-Object Name | Format-Table Name, @{n='kB';e={[math]::Round($_.Length/1024,1)}} -AutoSize
