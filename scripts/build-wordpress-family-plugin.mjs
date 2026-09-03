import fs from 'node:fs/promises'
import path from 'node:path'
import { fileURLToPath } from 'node:url'

const rootDir = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..')
const sourceDir = path.join(rootDir, 'wordpress-family-plugin')
const outputDir = path.join(sourceDir, 'build', 'life-revolution-family')
const files = ['life-revolution-family.php', 'README.txt']

await fs.rm(outputDir, { recursive: true, force: true })
await fs.mkdir(outputDir, { recursive: true })

for (const file of files) {
  await fs.copyFile(path.join(sourceDir, file), path.join(outputDir, file))
}

console.log(`WordPress family plugin built at ${path.relative(rootDir, outputDir)}`)
