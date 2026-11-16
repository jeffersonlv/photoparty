const L = require('../lib');
module.exports = function writeCfg(key, name, data) { L.fs.writeFileSync(`${L.FR}/${key}/config.json`, JSON.stringify({ version: 1, key, name, orientations: data }, null, 2)); };
