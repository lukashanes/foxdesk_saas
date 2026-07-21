#!/usr/bin/env node

const fs = require('fs');
const path = require('path');
const { spawnSync } = require('child_process');

const root = path.resolve(__dirname, '..');

function git(...args) {
  const result = spawnSync('git', args, { cwd: root, encoding: 'utf8' });
  if (result.status !== 0) {
    throw new Error((result.stderr || result.stdout || `git ${args.join(' ')} failed`).trim());
  }
  return result.stdout.trim();
}

function appVersion() {
  const source = fs.readFileSync(path.join(root, 'index.php'), 'utf8');
  const match = source.match(/define\(\s*['"]APP_VERSION['"]\s*,\s*['"]([^'"]+)['"]\s*\)/);
  if (!match) throw new Error('APP_VERSION is missing from index.php.');
  return match[1];
}

function currentState() {
  const status = git('status', '--porcelain=v1', '--untracked-files=all');
  return {
    schema: 1,
    product: 'FoxDesk Cloud',
    edition: 'saas',
    appVersion: appVersion(),
    commit: git('rev-parse', 'HEAD'),
    tree: git('rev-parse', 'HEAD^{tree}'),
    branch: git('branch', '--show-current') || 'detached',
    clean: status === '',
    generatedAt: new Date().toISOString(),
  };
}

function parseArgs(argv) {
  const options = { output: path.join(root, 'tmp', 'release-manifest.json'), verify: '' };
  for (const arg of argv) {
    if (arg.startsWith('--output=')) options.output = path.resolve(root, arg.slice(9));
    else if (arg.startsWith('--verify=')) options.verify = path.resolve(root, arg.slice(9));
    else if (arg !== '--help' && arg !== '-h') throw new Error(`Unknown argument: ${arg}`);
  }
  return options;
}

function verify(manifestPath, state) {
  const manifest = JSON.parse(fs.readFileSync(manifestPath, 'utf8'));
  const failures = [];
  if (!state.clean) failures.push('Current worktree is dirty.');
  for (const key of ['edition', 'appVersion', 'commit', 'tree']) {
    if (manifest[key] !== state[key]) failures.push(`${key} does not match the current release source.`);
  }
  if (manifest.clean !== true) failures.push('Manifest was not created from a clean worktree.');
  if (failures.length) throw new Error(failures.join(' '));
  process.stdout.write(`${JSON.stringify({ status: 'passed', manifest: manifestPath, commit: state.commit, tree: state.tree })}\n`);
}

function main() {
  const options = parseArgs(process.argv.slice(2));
  const state = currentState();
  if (options.verify) return verify(options.verify, state);
  if (!state.clean) throw new Error('Refusing to create a release manifest from a dirty worktree. Commit or stash every change first.');
  fs.mkdirSync(path.dirname(options.output), { recursive: true });
  fs.writeFileSync(options.output, `${JSON.stringify(state, null, 2)}\n`);
  process.stdout.write(`${JSON.stringify({ status: 'passed', manifest: options.output, ...state })}\n`);
}

try {
  main();
} catch (error) {
  process.stderr.write(`Release manifest failed: ${error.message}\n`);
  process.exit(1);
}
