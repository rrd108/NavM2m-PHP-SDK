#!/bin/bash

PACKAGE_NAME="rrd108/nav-m2m"
TEST_COMMAND="composer test"
COMPOSER_FILE="composer.json"
GIT_BRANCH="main"

# --- Helper Functions ---
get_current_version() {
    jq -r '.version' "$COMPOSER_FILE"
}

generate_release_notes() {
    previous_tag=$(git describe --tags --abbrev=0 HEAD^ 2>/dev/null || echo "")
    range="${previous_tag}..HEAD"
    
    echo "### Release Notes for v${new_version}"
    echo
    
    git log "$range" --pretty=format:"%s" | while read -r commit; do
        # Skip chore commits
        case "$commit" in
            chore:*) continue ;;
        esac
        
        type=$(echo "$commit" | sed -n 's/^\([a-z]\+\)(\([^)]\+\))\!*: \(.*\)/\1/p')
        scope=$(echo "$commit" | sed -n 's/^\([a-z]\+\)(\([^)]\+\))\!*: \(.*\)/\2/p')
        message=$(echo "$commit" | sed -n 's/^\([a-z]\+\)(\([^)]\+\))\!*: \(.*\)/\3/p')
        
        if [ -n "$type" ]; then
            echo "- **${type}(${scope}):** ${message}"
        fi
    done
}

# --- Colors ---
GREEN='\033[0;32m'
NC='\033[0m'
RED='\033[0;31m'

# --- Colored Echo ---
ok_echo() {
    printf "${GREEN}%s${NC}\n" "$*"
}

error_echo() {
    printf "${RED}%s${NC}\n" "$*"
}

# 1. Parse command-line arguments
if [ "$#" -ne 1 ]; then
    error_echo "Usage: $0 <patch|minor|major>"
    exit 1
fi
release_type="$1"

# 2. Validate release type
case "$release_type" in
    "patch"|"minor"|"major") ;;
    *) error_echo "Invalid release type. Please choose patch, minor, or major."; exit 1 ;;
esac

# 3. Get the Current Version
current_version=$(get_current_version)
ok_echo "Current version: $current_version"

# 4. Run Tests
echo "Running tests..."
if ! $TEST_COMMAND; then
    error_echo "Tests failed. Release aborted."
    exit 1
fi
ok_echo "Tests passed."

# 5. Increment Version
major=$(echo "$current_version" | cut -d. -f1)
minor=$(echo "$current_version" | cut -d. -f2)
patch=$(echo "$current_version" | cut -d. -f3)

case "$release_type" in
    "patch")
        patch=$((patch + 1))
        ;;
    "minor")
        minor=$((minor + 1))
        patch=0
        ;;
    "major")
        major=$((major + 1))
        minor=0
        patch=0
        ;;
esac

new_version="$major.$minor.$patch"
ok_echo "New version: $new_version"

# 6. Update composer.json
jq --arg new_version "$new_version" '.version = $new_version' "$COMPOSER_FILE" > temp.json && mv temp.json "$COMPOSER_FILE"
ok_echo "composer.json updated."

# 7. Commit Changes
git add "$COMPOSER_FILE"
git commit -m "Bump version to v${new_version}"

# 8. Create Git Tag
release_notes=$(generate_release_notes)
git tag -a "v${new_version}" -m "Release v${new_version}" -m "$release_notes"
ok_echo "Git tag created: v${new_version} with release notes"

# 9. Push to git (with tags)
git push origin "$GIT_BRANCH" --tags
ok_echo "Pushed changes to origin with tags."

# 10. Done Message
ok_echo "Successfully released v${new_version} of $PACKAGE_NAME!"