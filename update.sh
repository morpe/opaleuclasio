#!/bin/bash
# update.sh - Scarica opale.php, euclasio.php, style.css e update.sh dalla repo GitHub

REPO_OWNER="morpe"
REPO_NAME="opaleuclasio"
BRANCH="main"

echo "📥 Downloading Opale, Euclasio, style.css and update.sh from GitHub..."
echo "   Repository: $REPO_OWNER/$REPO_NAME"

# Scarica i file
curl -s -o opale.php "https://raw.githubusercontent.com/$REPO_OWNER/$REPO_NAME/$BRANCH/opale.php"
curl -s -o euclasio.php "https://raw.githubusercontent.com/$REPO_OWNER/$REPO_NAME/$BRANCH/euclasio.php"
curl -s -o style.css "https://raw.githubusercontent.com/$REPO_OWNER/$REPO_NAME/$BRANCH/style.css"
curl -s -o .update.sh.tmp "https://raw.githubusercontent.com/$REPO_OWNER/$REPO_NAME/$BRANCH/update.sh"

# Aggiorna update.sh (da file temporaneo per non corrompere lo script in esecuzione)
if [ -f .update.sh.tmp ]; then
    mv .update.sh.tmp update.sh
    chmod +x update.sh
fi

# Verifica
if [ -f opale.php ] && [ -f euclasio.php ]; then
    echo "✅ Done!"
    echo "   - opale.php ($(wc -l < opale.php) lines)"
    echo "   - euclasio.php ($(wc -l < euclasio.php) lines)"
    [ -f style.css ] && echo "   - style.css ($(wc -l < style.css) lines)"
else
    echo "❌ Download failed."
    exit 1
fi