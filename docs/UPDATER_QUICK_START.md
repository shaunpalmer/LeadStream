# GitHub Self-Updater - Quick Start

## What is This?

The LeadStream plugin now includes automatic update functionality that delivers updates directly from GitHub, bypassing the WordPress.org review queue.

## How It Works

1. **For Users**: When a new version is released on GitHub, WordPress automatically detects it and shows an "Update Available" notification in the admin dashboard.

2. **For Developers**: Simply tag a new version and push to GitHub. The update system and GitHub Actions handle everything else.

## Usage

### For Plugin Users

No configuration needed! Updates appear automatically in:
- Dashboard → Updates
- Plugins → Installed Plugins (update notification)

Click "Update Now" and WordPress handles the rest.

### For Developers

**To release a new version:**

```bash
# 1. Update version in leadstream-analytics-injector.php
# Change: Version: 2.12.2
# To:     Version: 2.12.3

# 2. Commit your changes
git add .
git commit -m "Release v2.12.3 - Add new features"

# 3. Create and push tag
git tag v2.12.3
git push origin main --tags
```

**That's it!** GitHub Actions will:
- Verify version matches tag
- Create plugin ZIP (excluding dev files)
- Publish GitHub Release
- Notify all WordPress installations

## Architecture

```
WordPress Plugin
├── plugin-update-checker/          ← Third-party library (PUC)
└── includes/Updates/
    ├── Updater.php                 ← Bootstrap (initializes system)
    └── GitHubUpdateManager.php     ← Singleton (manages updates)
```

### Why Singleton?

Prevents multiple GitHub API calls per page load, avoiding rate limits.

## Configuration

**GitHub Repository**: `https://github.com/shaunpalmer/LeadStream/`

To change, edit `includes/Updates/Updater.php`:
```php
$repo_url = 'https://github.com/your-username/your-repo/';
```

## Private Repositories

For private repos, add authentication:

```php
$manager = \LS\Updates\GitHubUpdateManager::get_instance($repo_url, $plugin_file, $slug);
$manager->set_auth_token('ghp_yourGitHubToken');
```

## Troubleshooting

**Updates not appearing?**
1. Check GitHub release is published (not draft)
2. Verify version number matches tag
3. Wait 12 hours or force check: `wp plugin update leadstream-analytics-injector`

**Rate limiting?**
- Unauthenticated: 60 requests/hour
- With token: 5,000 requests/hour
- Solution: Add authentication token

## Documentation

See full documentation: `docs/GITHUB_UPDATER_GUIDE.md`

## Security

- All communication uses HTTPS
- ZIP integrity verified by PUC library
- No credentials stored in database
- GitHub API rate limiting respected

## Credits

- Plugin Update Checker by [Yahnis Elsts](https://github.com/YahnisElsts/plugin-update-checker)
- Implementation by LeadStream Team

---

**Last Updated**: January 2026
