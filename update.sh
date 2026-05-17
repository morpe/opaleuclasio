#!/bin/bash
# update.sh - Scarica opale.php e euclasio.php dalla repo GitHub

REPO_OWNER="morpe"
REPO_NAME="opaleuclasio"
BRANCH="main"

echo "📥 Downloading Opale and Euclasio from GitHub..."
echo "   Repository: $REPO_OWNER/$REPO_NAME"

# Scarica i file
curl -s -o opale.php "https://raw.githubusercontent.com/$REPO_OWNER/$REPO_NAME/$BRANCH/opale.php"
curl -s -o euclasio.php "https://raw.githubusercontent.com/$REPO_OWNER/$REPO_NAME/$BRANCH/euclasio.php"

# Verifica
if [ -f opale.php ] && [ -f euclasio.php ]; then
    echo "✅ Done!"
    echo "   - opale.php ($(wc -l < opale.php) lines)"
    echo "   - euclasio.php ($(wc -l < euclasio.php) lines)"
else
    echo "❌ Download failed."
    exit 1
fi