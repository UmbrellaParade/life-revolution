# Yutori Ledger

スマホで支出、固定費、ローン返済をまとめて見るためのPWAです。

## 方針

- 支出は手入力で素早く記録する
- 月を切り替えて、来月以降の支出入力とローン残高の見通しを確認する
- ローンごとに残高、手数料、通常返済、追加返済を分けて管理する
- 固定費は必要に応じて関連ローンへ紐づける
- 登録した支出、固定費、ローンは一覧からそのまま編集する
- メルカリやカードのログイン情報は保存しない
- 入力データはブラウザのローカルストレージに保存する
- 個人情報は公開リポジトリに置かず、JSONで端末ごとに読み込む

## 開発

```bash
npm install
npm run dev
```

## ビルド

```bash
npm run build
```

## WordPress plugin

Build the WordPress plugin package from the same React app:

```bash
npm run build:wp
```

The generated plugin folder is created at:

```text
wordpress-plugin/build/yutori-ledger
```

Use the shortcode below in WordPress:

```text
[yutori_ledger]
```

When changing the app, keep the GitHub Pages version and the WordPress plugin version in sync by rebuilding both from this repository.
