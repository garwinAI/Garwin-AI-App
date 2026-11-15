const fs = require('node:fs');
const path = require('node:path');

const source = require.resolve('bootstrap/dist/css/bootstrap.min.css');
const destination = path.join(__dirname, '..', 'public', 'assets', 'css', 'bootstrap.min.css');

fs.mkdirSync(path.dirname(destination), { recursive: true });
fs.copyFileSync(source, destination);
console.log(`Built ${path.relative(path.join(__dirname, '..'), destination)}`);
