import fs from 'node:fs/promises'
import path from 'node:path'
import { fileURLToPath } from 'node:url'
import postcss from 'postcss'

const rootDir = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..')
const distDir = path.join(rootDir, 'dist')
const pluginSourceDir = path.join(rootDir, 'wordpress-plugin')
const outputDir = path.join(pluginSourceDir, 'build', 'yutori-ledger')
const scopeSelector = '.yutori-ledger-root'

async function exists(target) {
  try {
    await fs.access(target)
    return true
  } catch {
    return false
  }
}

async function copyDirectory(source, target) {
  await fs.mkdir(target, { recursive: true })
  const entries = await fs.readdir(source, { withFileTypes: true })

  for (const entry of entries) {
    const sourcePath = path.join(source, entry.name)
    const targetPath = path.join(target, entry.name)

    if (entry.isDirectory()) {
      await copyDirectory(sourcePath, targetPath)
    } else if (entry.isFile()) {
      await fs.copyFile(sourcePath, targetPath)
    }
  }
}

function prefixSelector(selector) {
  const trimmed = selector.trim()

  if (!trimmed || trimmed.startsWith(scopeSelector)) {
    return trimmed
  }

  if (trimmed === ':root' || trimmed === 'html' || trimmed === 'body') {
    return scopeSelector
  }

  if (trimmed === '*') {
    return `${scopeSelector} *`
  }

  return `${scopeSelector} ${trimmed}`
}

async function scopeCssAssets() {
  const assetsDir = path.join(outputDir, 'assets')
  const assets = await fs.readdir(assetsDir)
  const cssAssets = assets.filter((asset) => /^index-.*\.css$/.test(asset))

  for (const cssAsset of cssAssets) {
    const cssPath = path.join(assetsDir, cssAsset)
    const root = postcss.parse(await fs.readFile(cssPath, 'utf8'))

    root.walkRules((rule) => {
      if (rule.parent?.type === 'atrule' && /keyframes$/i.test(rule.parent.name)) {
        return
      }

      rule.selectors = rule.selectors.map(prefixSelector)
    })

    await fs.writeFile(cssPath, root.toString(), 'utf8')
  }
}

async function buildPlugin() {
  if (!(await exists(path.join(distDir, 'index.html')))) {
    throw new Error('Missing dist/index.html. Run npm run build first.')
  }

  await fs.rm(outputDir, { recursive: true, force: true })
  await fs.mkdir(outputDir, { recursive: true })

  await fs.copyFile(
    path.join(pluginSourceDir, 'yutori-ledger.php'),
    path.join(outputDir, 'yutori-ledger.php'),
  )
  await fs.copyFile(
    path.join(pluginSourceDir, 'README.txt'),
    path.join(outputDir, 'README.txt'),
  )

  const distEntries = await fs.readdir(distDir, { withFileTypes: true })

  for (const entry of distEntries) {
    if (entry.name === 'index.html') continue

    const sourcePath = path.join(distDir, entry.name)
    const targetPath = path.join(outputDir, entry.name)

    if (entry.isDirectory()) {
      await copyDirectory(sourcePath, targetPath)
    } else if (entry.isFile()) {
      await fs.copyFile(sourcePath, targetPath)
    }
  }

  await scopeCssAssets()

  console.log(`WordPress plugin built at ${path.relative(rootDir, outputDir)}`)
}

await buildPlugin()
