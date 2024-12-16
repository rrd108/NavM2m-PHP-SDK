#!/bin/bash

set -e

PACKAGE_NAME="rrd108/nav-m2m"
TEST_COMMAND="composer test"
COMPOSER_FILE="composer.json"
GIT_BRANCH="main"


# --- Helper ---
get_current_version() {
  jq -r '.version' "$COMPOSER_FILE"
}

# 1. Parse command-line arguments
if [ "$#" -ne 1 ]; then
  echo "Usage: $0 <patch|minor|major>"
  exit 1
fi
release_type="$1"

# 2. Validate release type
case "$release_type" in
  "patch" | "minor" | "major" ) ;;
  *) echo "Invalid release type. Please choose patch, minor, or major."; exit 1 ;;
esac

# 3. Get the Current Version
current_version=$(get_current_version)
echo "Current version: $current_version"

# 4. Run Tests
echo "Running tests..."
$TEST_COMMAND

if [ $? -ne 0 ]; then
  echo "Tests failed. Release aborted."
  exit 1
fi

echo "Tests passed."

# 5. Increment Version
IFS="." read -r major minor patch <<< "$current_version"

case "$release_type" in
    "patch")
      ((patch++))
    ;;
    "minor")
      ((minor++))
      patch=0
    ;;
    "major")
      ((major++))
      minor=0
      patch=0
    ;;
esac

new_version="$major.$minor.$patch"

echo "New version: $new_version"

# 6. Update composer.json
jq --arg new_version "$new_version" '.version = $new_version' "$COMPOSER_FILE" > temp.json && mv temp.json "$COMPOSER_FILE"
echo "composer.json updated."


# 7. Commit Changes
git add "$COMPOSER_FILE"
git commit -m "Bump version to v${new_version}"

# 8. Create Git Tag
git tag "v${new_version}" -m "Release v${new_version}"
echo "Git tag created: v${new_version}"

# 9. Push to git (with tags)
git push origin "$GIT_BRANCH" --tags
echo "Pushed changes to origin with tags."

# 10. Done Message
echo "Successfully released v${new_version} of $PACKAGE_NAME!"