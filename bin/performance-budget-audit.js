#!/usr/bin/env node

const fs = require('fs');
const path = require('path');
const zlib = require('zlib');

const root = path.resolve(__dirname, '..');
const beforePath = path.join(root, 'docs/qa/ui-quality-before.json');
const currentPath = path.join(root, 'docs/qa/ui-quality-current.json');

function readJson(file) {
  return JSON.parse(fs.readFileSync(file, 'utf8'));
}

function gzipBytes(relativePath) {
  const filePath = path.join(root, relativePath);
  const contents = fs.readFileSync(filePath);
  return {
    path: relativePath,
    rawBytes: contents.length,
    gzipBytes: zlib.gzipSync(contents).length
  };
}

function listJsAssets() {
  const dir = path.join(root, 'assets/js');
  return fs.readdirSync(dir)
    .filter((file) => file.endsWith('.js'))
    .sort()
    .map((file) => gzipBytes(`assets/js/${file}`));
}

function sum(items, key) {
  return items.reduce((total, item) => total + item[key], 0);
}

function assert(condition, message, failures) {
  if (!condition) {
    failures.push(message);
  }
}

const before = readJson(beforePath);
const current = fs.existsSync(currentPath) ? readJson(currentPath) : null;
const cssAssets = [
  gzipBytes('theme.css'),
  gzipBytes('assets/public/cloud.css')
];
const jsAssets = listJsAssets();
const cssGzipBytes = sum(cssAssets, 'gzipBytes');
const jsGzipBytes = sum(jsAssets, 'gzipBytes');
const beforeCssGzip = Number(before.metrics?.css?.cssGzipBytes || 0);
const cssTarget10 = beforeCssGzip > 0 ? Math.floor(beforeCssGzip * 0.9) : 0;
const largestJs = [...jsAssets].sort((a, b) => b.gzipBytes - a.gzipBytes)[0];
const failures = [];
const warnings = [];

assert(beforeCssGzip > 0, 'Missing before CSS gzip baseline.', failures);
assert(cssGzipBytes <= beforeCssGzip, `CSS gzip regressed: ${cssGzipBytes} > ${beforeCssGzip}.`, failures);
assert(largestJs.gzipBytes <= 16000, `Largest JS asset is too large: ${largestJs.path} ${largestJs.gzipBytes} gzip bytes.`, failures);
assert(jsGzipBytes <= 65000, `Total JS gzip payload is too large: ${jsGzipBytes} gzip bytes.`, failures);

if (cssTarget10 > 0 && cssGzipBytes > cssTarget10) {
  warnings.push(`CSS gzip is stable but has not reached the -10% target yet: ${cssGzipBytes} > ${cssTarget10}.`);
}

if (current && current.status !== 'passed') {
  failures.push('Current UI quality audit is not passing.');
}

const report = {
  status: failures.length === 0 ? 'passed' : 'failed',
  generatedAt: new Date().toISOString(),
  css: {
    assets: cssAssets,
    gzipBytes: cssGzipBytes,
    beforeGzipBytes: beforeCssGzip,
    target10PercentGzipBytes: cssTarget10,
    gzipDeltaBytes: beforeCssGzip ? cssGzipBytes - beforeCssGzip : null,
    gzipDeltaPercent: beforeCssGzip ? Number((((cssGzipBytes - beforeCssGzip) / beforeCssGzip) * 100).toFixed(2)) : null
  },
  js: {
    assets: jsAssets,
    gzipBytes: jsGzipBytes,
    largestAsset: largestJs
  },
  warnings,
  failures
};

const args = new Set(process.argv.slice(2));
if (args.has('--write')) {
  const outPath = path.join(root, 'docs/qa/performance-budget-current.json');
  fs.writeFileSync(outPath, JSON.stringify(report, null, 2) + '\n');
}

if (failures.length > 0) {
  console.error('Performance budget: failed');
  for (const failure of failures) {
    console.error(`- ${failure}`);
  }
  process.exit(1);
}

console.log('Performance budget: passed');
console.log(`- CSS gzip: ${cssGzipBytes} bytes (baseline ${beforeCssGzip}, target ${cssTarget10})`);
console.log(`- JS gzip: ${jsGzipBytes} bytes; largest ${largestJs.path} ${largestJs.gzipBytes} bytes`);
for (const warning of warnings) {
  console.log(`- warning: ${warning}`);
}
