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

function jsAssetMap(items) {
  return new Map(items.map((item) => [path.basename(item.path), item]));
}

function buildRouteBundles(items) {
  const assets = jsAssetMap(items);
  const common = [
    'app-api-client.js',
    'app-contract-shell.js',
    'page-transitions.js',
    'image-preview.js',
    'app-footer.js',
    'app-header.js',
    'shortcuts.js'
  ];
  const routes = {
    shell: [],
    work: ['work-dashboard.js'],
    tickets: ['kanban.js', 'ticket-list.js'],
    'ticket-detail': [
      'chip-select.js',
      'rich-text-editor.js',
      'quill-image-upload.js',
      'attachment-paste-drop.js',
      'autosave.js',
      'upload-preview.js',
      'ticket-detail.js'
    ],
    'new-ticket': [
      'chip-select.js',
      'rich-text-editor.js',
      'quill-image-upload.js',
      'attachment-paste-drop.js',
      'autosave.js',
      'upload-preview.js'
    ],
    reports: ['chip-select.js', 'report-page.js', 'report-billing-review.js', 'report-time-delete.js']
  };

  return Object.entries(routes).map(([route, routeAssets]) => {
    const names = [...new Set([...common, ...routeAssets])];
    const resolved = names.map((name) => assets.get(name)).filter(Boolean);
    return {
      route,
      assets: resolved.map((item) => item.path),
      gzipBytes: sum(resolved, 'gzipBytes')
    };
  });
}

function assert(condition, message, failures) {
  if (!condition) {
    failures.push(message);
  }
}

const before = readJson(beforePath);
const current = fs.existsSync(currentPath) ? readJson(currentPath) : null;
const cssAssets = [
  gzipBytes('assets/css/theme.min.css'),
  gzipBytes('assets/public/cloud.css')
];
const jsAssets = listJsAssets();
const cssGzipBytes = sum(cssAssets, 'gzipBytes');
const jsGzipBytes = sum(jsAssets, 'gzipBytes');
const jsRouteBundles = buildRouteBundles(jsAssets);
const largestJsRoute = [...jsRouteBundles].sort((a, b) => b.gzipBytes - a.gzipBytes)[0];
const beforeCssGzip = Number(before.metrics?.css?.cssGzipBytes || 0);
const cssTarget10 = beforeCssGzip > 0 ? Math.floor(beforeCssGzip * 0.9) : 0;
const largestJs = [...jsAssets].sort((a, b) => b.gzipBytes - a.gzipBytes)[0];
const failures = [];
const warnings = [];

assert(beforeCssGzip > 0, 'Missing before CSS gzip baseline.', failures);
assert(cssGzipBytes <= beforeCssGzip, `CSS gzip regressed: ${cssGzipBytes} > ${beforeCssGzip}.`, failures);
assert(largestJs.gzipBytes <= 16000, `Largest JS asset is too large: ${largestJs.path} ${largestJs.gzipBytes} gzip bytes.`, failures);
assert(largestJsRoute.gzipBytes <= 65000, `Largest route JS payload is too large: ${largestJsRoute.route} ${largestJsRoute.gzipBytes} gzip bytes.`, failures);

assert(
  cssTarget10 <= 0 || cssGzipBytes <= cssTarget10,
  `CSS gzip has not reached the required -10% target: ${cssGzipBytes} > ${cssTarget10}.`,
  failures
);

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
    totalAssetGzipBytes: jsGzipBytes,
    routeBundles: jsRouteBundles,
    largestRoute: largestJsRoute,
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
console.log(`- JS route gzip: ${largestJsRoute.gzipBytes} bytes on ${largestJsRoute.route}; largest asset ${largestJs.path} ${largestJs.gzipBytes} bytes`);
console.log(`- JS asset inventory: ${jsGzipBytes} gzip bytes across ${jsAssets.length} route-split files`);
for (const warning of warnings) {
  console.log(`- warning: ${warning}`);
}
