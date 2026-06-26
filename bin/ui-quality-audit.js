#!/usr/bin/env node
const fs = require('fs');
const path = require('path');
const zlib = require('zlib');

const root = path.resolve(__dirname, '..');
const args = new Set(process.argv.slice(2));
const write = args.has('--write');
const check = args.has('--check');
const jsonOnly = args.has('--json');
const outDir = path.join(root, 'docs', 'qa');
const jsonPath = path.join(outDir, 'ui-quality-current.json');
const reportPath = path.join(outDir, 'ui-quality-current.md');

const uiDirs = ['pages', 'includes', 'assets/js'];
const cssFiles = ['theme.css', 'assets/public/cloud.css'];
const languageFiles = ['cs', 'de', 'es', 'it'];
const productionExtensions = new Set(['.php', '.js', '.css']);
const allowedStyleFiles = [
  'includes/footer.php',
  'includes/mailer.php',
  'includes/email-functions.php',
  'includes/report-functions.php',
  'includes/modules/email/email-renderer.php',
  'pages/report-public.php',
];

const thresholds = {
  viewportFontClamp: 0,
  missingTranslationKeys: 0,
  uniqueFontSizes: 42,
  uniqueBorderRadii: 14,
  uniqueBoxShadows: 30,
  styleAttrs: 35,
  roundedUtilities: 35,
};

function walk(dir) {
  if (!fs.existsSync(dir)) return [];
  const entries = fs.readdirSync(dir, { withFileTypes: true });
  let files = [];
  for (const entry of entries) {
    const fullPath = path.join(dir, entry.name);
    if (entry.isDirectory()) {
      files = files.concat(walk(fullPath));
    } else {
      files.push(fullPath);
    }
  }
  return files;
}

function rel(file) {
  return path.relative(root, file).split(path.sep).join('/');
}

function read(file) {
  return fs.readFileSync(path.join(root, file), 'utf8');
}

function productionFiles() {
  return uiDirs
    .flatMap((dir) => walk(path.join(root, dir)))
    .filter((file) => productionExtensions.has(path.extname(file)))
    .filter((file) => !rel(file).startsWith('includes/lang/'));
}

function countMatches(content, regex) {
  return (content.match(regex) || []).length;
}

function styleValueAllowed(value) {
  const normalized = String(value || '').trim().replace(/\s+/g, ' ');
  if (normalized === '') return true;
  if (normalized.startsWith('--')) return true;
  if (/^width\s*:\s*<\?php/i.test(normalized)) return true;
  if (/^background(?:-color)?\s*:\s*<\?php/i.test(normalized)) return true;
  if (/^color\s*:\s*<\?php/i.test(normalized)) return true;
  if (/background-color:\s*<\?php[\s\S]*color:\s*<\?php/i.test(normalized)) return true;
  const declarations = normalized.split(';').map((part) => part.trim()).filter(Boolean);
  if (declarations.length > 0 && declarations.every((part) => part.startsWith('--'))) {
    return true;
  }
  if (/^display\s*:\s*none$/i.test(normalized)) {
    return true;
  }
  return false;
}

function countNonAllowlistedStyleAttrs(content, relativeFile) {
  if (allowedStyleFiles.includes(relativeFile)) {
    return 0;
  }

  const pattern = /\bstyle\s*=\s*(["'])(.*?)\1/g;
  let count = 0;
  let match;
  while ((match = pattern.exec(content)) !== null) {
    if (!styleValueAllowed(match[2])) {
      count += 1;
    }
  }
  return count;
}

function uniqueDeclarationValues(css, property) {
  const pattern = new RegExp(`${property}\\s*:\\s*([^;{}]+)`, 'gi');
  const values = new Set();
  let match;
  while ((match = pattern.exec(css)) !== null) {
    const value = String(match[1] || '').trim().replace(/\s+/g, ' ');
    if (value === '' || value.includes('var(--') || value === 'none' || value === 'none !important') {
      continue;
    }
    values.add(value);
  }
  return values;
}

function parsePhpLang(file) {
  const content = read(file);
  const entries = new Map();
  const pattern = /'((?:\\.|[^'\\])*)'\s*=>\s*'((?:\\.|[^'\\])*)'/g;
  let match;
  while ((match = pattern.exec(content)) !== null) {
    entries.set(match[1].replace(/\\'/g, "'"), match[2].replace(/\\'/g, "'"));
  }
  return entries;
}

function cssMetrics() {
  const css = cssFiles.map(read).join('\n');
  const rawBytes = cssFiles.reduce((sum, file) => sum + Buffer.byteLength(read(file)), 0);
  const gzipBytes = cssFiles.reduce((sum, file) => sum + zlib.gzipSync(read(file)).length, 0);
  return {
    uniqueFontSizes: uniqueDeclarationValues(css, 'font-size').size,
    uniqueBorderRadii: uniqueDeclarationValues(css, 'border-radius').size,
    uniqueBoxShadows: uniqueDeclarationValues(css, 'box-shadow').size,
    viewportFontClamp: countMatches(css, /font-size\s*:\s*clamp\(/g),
    cssRawBytes: rawBytes,
    cssGzipBytes: gzipBytes,
  };
}

function uiMetrics() {
  const files = productionFiles();
  let loc = 0;
  let styleAttrs = 0;
  let roundedUtilities = 0;
  let nonAllowlistedStyleAttrs = 0;

  for (const file of files) {
    const content = fs.readFileSync(file, 'utf8');
    const relative = rel(file);
    loc += content.split(/\r?\n/).length;
    const styleCount = countMatches(content, /\bstyle\s*=/g);
    styleAttrs += styleCount;
    roundedUtilities += countMatches(content, /\brounded-(?:sm|md|lg|xl|2xl|3xl|full)\b/g);
    nonAllowlistedStyleAttrs += countNonAllowlistedStyleAttrs(content, relative);
  }

  return {
    scannedFiles: files.length,
    productionLoc: loc,
    styleAttrs,
    nonAllowlistedStyleAttrs,
    roundedUtilities,
  };
}

function translationMetrics() {
  const en = parsePhpLang('includes/lang/en.php');
  const languages = {};
  let missingTotal = 0;

  for (const language of languageFiles) {
    const entries = parsePhpLang(`includes/lang/${language}.php`);
    const missing = [...en.keys()].filter((key) => !entries.has(key));
    const extra = [...entries.keys()].filter((key) => !en.has(key));
    missingTotal += missing.length;
    languages[language] = {
      keys: entries.size,
      missing: missing.length,
      extra: extra.length,
      missingSample: missing.slice(0, 12),
    };
  }

  return {
    sourceKeys: en.size,
    missingTotal,
    languages,
  };
}

function collectMetrics() {
  return {
    generatedAt: new Date().toISOString(),
    root,
    css: cssMetrics(),
    ui: uiMetrics(),
    translations: translationMetrics(),
    thresholds,
  };
}

function failuresFor(metrics) {
  const failures = [];
  const checks = [
    ['css.viewportFontClamp', metrics.css.viewportFontClamp, thresholds.viewportFontClamp],
    ['css.uniqueFontSizes', metrics.css.uniqueFontSizes, thresholds.uniqueFontSizes],
    ['css.uniqueBorderRadii', metrics.css.uniqueBorderRadii, thresholds.uniqueBorderRadii],
    ['css.uniqueBoxShadows', metrics.css.uniqueBoxShadows, thresholds.uniqueBoxShadows],
    ['ui.nonAllowlistedStyleAttrs', metrics.ui.nonAllowlistedStyleAttrs, thresholds.styleAttrs],
    ['ui.roundedUtilities', metrics.ui.roundedUtilities, thresholds.roundedUtilities],
    ['translations.missingTotal', metrics.translations.missingTotal, thresholds.missingTranslationKeys],
  ];

  for (const [name, value, max] of checks) {
    if (value > max) {
      failures.push(`${name} must be <= ${max}; got ${value}.`);
    }
  }

  return failures;
}

function markdown(metrics, failures) {
  const lines = [
    '# UI Quality Baseline',
    '',
    `Generated: ${metrics.generatedAt}`,
    '',
    '## Metrics',
    '',
    '| Area | Metric | Value | Target |',
    '| --- | --- | ---: | ---: |',
    `| CSS | unique font sizes | ${metrics.css.uniqueFontSizes} | <= ${thresholds.uniqueFontSizes} |`,
    `| CSS | unique radii | ${metrics.css.uniqueBorderRadii} | <= ${thresholds.uniqueBorderRadii} |`,
    `| CSS | unique shadows | ${metrics.css.uniqueBoxShadows} | <= ${thresholds.uniqueBoxShadows} |`,
    `| CSS | viewport font clamps | ${metrics.css.viewportFontClamp} | ${thresholds.viewportFontClamp} |`,
    `| CSS | raw bytes | ${metrics.css.cssRawBytes} | track down |`,
    `| CSS | gzip bytes | ${metrics.css.cssGzipBytes} | track down |`,
    `| UI | production LOC | ${metrics.ui.productionLoc} | track down |`,
    `| UI | style attributes | ${metrics.ui.styleAttrs} | track down |`,
    `| UI | non-allowlisted style attributes | ${metrics.ui.nonAllowlistedStyleAttrs} | <= ${thresholds.styleAttrs} |`,
    `| UI | rounded utilities | ${metrics.ui.roundedUtilities} | <= ${thresholds.roundedUtilities} |`,
    `| i18n | source keys | ${metrics.translations.sourceKeys} | parity source |`,
    `| i18n | missing keys total | ${metrics.translations.missingTotal} | ${thresholds.missingTranslationKeys} |`,
    '',
    '## Translation Parity',
    '',
    '| Language | Keys | Missing | Extra |',
    '| --- | ---: | ---: | ---: |',
  ];

  for (const [language, data] of Object.entries(metrics.translations.languages)) {
    lines.push(`| ${language} | ${data.keys} | ${data.missing} | ${data.extra} |`);
  }

  lines.push('', '## Gate', '');
  if (failures.length === 0) {
    lines.push('PASS');
  } else {
    for (const failure of failures) {
      lines.push(`- ${failure}`);
    }
  }

  return lines.join('\n') + '\n';
}

const metrics = collectMetrics();
const failures = failuresFor(metrics);
const output = {
  status: failures.length === 0 ? 'passed' : 'failed',
  metrics,
  failures,
};

if (write) {
  fs.mkdirSync(outDir, { recursive: true });
  fs.writeFileSync(jsonPath, JSON.stringify(output, null, 2) + '\n');
  fs.writeFileSync(reportPath, markdown(metrics, failures));
}

if (jsonOnly) {
  process.stdout.write(JSON.stringify(output, null, 2) + '\n');
} else {
  console.log(`UI quality audit: ${output.status}`);
  console.log(`- CSS values: font ${metrics.css.uniqueFontSizes}, radii ${metrics.css.uniqueBorderRadii}, shadows ${metrics.css.uniqueBoxShadows}`);
  console.log(`- CSS payload: ${metrics.css.cssRawBytes} raw / ${metrics.css.cssGzipBytes} gzip bytes`);
  console.log(`- UI local styles: ${metrics.ui.nonAllowlistedStyleAttrs} non-allowlisted, rounded utilities ${metrics.ui.roundedUtilities}`);
  console.log(`- translations missing total: ${metrics.translations.missingTotal}`);
  for (const failure of failures) {
    console.error(`- ${failure}`);
  }
  if (write) {
    console.log(`- wrote ${rel(jsonPath)} and ${rel(reportPath)}`);
  }
}

if (check && failures.length > 0) {
  process.exit(1);
}
