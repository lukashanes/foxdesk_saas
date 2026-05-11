const { execFileSync } = require('child_process');
const fs = require('fs');

function run(command, args, options = {}) {
  return execFileSync(command, args, {
    encoding: 'utf8',
    stdio: options.stdio || ['ignore', 'pipe', 'pipe']
  });
}

try {
  run('docker', ['compose', '-f', 'docker-compose.local.yml', 'down', '-v', '--remove-orphans'], { stdio: 'inherit' });
} catch (_) {}

for (const path of ['config.php']) {
  fs.rmSync(path, { force: true });
}

console.log('Local FoxDesk SaaS environment reset.');

