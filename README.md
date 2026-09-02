# Life Revolution

Umbrella Parade Life Revolution is a mobile-first budgeting and life-revolution tool for tracking expenses, fixed costs, loans, savings goals, side income, and strategy notes.

## Public And Private Use

- Public users use the GitHub Pages app at https://umbrellaparade.github.io/life-revolution/. Their data stays in their own browser.
- The site administrator uses the Life Revolution screen in WordPress admin. Private data is stored in that administrator's WordPress user metadata.
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

The folder and legacy shortcode stay `yutori-ledger` / `[yutori_ledger]` for compatibility with existing WordPress installs. The user-facing plugin name and preferred shortcode are:

```text
[life_revolution]
```

The GitHub Pages app and public shortcode store data in the visitor's browser localStorage. Use the app's JSON export/import controls before changing devices, browsers, or clearing browser data.

