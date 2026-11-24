/**
 * CR338: starting a folder rename must not turn the folder name into markup.
 *
 * Usage: NODE_PATH=<folder with jsdom + jquery in node_modules> node tests/js/test-cr338-rename-xss.js [path/to/media-library.js]
 *   (one-off setup: cd /tmp/h && npm i jsdom jquery)
 *
 * Loads the real media-library.js into jsdom with real jQuery (one line added inside its IIFE to
 * expose the object), puts a folder named with a double quote in the sidebar, and calls
 * startRename(). The input must hold the exact name, with no extra attribute on it.
 * FAILS on the pre-fix file: escapeHtml left the quote alone, so the name broke out of value="".
 */
'use strict';
const fs = require('fs');
const path = require('path');
const { JSDOM } = require('jsdom');

const file = process.argv[2] || path.join(__dirname, '..', '..', 'assets', 'js', 'media-library.js');
const src = fs.readFileSync(file, 'utf8');
const close = src.lastIndexOf('})(jQuery);');
if (close < 0) throw new Error('media-library.js no longer ends with })(jQuery);');
const exposed = src.slice(0, close) + 'window.__tpfMO = TPF_MediaOrganizer;\n' + src.slice(close);

let fails = 0;
function check(name, cond, detail) {
  console.log((cond ? 'ok   ' : 'FAIL ') + name + (!cond && detail ? `  [${detail}]` : ''));
  if (!cond) fails++;
}

for (const evil of ['x" onfocus="window.__pwned=1" a="', "x' onfocus='window.__pwned=1' a='", 'Tom & "Jerry" <b>']) {
  const dom = new JSDOM('<!DOCTYPE html><body></body>', { runScripts: 'outside-only' });
  const w = dom.window;
  w.eval(fs.readFileSync(require.resolve('jquery'), 'utf8'));
  w.eval(exposed);
  const $ = w.jQuery;
  const $item = $('<li class="tpf-mo-folder-item" data-folder-id="5"><a class="tpf-mo-folder-link">' +
                  '<span class="tpf-mo-folder-name"></span></a></li>').appendTo(w.document.body);
  $item.find('.tpf-mo-folder-name').text(evil);   // as createSidebar shows a stored name
  w.__tpfMO.startRename('5');
  const input = w.document.querySelector('.tpf-mo-rename-input');
  check(`rename of ${JSON.stringify(evil)}: one input holding the exact name`, input && input.value === evil,
        input ? input.value : 'no input');
  check(`rename of ${JSON.stringify(evil)}: no attribute injected`,
        input && [...input.attributes].every(a => ['type', 'class', 'value'].includes(a.name)) && !w.__pwned,
        input ? [...input.attributes].map(a => a.name).join(',') : '');
}

console.log(`\nFAILURES: ${fails}`);
process.exit(fails ? 1 : 0);
