$ErrorActionPreference = 'Stop'
$origin = 'https://imran-ali-dev.netlify.app'
$workspace = $PSScriptRoot
$destination = Join-Path $workspace 'dist'
New-Item -ItemType Directory -Force -Path $destination | Out-Null
$utf8 = New-Object System.Text.UTF8Encoding($false)
$assets = New-Object 'System.Collections.Generic.HashSet[string]'
function Save-Asset([string]$path) {
    $path = [Net.WebUtility]::HtmlDecode($path)
    if ($path -match '^/_next/image\?') {
        $path = [Uri]::UnescapeDataString(([regex]::Match($path,'[?&]url=([^&]+)')).Groups[1].Value)
    }
    $path = $path.Split('?')[0]
    if (-not $assets.Add($path)) { return }
    $output = Join-Path $destination $path.TrimStart('/')
    New-Item -ItemType Directory -Force -Path (Split-Path $output) | Out-Null
    & curl.exe -sS -f -L "$origin$path" -o $output
    if ($LASTEXITCODE -ne 0) { throw "Unable to download $path" }
    Write-Output "Asset: $path"
    if ($path.EndsWith('.css')) {
        $css = [IO.File]::ReadAllText($output)
        foreach ($match in [regex]::Matches($css,'url\(([^)]+)\)')) {
            $value = $match.Groups[1].Value.Trim('"',"'")
            if ($value.StartsWith('data:')) { continue }
            $url = [Uri]::new([Uri]::new("$origin$path"),$value)
            if ($url.Host -eq ([Uri]$origin).Host) { Save-Asset $url.AbsolutePath }
        }
        $css = $css.Replace('url(/_next/static/media/','url(../media/')
        [IO.File]::WriteAllText($output,$css,$utf8)
    }
}
$pages = @(@{source=(Join-Path $workspace 'reference.html');route=''})
foreach ($file in Get-ChildItem (Join-Path $workspace 'reference') -Filter index.html -Recurse) {
    $route = $file.DirectoryName.Substring((Join-Path $workspace 'reference').Length).TrimStart('\').Replace('\','/')
    $pages += @{source=$file.FullName;route=$route}
}
foreach ($page in $pages) {
    $html = [IO.File]::ReadAllText($page.source)
    $html = [regex]::Replace($html,'<script\b[^>]*>[\s\S]*?</script>','')
    $html = [regex]::Replace($html,'<link\b[^>]*as="script"[^>]*>','')
    foreach ($match in [regex]::Matches($html,'(?:src|href)="(/[^"#]*)"')) {
        $path = [Net.WebUtility]::HtmlDecode($match.Groups[1].Value)
        if ($path -match '\.(css|woff2|png|jpg|jpeg|svg|ico|pdf|webp)(\?|$)' -or $path.StartsWith('/_next/image')) { Save-Asset $path }
    }
    $html = [regex]::Replace($html,'(?:imageSrcSet|srcSet)="[^"]*"','')
    $html = [regex]::Replace($html,'(/_next/image\?[^" ]+)',{
        param($match)
        $decoded = [Net.WebUtility]::HtmlDecode($match.Value)
        [Uri]::UnescapeDataString(([regex]::Match($decoded,'[?&]url=([^&]+)')).Groups[1].Value)
    })
    $depth = if ($page.route -eq '') {0} else {$page.route.Split('/').Count}
    $prefix = if ($depth -eq 0) {'./'} else {'../' * $depth}
    $html = [regex]::Replace($html,'(href|src)="/([^"?]*)(?:\?[^" ]*)?"',{
        param($match)
        $path = $match.Groups[2].Value
        if ($path -ne '' -and $path -notmatch '\.[a-zA-Z0-9]+$') { $path += '/' }
        $match.Groups[1].Value + '="' + $prefix + $path + '"'
    })
    $html = $html.Replace('</head>',"<link rel=`"stylesheet`" href=`"${prefix}site.css`"/></head>")
    $html = $html.Replace('</body>',"<script src=`"${prefix}site.js`" defer></script></body>")
    $directory = Join-Path $destination $page.route
    New-Item -ItemType Directory -Force -Path $directory | Out-Null
    [IO.File]::WriteAllText((Join-Path $directory 'index.html'),$html,$utf8)
    Write-Output "Page: /$($page.route)"
}
Copy-Item -LiteralPath (Join-Path $workspace 'site.js') -Destination $destination
Copy-Item -LiteralPath (Join-Path $workspace 'site.css') -Destination $destination
Write-Output "Complete: $($pages.Count) pages, $($assets.Count) assets."
