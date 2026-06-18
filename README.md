# Life Revolution

Umbrella Parade Life Revolution is a mobile-first budgeting and life-revolution tool for tracking expenses, fixed costs, loans, savings goals, side income, and strategy notes.

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

Current data is stored in the visitor's browser localStorage. Use the app's JSON export/import controls before changing devices, browsers, or clearing browser data.

