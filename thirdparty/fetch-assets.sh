#!/usr/bin/env bash
# Fetch explicitly versioned third-party browser assets before packaging the plugin.
# Build-time only: learner browsers never request these assets from a CDN.
set -euo pipefail
cd "$(dirname "$0")"
mkdir -p pdfjs pageflip
curl -fL --retry 3 --proto '=https' --tlsv1.2 \
  'https://cdn.jsdelivr.net/npm/pdfjs-dist@2.16.105/build/pdf.min.js' \
  -o pdfjs/pdf.min.js
curl -fL --retry 3 --proto '=https' --tlsv1.2 \
  'https://cdn.jsdelivr.net/npm/pdfjs-dist@2.16.105/build/pdf.worker.min.js' \
  -o pdfjs/pdf.worker.min.js
curl -fL --retry 3 --proto '=https' --tlsv1.2 \
  'https://cdn.jsdelivr.net/npm/page-flip@2.0.7/dist/js/page-flip.browser.js' \
  -o pageflip/page-flip.browser.js
for file in pdfjs/pdf.min.js pdfjs/pdf.worker.min.js pageflip/page-flip.browser.js; do
  if [[ ! -s "$file" ]]; then
    echo "Missing browser asset: $file" >&2
    exit 1
  fi
done
sha256sum pdfjs/pdf.min.js pdfjs/pdf.worker.min.js pageflip/page-flip.browser.js > SHA256SUMS
printf 'Bundled assets downloaded. Validate the SHA-256 values against trusted release artifacts.\n'
