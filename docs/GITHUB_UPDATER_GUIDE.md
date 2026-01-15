# WordPress GitHub Self-Updater Guide

**Project**: LeadStream (and future plugins)  
**Core Goal**: Enable automatic updates directly from a GitHub repository to bypass the WordPress.org review queue.

## Overview

This solution uses the **Plugin Update Checker (PUC)** library by Yahnis Elsts. It allows the plugin to check a GitHub repository, compare the local version number with the latest GitHub Release tag, and handle the ZIP download and installation automatically within the WordPress dashboard.

## Architecture

### Directory Structure

```
leadstream-analytics-injector.php    ← Main Plugin File
plugin-update-checker/               ← PUC Library Folder
├── plugin-update-checker.php
├── load-v5p6.php
├── Puc/
└── (other library files)
includes/
└── Updates/
    ├── Updater.php                  ← Bootstrap class
    └── GitHubUpdateManager.php      ← Singleton manager
```

## Implementation Details

### 1. GitHubUpdateManager.php (Singleton Pattern)

Located at: `includes/Updates/GitHubUpdateManager.php`

This class:
- Implements the Singleton pattern to ensure only one update check runs per page load
- Prevents GitHub API rate limiting
- Manages the PUC library integration
- Provides methods for manual update checks
- Tracks last update check timestamp

**Key Methods:**
- `get_instance($repo_url, $plugin_file, $slug)` - Get singleton instance
- `check_now()` - Manually trigger update check
- `get_last_checked_time()` - Get human-readable last check time
- `set_auth_token($token)` - Set GitHub token for private repos

### 2. Updater.php (Bootstrap)

Located at: `includes/Updates/Updater.php`

This lightweight class:
- Hooks into WordPress `plugins_loaded` action
- Initializes the GitHubUpdateManager singleton
- Provides clean separation between bootstrap and business logic

### 3. Bootstrap Integration

The updater is loaded in `includes/Bootstrap.php`:
- Added to `$update_components` array
- Loaded early in the initialization sequence
- Initialized in admin context via `Updater::boot()`

## How It Works

### Version Control Flow

1. **Local Version**: Defined in `leadstream-analytics-injector.php` header
2. **GitHub Release**: Created with matching version tag (e.g., `v2.12.3`)
3. **Update Check**: WordPress checks GitHub every 12 hours
4. **Notification**: If newer version found, shows "Update Available"
5. **Installation**: User clicks update, WordPress downloads and installs from GitHub

### The Update Workflow

```
Code Change → Version Bump → Git Tag → GitHub Release → WordPress Update
```

**Example:**
```bash
# 1. Update version in leadstream-analytics-injector.php
# Version: 2.12.3

# 2. Commit and tag
git add .
git commit -m "Add new tracking feature"
git tag v2.12.3
git push origin main --tags

# 3. Create GitHub Release
# - Go to GitHub Releases
# - Create new release with tag v2.12.3
# - Upload plugin ZIP or use auto-generated source

# 4. WordPress detects update automatically
```

## Configuration

### Repository URL
Currently configured: `https://github.com/shaunpalmer/LeadStream/`

To change, edit `includes/Updates/Updater.php`:
```php
$repo_url = 'https://github.com/your-username/your-plugin/';
```

### Plugin Slug
Currently: `leadstream-analytics-injector`

Must match the plugin directory name.

## Why Singleton Pattern?

Without the Singleton, if the update code was included in multiple files, WordPress might:
- Ping GitHub multiple times per page load
- Hit GitHub API rate limits (60 requests/hour for unauthenticated)
- Get IP blacklisted
- Slow down the admin dashboard

The Singleton ensures **one instance, one request**.

## Private Repository Support

For private repositories, add a GitHub Personal Access Token:

```php
$manager = \LS\Updates\GitHubUpdateManager::get_instance($repo_url, $plugin_file, $slug);
$manager->set_auth_token('ghp_your_token_here');
```

**Permissions needed:**
- `repo` (Full control of private repositories)

## Manual Update Check

To manually trigger an update check:

```php
$manager = \LS\Updates\GitHubUpdateManager::get_instance($repo_url, $plugin_file, $slug);
$manager->check_now(); // Forces immediate check, ignores 12-hour cache
```

## Caching

- **WordPress Cache**: 12 hours (WordPress default)
- **Manual Check**: Bypasses cache
- **Last Check Time**: Stored in `{slug}_last_check` option

## GitHub Release Requirements

### Required Files
1. **Release Tag**: Must match version (e.g., `v2.12.3`)
2. **ZIP Asset**: Plugin ZIP file (optional, auto-generated available)

### Best Practices
1. Use semantic versioning: `MAJOR.MINOR.PATCH`
2. Prefix tags with `v` (e.g., `v2.12.3`)
3. Include changelog in release notes
4. Test releases with a staging site first

## Troubleshooting

### Updates Not Appearing

**Check:**
1. Version number in plugin header matches tag
2. GitHub release is published (not draft)
3. 12 hours have passed since last check
4. WordPress has internet access

**Debug:**
```php
// Force update check
delete_transient('puc_request_info_result-' . $slug);
$manager->check_now();
```

### Rate Limiting

GitHub API limits:
- **Unauthenticated**: 60 requests/hour
- **Authenticated**: 5,000 requests/hour

**Solution**: Add authentication token for high-traffic sites.

## Security Considerations

1. **HTTPS Only**: All GitHub requests use HTTPS
2. **Signature Verification**: PUC verifies ZIP integrity
3. **Token Storage**: Store tokens in wp-config.php, not database
4. **Private Repos**: Use tokens with minimal permissions

## Future Enhancements

### Potential Additions

1. **Admin UI**: Manual update button in settings page
2. **Logging**: Track update checks and failures
3. **Beta Channel**: Separate branch for beta testers
4. **Rollback**: Ability to revert to previous version
5. **GitHub Actions**: Automated release creation on tag push

### Example: Manual Update Button

```php
// In admin settings page
$manager = \LS\Updates\GitHubUpdateManager::get_instance($repo_url, $plugin_file, $slug);
$last_check = $manager->get_last_checked_time();

echo '<p>Last checked: ' . esc_html($last_check) . '</p>';
echo '<button onclick="checkUpdates()">Check for Updates Now</button>';
```

## CI/CD Integration (Advanced)

See `docs/GITHUB_ACTIONS_DEPLOY.md` for automated release workflow.

## Resources

- **PUC Library**: https://github.com/YahnisElsts/plugin-update-checker
- **WordPress Plugin API**: https://developer.wordpress.org/plugins/plugin-basics/
- **GitHub Releases**: https://docs.github.com/en/repositories/releasing-projects-on-github

## Support

For issues related to:
- **PUC Library**: Report to YahnisElsts/plugin-update-checker
- **LeadStream Integration**: Report to shaunpalmer/LeadStream

---

**Last Updated**: January 15, 2026  
**Implementation**: 2-Pass Architecture ✅
