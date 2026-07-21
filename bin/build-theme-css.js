#!/usr/bin/env node

const fs = require('fs');
const path = require('path');
const CleanCSS = require('clean-css');

const root = path.resolve(__dirname, '..');
const sourcePath = path.join(root, 'theme.css');
const outputPath = path.join(root, 'assets/css/theme.min.css');
const source = fs.readFileSync(sourcePath, 'utf8');
const result = new CleanCSS({ level: 2, rebase: false, returnPromise: false }).minify(source);

if (result.errors.length > 0) {
  for (const error of result.errors) console.error(error);
  process.exit(1);
}

const output = `${result.styles}\n`;
if (process.argv.includes('--check')) {
  const current = fs.existsSync(outputPath) ? fs.readFileSync(outputPath, 'utf8') : '';
  if (current !== output) {
    console.error('assets/css/theme.min.css is stale. Run npm run build:css.');
    process.exit(1);
  }
  console.log('assets/css/theme.min.css matches theme.css.');
  process.exit(0);
}

fs.writeFileSync(outputPath, output);
console.log(`Built assets/css/theme.min.css (${Buffer.byteLength(output)} bytes).`);
