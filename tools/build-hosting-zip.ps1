$ErrorActionPreference = 'Stop'
$projectDirectory = Split-Path $PSScriptRoot -Parent
$phpExecutable = 'C:/xampp/php/php.exe'
$buildJson = & $phpExecutable (Join-Path $PSScriptRoot 'prepare-hosting.php')
if ($LASTEXITCODE -ne 0) { throw 'Hosting preparation failed.' }
$build = $buildJson | ConvertFrom-Json
Add-Type -AssemblyName System.IO.Compression
Add-Type -AssemblyName System.IO.Compression.FileSystem
$archivePath = Join-Path $projectDirectory ('.deployment/portfolio-hosting-fixed-' + (Get-Date -Format 'yyyyMMdd-HHmmss') + '.zip')
$archiveStream = [IO.File]::Open($archivePath, [IO.FileMode]::CreateNew)
$archive = New-Object IO.Compression.ZipArchive($archiveStream, [IO.Compression.ZipArchiveMode]::Create)
try {
    # Linux hosting extractors require forward slashes, including directory entries.
    foreach ($directory in Get-ChildItem -LiteralPath $build.directory -Directory -Recurse) {
        $relative = $directory.FullName.Substring($build.directory.Length + 1).Replace('\', '/') + '/'
        $null = $archive.CreateEntry($relative)
    }
    foreach ($file in Get-ChildItem -LiteralPath $build.directory -File -Recurse -Force) {
        $relative = $file.FullName.Substring($build.directory.Length + 1).Replace('\', '/')
        $null = [IO.Compression.ZipFileExtensions]::CreateEntryFromFile($archive, $file.FullName, $relative, [IO.Compression.CompressionLevel]::Optimal)
    }
} finally { $archive.Dispose(); $archiveStream.Dispose() }
$verification = [IO.Compression.ZipFile]::OpenRead($archivePath)
try {
    if (@($verification.Entries | Where-Object { $_.FullName.Contains('\') }).Count -ne 0) { throw 'ZIP contains incompatible paths.' }
    foreach ($required in @('admin/', 'cms/', 'dist/', 'admin/index.php', 'cms/data/.htaccess', 'cms/data/auth.json', 'dist/index.php', 'index.php')) {
        if (-not $verification.GetEntry($required)) { throw "ZIP is missing $required" }
    }
} finally { $verification.Dispose() }
Write-Output ('Verified Linux-compatible ZIP: ' + $archivePath)
Write-Output ('Runtime directory: ' + $build.directory)
