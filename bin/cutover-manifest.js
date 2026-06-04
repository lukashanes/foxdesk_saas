#!/usr/bin/env node

const crypto = require('crypto');
const fs = require('fs');
const path = require('path');

const DEFAULT_FILES = [
  'result.json',
  'report.md',
  'cutover-preflight.json',
  'cutover-preflight.md',
  'cutover-postcheck.json',
  'cutover-postcheck.md',
  'cutover-status.json',
  'cutover-status.md',
];

function usage() {
  return [
    'Usage: node bin/cutover-manifest.js --dir=/path/to/foxdesk-cutover-gate [--output-dir=/path]',
    '',
    'Builds a checksum manifest for cutover evidence, preflight, postcheck, status, and screenshots.',
  ].join('\n');
}

function parseArgs(argv) {
  const options = {
    dir: process.env.FOXDESK_CUTOVER_DIR || '',
    outputDir: process.env.FOXDESK_CUTOVER_MANIFEST_DIR || '',
  };

  for (const arg of argv) {
    if (arg === '--help' || arg === '-h') {
      options.help = true;
      continue;
    }
    if (arg.startsWith('--dir=')) {
      options.dir = arg.slice('--dir='.length);
      continue;
    }
    if (arg.startsWith('--output-dir=')) {
      options.outputDir = arg.slice('--output-dir='.length);
      continue;
    }
    if (!arg.startsWith('--') && !options.dir) {
      options.dir = arg;
      continue;
    }
    throw new Error(`Unknown argument: ${arg}`);
  }

  return options;
}

function sha256(filePath) {
  const hash = crypto.createHash('sha256');
  hash.update(fs.readFileSync(filePath));
  return hash.digest('hex');
}

function listScreenshotFiles(dir) {
  if (!fs.existsSync(dir)) {
    return [];
  }
  return fs.readdirSync(dir)
    .filter((name) => /\.(png|jpe?g|webp)$/i.test(name))
    .sort();
}

function fileEntry(dir, name) {
  const filePath = path.join(dir, name);
  if (!fs.existsSync(filePath)) {
    return {
      name,
      path: filePath,
      exists: false,
      size: 0,
      sha256: '',
    };
  }
  const stat = fs.statSync(filePath);
  return {
    name,
    path: filePath,
    exists: true,
    size: stat.size,
    sha256: stat.isFile() ? sha256(filePath) : '',
  };
}

function readJsonIfExists(dir, name) {
  const filePath = path.join(dir, name);
  if (!fs.existsSync(filePath)) {
    return null;
  }
  try {
    return JSON.parse(fs.readFileSync(filePath, 'utf8'));
  } catch (_) {
    return null;
  }
}

function buildManifest(dir) {
  const resolvedDir = path.resolve(dir);
  if (!fs.existsSync(resolvedDir) || !fs.statSync(resolvedDir).isDirectory()) {
    throw new Error(`Cutover directory does not exist: ${resolvedDir}`);
  }

  const status = readJsonIfExists(resolvedDir, 'cutover-status.json');
  const postcheck = readJsonIfExists(resolvedDir, 'cutover-postcheck.json');
  const preflight = readJsonIfExists(resolvedDir, 'cutover-preflight.json');
  const result = readJsonIfExists(resolvedDir, 'result.json');
  const names = [...DEFAULT_FILES, ...listScreenshotFiles(resolvedDir)];
  const files = names.map((name) => fileEntry(resolvedDir, name));
  const presentFiles = files.filter((file) => file.exists);
  const missingCoreFiles = files
    .filter((file) => DEFAULT_FILES.includes(file.name) && !file.exists)
    .map((file) => file.name);

  const manifest = {
    generatedAt: new Date().toISOString(),
    directory: resolvedDir,
    lifecycle: {
      gateDecision: result?.holdDecision || '',
      preflightDecision: preflight?.decision || '',
      postcheckDecision: postcheck?.decision || '',
      statusPhase: status?.phase || '',
      statusDecision: status?.decision || '',
    },
    summary: {
      filesPresent: presentFiles.length,
      screenshotsPresent: presentFiles.filter((file) => /\.(png|jpe?g|webp)$/i.test(file.name)).length,
      missingCoreFiles,
      totalBytes: presentFiles.reduce((sum, file) => sum + file.size, 0),
    },
    files,
  };

  const manifestHash = crypto.createHash('sha256');
  manifestHash.update(JSON.stringify({
    lifecycle: manifest.lifecycle,
    summary: manifest.summary,
    files: manifest.files.map((file) => ({
      name: file.name,
      exists: file.exists,
      size: file.size,
      sha256: file.sha256,
    })),
  }, null, 2));
  manifest.manifestSha256 = manifestHash.digest('hex');

  return manifest;
}

function renderMarkdown(manifest) {
  const lines = [
    '# FoxDesk Cutover Manifest',
    '',
    `Generated: ${manifest.generatedAt}`,
    `Directory: ${manifest.directory}`,
    `Manifest SHA-256: ${manifest.manifestSha256}`,
    '',
    '## Lifecycle',
    '',
    `- Gate decision: ${manifest.lifecycle.gateDecision || '(missing)'}`,
    `- Preflight decision: ${manifest.lifecycle.preflightDecision || '(missing)'}`,
    `- Postcheck decision: ${manifest.lifecycle.postcheckDecision || '(missing)'}`,
    `- Status phase: ${manifest.lifecycle.statusPhase || '(missing)'}`,
    `- Status decision: ${manifest.lifecycle.statusDecision || '(missing)'}`,
    '',
    '## Summary',
    '',
    `- Files present: ${manifest.summary.filesPresent}`,
    `- Screenshots present: ${manifest.summary.screenshotsPresent}`,
    `- Total bytes: ${manifest.summary.totalBytes}`,
    `- Missing core files: ${manifest.summary.missingCoreFiles.length > 0 ? manifest.summary.missingCoreFiles.join(', ') : 'none'}`,
    '',
    '## Files',
    '',
  ];

  for (const file of manifest.files) {
    lines.push(`- ${file.exists ? '[x]' : '[ ]'} ${file.name} (${file.size} bytes) ${file.sha256 || ''}`.trim());
  }

  return `${lines.join('\n')}\n`;
}

function writeManifest(manifest, outputDir) {
  fs.mkdirSync(outputDir, { recursive: true });
  const jsonPath = path.join(outputDir, 'cutover-manifest.json');
  const reportPath = path.join(outputDir, 'cutover-manifest.md');
  fs.writeFileSync(jsonPath, `${JSON.stringify(manifest, null, 2)}\n`);
  fs.writeFileSync(reportPath, renderMarkdown(manifest));
  return { jsonPath, reportPath };
}

function main() {
  try {
    const options = parseArgs(process.argv.slice(2));
    if (options.help) {
      console.log(usage());
      return 0;
    }
    if (!options.dir) {
      throw new Error('Missing cutover directory.');
    }

    const dir = path.resolve(options.dir);
    const outputDir = path.resolve(options.outputDir || dir);
    const manifest = buildManifest(dir);
    const output = writeManifest(manifest, outputDir);
    console.log(JSON.stringify({ ...manifest, output }, null, 2));
    return manifest.summary.missingCoreFiles.length === 0 ? 0 : 1;
  } catch (error) {
    console.error(error.message);
    console.error('');
    console.error(usage());
    return 1;
  }
}

if (require.main === module) {
  process.exitCode = main();
}

module.exports = {
  parseArgs,
  buildManifest,
  renderMarkdown,
  writeManifest,
};
