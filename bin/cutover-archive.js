#!/usr/bin/env node

const crypto = require('crypto');
const fs = require('fs');
const path = require('path');
const { spawnSync } = require('child_process');
const { buildManifest, writeManifest } = require('./cutover-manifest.js');

function usage() {
  return [
    'Usage: node bin/cutover-archive.js --dir=/path/to/foxdesk-cutover-gate [--output-dir=/path] [--archive-name=name.tar.gz] [--allow-missing-core]',
    '',
    'Creates a tar.gz archive and sha256 file for the cutover audit evidence bundle.',
  ].join('\n');
}

function parseArgs(argv) {
  const options = {
    dir: process.env.FOXDESK_CUTOVER_DIR || '',
    outputDir: process.env.FOXDESK_CUTOVER_ARCHIVE_DIR || '',
    archiveName: process.env.FOXDESK_CUTOVER_ARCHIVE_NAME || '',
    allowMissingCore: process.env.FOXDESK_CUTOVER_ALLOW_MISSING_CORE === '1',
  };

  for (const arg of argv) {
    if (arg === '--help' || arg === '-h') {
      options.help = true;
      continue;
    }
    if (arg === '--allow-missing-core') {
      options.allowMissingCore = true;
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
    if (arg.startsWith('--archive-name=')) {
      options.archiveName = arg.slice('--archive-name='.length);
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

function safeArchiveName(name) {
  const fallback = `foxdesk-cutover-${new Date().toISOString().replace(/[:.]/g, '-')}.tar.gz`;
  const raw = name || fallback;
  const base = path.basename(raw).replace(/[^a-zA-Z0-9._-]/g, '-');
  return base.endsWith('.tar.gz') ? base : `${base}.tar.gz`;
}

function sha256(filePath) {
  const hash = crypto.createHash('sha256');
  hash.update(fs.readFileSync(filePath));
  return hash.digest('hex');
}

function uniqueExistingNames(names) {
  return [...new Set(names)].sort();
}

function archiveFileNames(manifest) {
  const manifestNames = ['cutover-manifest.json', 'cutover-manifest.md'];
  return uniqueExistingNames([
    ...manifest.files.filter((file) => file.exists).map((file) => file.name),
    ...manifestNames,
  ]);
}

function createArchive(dir, outputDir, archiveName, fileNames) {
  fs.mkdirSync(outputDir, { recursive: true });
  const archivePath = path.join(outputDir, archiveName);
  if (fs.existsSync(archivePath)) {
    fs.unlinkSync(archivePath);
  }

  const result = spawnSync('tar', ['-czf', archivePath, ...fileNames], {
    cwd: dir,
    encoding: 'utf8',
  });
  if (result.status !== 0) {
    throw new Error(`tar failed: ${(result.stderr || result.stdout || '').trim()}`);
  }

  return archivePath;
}

function buildArchive(options) {
  const dir = path.resolve(options.dir || '');
  if (!dir || !fs.existsSync(dir) || !fs.statSync(dir).isDirectory()) {
    throw new Error(`Cutover directory does not exist: ${dir}`);
  }

  const outputDir = path.resolve(options.outputDir || dir);
  const manifest = buildManifest(dir);
  if (manifest.summary.missingCoreFiles.length > 0 && !options.allowMissingCore) {
    throw new Error(`Cannot archive incomplete cutover bundle. Missing core files: ${manifest.summary.missingCoreFiles.join(', ')}`);
  }

  const manifestOutput = writeManifest(manifest, dir);
  const fileNames = archiveFileNames(manifest)
    .filter((name) => fs.existsSync(path.join(dir, name)));
  const archiveName = safeArchiveName(options.archiveName);
  const archivePath = createArchive(dir, outputDir, archiveName, fileNames);
  const archiveSha256 = sha256(archivePath);
  const shaPath = `${archivePath}.sha256`;
  fs.writeFileSync(shaPath, `${archiveSha256}  ${path.basename(archivePath)}\n`);

  return {
    status: 'archived',
    generatedAt: new Date().toISOString(),
    directory: dir,
    outputDir,
    archivePath,
    sha256Path: shaPath,
    archiveSha256,
    fileCount: fileNames.length,
    files: fileNames,
    manifestSha256: manifest.manifestSha256,
    manifest: manifestOutput,
  };
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

    const archive = buildArchive(options);
    console.log(JSON.stringify(archive, null, 2));
    return 0;
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
  safeArchiveName,
  archiveFileNames,
  buildArchive,
};
