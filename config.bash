#
# Do not change this file! It contains static project details
# that should be identical for every developer.
# To adjust settings, edit the file "config.local.bash"
#
# plugin_name:   The plugin slug
# prefix:        The prefix of custom functions (used by copy-project.sh)
# package:       The namespace/package name (used by copy-project.sh)
# text_domain:   The translation text-domain (used by copy-project.sh)
# plugin_type:   free or pro
# build_exclude: List of files to exclude from the build process (array).
#

export plugin_name=gdpr-cache-script-styles
export prefix=
export package=GdprCache
export text_domain=gdpr-cache
export plugin_type=free
export build_exclude=('/docs')

# Local build targets (optional)
# Local sites that should receive the plugin code for testing (after deploy.sh).

export build_targets=()
