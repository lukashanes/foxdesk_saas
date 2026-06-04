#!/usr/bin/env node

const fs = require('fs');
const path = require('path');

const root = path.resolve(__dirname, '..');
const scanRoots = ['pages', 'includes'];
const baselinePath = path.join(root, 'docs', 'csp-ui-baseline.json');

const priorityFiles = [
  'pages/work.php',
  'pages/inbox.php',
  'pages/tickets.php',
  'pages/ticket-detail.php',
  'pages/new-ticket.php',
  'pages/client.php',
  'pages/dashboard.php',
  'pages/admin/reports.php',
  'pages/admin/settings.php',
  'pages/billing.php',
  'pages/platform.php',
  'pages/login.php',
  'pages/signup.php',
  'pages/forgot-password.php',
  'pages/reset-password.php'
];

function walk(dir) {
  const out = [];
  for (const entry of fs.readdirSync(dir, { withFileTypes: true })) {
    const full = path.join(dir, entry.name);
    if (entry.isDirectory()) {
      out.push(...walk(full));
    } else if (entry.isFile() && entry.name.endsWith('.php')) {
      out.push(full);
    }
  }
  return out;
}

function countMatches(source, regex) {
  return [...source.matchAll(regex)].length;
}

function hasUnversionedThemeLink(source) {
  return /<link\b[^>]*href=["']theme\.css["'][^>]*>/i.test(source)
    || /<link\b[^>]*href=["'][^"']*theme\.css["'][^>]*>/i.test(source);
}

function hasUnversionedTailwindLink(source) {
  return /<link\b[^>]*href=["']tailwind\.min\.css["'][^>]*>/i.test(source)
    || /<link\b[^>]*href=["'][^"']*tailwind\.min\.css["'][^>]*>/i.test(source);
}

function classifyRisk(record) {
  if (record.styleBlocks > 0) return 'critical';
  if (record.inlineStyles >= 25) return 'high';
  if (record.inlineStyles > 0 || record.unversionedThemeLinks > 0) return 'medium';
  return 'low';
}

function audit() {
  const files = scanRoots.flatMap(scanRoot => walk(path.join(root, scanRoot)));
  const records = files.map(file => {
    const relative = path.relative(root, file);
    const source = fs.readFileSync(file, 'utf8');
    const record = {
      file: relative,
      styleBlocks: countMatches(source, /<style\b[^>]*>/gi),
      inlineStyles: countMatches(source, /\sstyle\s*=\s*["']/gi),
      unversionedThemeLinks: hasUnversionedThemeLink(source) ? 1 : 0,
      unversionedTailwindLinks: hasUnversionedTailwindLink(source) ? 1 : 0,
      isPriority: priorityFiles.includes(relative)
    };
    record.risk = classifyRisk(record);
    return record;
  }).filter(record =>
    record.styleBlocks > 0 ||
    record.inlineStyles > 0 ||
    record.unversionedThemeLinks > 0 ||
    record.unversionedTailwindLinks > 0
  );

  records.sort((a, b) => {
    const riskOrder = { critical: 0, high: 1, medium: 2, low: 3 };
    return riskOrder[a.risk] - riskOrder[b.risk]
      || Number(b.isPriority) - Number(a.isPriority)
      || b.styleBlocks - a.styleBlocks
      || b.inlineStyles - a.inlineStyles
      || a.file.localeCompare(b.file);
  });

  const totals = records.reduce((sum, record) => {
    sum.files += 1;
    sum.styleBlocks += record.styleBlocks;
    sum.inlineStyles += record.inlineStyles;
    sum.unversionedThemeLinks += record.unversionedThemeLinks;
    sum.unversionedTailwindLinks += record.unversionedTailwindLinks;
    sum.priorityFiles += record.isPriority ? 1 : 0;
    sum.risk[record.risk] += 1;
    return sum;
  }, {
    files: 0,
    styleBlocks: 0,
    inlineStyles: 0,
    unversionedThemeLinks: 0,
    unversionedTailwindLinks: 0,
    priorityFiles: 0,
    risk: { critical: 0, high: 0, medium: 0, low: 0 }
  });

  return {
    generatedAt: new Date().toISOString(),
    scanRoots,
    priorityFiles,
    totals,
    records
  };
}

function printSummary(result) {
  console.log(`CSP UI audit: ${result.totals.files} affected files`);
  console.log(`style blocks: ${result.totals.styleBlocks}`);
  console.log(`inline style attributes: ${result.totals.inlineStyles}`);
  console.log(`unversioned theme links: ${result.totals.unversionedThemeLinks}`);
  console.log(`unversioned tailwind links: ${result.totals.unversionedTailwindLinks}`);
  console.log(`priority files affected: ${result.totals.priorityFiles}`);
  console.log('');
  console.log('Top affected files:');
  for (const record of result.records.slice(0, 20)) {
    console.log([
      record.risk.padEnd(8),
      record.isPriority ? 'priority' : '        ',
      String(record.styleBlocks).padStart(2),
      'style blocks,',
      String(record.inlineStyles).padStart(3),
      'inline styles,',
      String(record.unversionedThemeLinks).padStart(1),
      'theme links,',
      record.file
    ].join(' '));
  }
}

function writeBaseline(result) {
  const baseline = {
    generatedAt: result.generatedAt,
    scanRoots: result.scanRoots,
    priorityFiles: result.priorityFiles,
    totals: result.totals,
    records: Object.fromEntries(result.records.map(record => [
      record.file,
      {
        styleBlocks: record.styleBlocks,
        inlineStyles: record.inlineStyles,
        unversionedThemeLinks: record.unversionedThemeLinks,
        unversionedTailwindLinks: record.unversionedTailwindLinks
      }
    ]))
  };
  fs.writeFileSync(baselinePath, `${JSON.stringify(baseline, null, 2)}\n`);
}

function compareToBaseline(result) {
  if (!fs.existsSync(baselinePath)) {
    throw new Error(`Missing baseline: ${path.relative(root, baselinePath)}`);
  }
  const baseline = JSON.parse(fs.readFileSync(baselinePath, 'utf8'));
  const current = new Map(result.records.map(record => [record.file, record]));
  const failures = [];

  for (const record of result.records) {
    const allowed = baseline.records[record.file];
    if (!allowed) {
      failures.push(`${record.file}: new CSP-risky file with inline/page styles`);
      continue;
    }
    for (const key of ['styleBlocks', 'inlineStyles', 'unversionedThemeLinks', 'unversionedTailwindLinks']) {
      if (record[key] > allowed[key]) {
        failures.push(`${record.file}: ${key} increased from ${allowed[key]} to ${record[key]}`);
      }
    }
  }

  for (const file of Object.keys(baseline.records)) {
    if (!current.has(file)) continue;
    const currentRecord = current.get(file);
    const allowed = baseline.records[file];
    for (const key of ['styleBlocks', 'inlineStyles', 'unversionedThemeLinks', 'unversionedTailwindLinks']) {
      if (currentRecord[key] > allowed[key]) {
        failures.push(`${file}: ${key} increased from ${allowed[key]} to ${currentRecord[key]}`);
      }
    }
  }

  if (failures.length > 0) {
    throw new Error(`CSP UI audit regression:\n${failures.join('\n')}`);
  }
}

const args = new Set(process.argv.slice(2));
const result = audit();

if (args.has('--json')) {
  console.log(JSON.stringify(result, null, 2));
} else {
  printSummary(result);
}

if (args.has('--write-baseline')) {
  writeBaseline(result);
  console.log(`\nWrote ${path.relative(root, baselinePath)}`);
}

if (args.has('--check-baseline')) {
  compareToBaseline(result);
  console.log('\nCSP UI baseline check passed.');
}

