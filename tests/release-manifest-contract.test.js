const assert = require('assert');
const fs = require('fs');
const path = require('path');

const source = fs.readFileSync(path.join(__dirname, '..', 'bin', 'release-manifest.js'), 'utf8');
for (const required of [
  "git('status', '--porcelain=v1', '--untracked-files=all')",
  "git('rev-parse', 'HEAD')",
  "git('rev-parse', 'HEAD^{tree}')",
  "git('branch', '--show-current')",
  'Refusing to create a release manifest from a dirty worktree',
  "for (const key of ['edition', 'appVersion', 'commit', 'tree'])",
]) assert(source.includes(required), `Missing release-manifest contract: ${required}`);

console.log('Release manifest contract test passed.');
