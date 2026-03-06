param(
    [string]$ProjectRoot = "",
    [int]$SeedCount = 120
)

Set-StrictMode -Version Latest
$ErrorActionPreference = "Stop"

if ($ProjectRoot -eq "") {
    $ProjectRoot = (Resolve-Path (Join-Path $PSScriptRoot "..\..")).Path
}

$photosDir = Join-Path $ProjectRoot "uploads\photos"
$docsDir = Join-Path $ProjectRoot "uploads\documents"

if (-not (Test-Path -LiteralPath $photosDir)) { New-Item -ItemType Directory -Path $photosDir | Out-Null }
if (-not (Test-Path -LiteralPath $docsDir)) { New-Item -ItemType Directory -Path $docsDir | Out-Null }

# 1x1 JPEG (valid tiny image)
$jpgBase64 = '/9j/4AAQSkZJRgABAQAAAQABAAD/2wCEAAkGBxAQEBUQEBAVFRUVFRUVFRUVFRUVFRUVFRUWFhUVFRUYHSggGBolGxUVITEhJSkrLi4uFx8zODMtNygtLisBCgoKDg0OGxAQGy0lHyUtLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLf/AABEIAAEAAQMBIgACEQEDEQH/xAAXAAADAQAAAAAAAAAAAAAAAAAAAQMC/8QAFhEBAQEAAAAAAAAAAAAAAAAAAAER/9oADAMBAAIQAxAAAAH2A//EABYQAQEBAAAAAAAAAAAAAAAAAAABEf/aAAgBAQABBQJp/8QAFhEBAQEAAAAAAAAAAAAAAAAAABEh/9oACAEDAQE/AYf/xAAVEQEBAAAAAAAAAAAAAAAAAAABEP/aAAgBAgEBPwGn/8QAFBABAAAAAAAAAAAAAAAAAAAAEP/aAAgBAQAGPwJf/8QAGhAAAwEBAQEAAAAAAAAAAAAAAAERITFBUf/aAAgBAQABPyE9m8Q29m8nqJ2sKQ4j/9oADAMBAAIAAwAAABCD/8QAFhEBAQEAAAAAAAAAAAAAAAAAARAR/9oACAEDAQE/EFX/xAAWEQEBAQAAAAAAAAAAAAAAAAABEBH/2gAIAQIBAT8Qqf/EABoQAAMBAQEBAAAAAAAAAAAAAAABERAhMUH/2gAIAQEAAT8QvL6kQPSprY6QmUjJxDYV8Y2QwS2m7Y0cX//2Q=='
$jpgBytes = [Convert]::FromBase64String($jpgBase64)

$pdfContent = @'
%PDF-1.4
1 0 obj
<< /Type /Catalog /Pages 2 0 R >>
endobj
2 0 obj
<< /Type /Pages /Count 1 /Kids [3 0 R] >>
endobj
3 0 obj
<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Contents 4 0 R /Resources << /Font << /F1 5 0 R >> >> >>
endobj
4 0 obj
<< /Length 78 >>
stream
BT
/F1 14 Tf
72 720 Td
(San Enrique LGU Scholarship - Flood Seed Document) Tj
ET
endstream
endobj
5 0 obj
<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>
endobj
xref
0 6
0000000000 65535 f 
0000000009 00000 n 
0000000058 00000 n 
0000000115 00000 n 
0000000241 00000 n 
0000000369 00000 n 
trailer
<< /Size 6 /Root 1 0 R >>
startxref
449
%%EOF
'@

if ($SeedCount -lt 1) {
    throw "SeedCount must be at least 1."
}

1..$SeedCount | ForEach-Object {
    $id = $_.ToString('0000')
    $photoFile = Join-Path $photosDir ("app_flood_{0}.jpg" -f $id)
    if (-not (Test-Path -LiteralPath $photoFile)) {
        [IO.File]::WriteAllBytes($photoFile, $jpgBytes)
    }

    $base = "app_flood_{0}" -f $id
    $docs = @(
        "${base}_report_card.pdf",
        "${base}_enrollment.pdf",
        "${base}_barangay_residency.pdf",
        "${base}_good_moral.pdf",
        "${base}_soa.pdf"
    )
    foreach ($doc in $docs) {
        $docFile = Join-Path $docsDir $doc
        if (-not (Test-Path -LiteralPath $docFile)) {
            Set-Content -LiteralPath $docFile -Value $pdfContent -Encoding ASCII
        }
    }
}

Write-Host "Flood seed assets created/verified in:"
Write-Host " - $photosDir"
Write-Host " - $docsDir"
