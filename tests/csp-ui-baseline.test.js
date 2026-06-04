const { execFileSync } = require('child_process');
const path = require('path');

const root = path.resolve(__dirname, '..');

execFileSync(
  process.execPath,
  [path.join(root, 'bin', 'audit-csp-ui.js'), '--check-baseline'],
  {
    cwd: root,
    stdio: 'inherit'
  }
);

