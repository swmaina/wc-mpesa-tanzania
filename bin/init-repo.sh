#!/bin/bash

# Initialize GitHub repository for WooCommerce M-Pesa Tanzania
# Usage: bash bin/init-repo.sh YOUR_GITHUB_USERNAME

if [ -z "$1" ]; then
    echo "Usage: bash bin/init-repo.sh YOUR_GITHUB_USERNAME"
    echo "Example: bash bin/init-repo.sh swmaina"
    exit 1
fi

GITHUB_USER=$1
REPO_NAME="wc-mpesa-tanzania"
REMOTE_URL="https://github.com/${GITHUB_USER}/${REPO_NAME}.git"

echo "🚀 Initializing WooCommerce M-Pesa Tanzania Repository"
echo "=================================================="
echo "GitHub User: $GITHUB_USER"
echo "Repository: $REPO_NAME"
echo "Remote URL: $REMOTE_URL"
echo ""

# Check if git is initialized
if [ ! -d .git ]; then
    echo "📦 Initializing git repository..."
    git init
    git config user.name "Your Name"
    git config user.email "your.email@example.com"
else
    echo "✅ Git repository already initialized"
fi

# Add remote
echo "🔗 Adding remote origin..."
git remote remove origin 2>/dev/null
git remote add origin "$REMOTE_URL"

# Create initial commit
echo "📝 Creating initial commit..."
git add .
git commit -m "Initial commit: WooCommerce M-Pesa Tanzania plugin v1.0.0" --allow-empty

# Create main branch
echo "🌿 Setting up main branch..."
git branch -M main

# Push to GitHub
echo "📤 Pushing to GitHub..."
git push -u origin main

echo ""
echo "✨ Repository initialized successfully!"
echo ""
echo "Next steps:"
echo "1. Go to https://github.com/${GITHUB_USER}/${REPO_NAME}"
echo "2. Add repository topics: wordpress, woocommerce, m-pesa, vodacom, tanzania, payment-gateway"
echo "3. Enable Discussions (Settings > Features > Discussions)"
echo "4. Enable GitHub Pages (Settings > Pages)"
echo "5. Set branch protection rules (Settings > Branches)"
echo ""