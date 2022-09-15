#
# Configuration for the copy-project.sh script
#

copy_target="$(dirname "$PROJECT_DIR")/free-plugin-name"
export copy_replacements=(
  "PREMIUM_VERSION==>FREE_VERSION"
  "PREMIUM_URL==>FREE_URL"
  "PREMIUM_PATH==>FREE_PATH"
  "'premium_loaded'==>'free_loaded'"
  "'premium_helper_==>'free_helper_"
  "function premium_helper_==>function free_helper_"
  "Premium Plugin classes==>Free Plugin classes"
)
export copy_target_lang=free-lang
export copy_target_prefix=free
export copy_target_package=FreePlugin
export copy_target_type=free
export copy_target

