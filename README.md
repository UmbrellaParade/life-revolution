# Life Revolution

Umbrella Parade Life Revolution is a mobile-first budgeting and life-revolution tool for tracking expenses, fixed costs, loans, savings goals, side income, and strategy notes.

## Public And Private Use

- Public users use the GitHub Pages app at https://umbrellaparade.github.io/life-revolution/. Their data stays in their own browser.
- WordPress users with the `Life Revolution利用者` role use the same private app while keeping separate user-metadata ledgers.
- Administrators can install Life Revolution Family to combine two selected private ledgers in an admin-only household dashboard.
- The optional `[life_revolution]` shortcode is also public/browser-only and never reads WordPress admin data.

## Development

```bash
npm install
npm run dev
```

## Build

```bash
npm run build
```

## WordPress Plugin

Build the WordPress plugin package from the same React app:

```bash
npm run build:wp
```

The generated plugin folder is created at:

```text
wordpress-plugin/build/yutori-ledger
```

Build both the main and family plugins with:

```bash
npm run build:wp:all
```

The family plugin folder is created at:

```text
wordpress-family-plugin/build/life-revolution-family
```

The folder and legacy shortcode stay `yutori-ledger` / `[yutori_ledger]` for compatibility with existing WordPress installs. The user-facing plugin name and preferred shortcode are:

```text
[life_revolution]
```

The GitHub Pages app and public shortcode store data in the visitor's browser localStorage. Private WordPress browser caches are keyed by WordPress user ID, and the primary private copy remains in that user's WordPress metadata. Use the app's JSON export/import controls for additional backups.

