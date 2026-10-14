#!/usr/bin/env node
// Temporary: structural PHP lint (quotes/comments-aware bracket balance)
// for the sandbox that has no PHP binary. Deleted after use.
const fs = require('fs');

const files = process.argv.slice(2);
let failed = 0;

for (const file of files) {
  const src = fs.readFileSync(file, 'utf8');
  if (!src.startsWith('<?php')) { console.log(`FAIL ${file}: missing <?php open tag`); failed++; continue; }

  const stack = [];
  let line = 1;
  let state = 'code'; // code | sq | dq | line | block

  for (let i = 0; i < src.length; i++) {
    const c = src[i];
    const n = src[i + 1];
    if (c === '\n') line++;

    if (state === 'sq') { if (c === '\\') i++; else if (c === "'") state = 'code'; continue; }
    if (state === 'dq') { if (c === '\\') i++; else if (c === '"') state = 'code'; continue; }
    if (state === 'line') { if (c === '\n') state = 'code'; continue; }
    if (state === 'block') { if (c === '*' && n === '/') { state = 'code'; i++; } continue; }

    if (c === "'") { state = 'sq'; continue; }
    if (c === '"') { state = 'dq'; continue; }
    if (c === '/' && n === '/') { state = 'line'; i++; continue; }
    if (c === '#') { state = 'line'; continue; }
    if (c === '/' && n === '*') { state = 'block'; i++; continue; }

    if ('([{'.includes(c)) stack.push([c, line]);
    else if (')]}'.includes(c)) {
      const want = { ')': '(', ']': '[', '}': '{' }[c];
      const top = stack.pop();
      if (!top || top[0] !== want) {
        console.log(`FAIL ${file}:${line}: unmatched '${c}' (top=${top ? top[0] + '@' + top[1] : 'none'})`);
        failed++; stack.length = 0; break;
      }
    }
  }
  if (state === 'sq' || state === 'dq') { console.log(`FAIL ${file}: unterminated string at EOF`); failed++; }
  else if (state === 'block') { console.log(`FAIL ${file}: unterminated block comment at EOF`); failed++; }
  else if (stack.length) { const t = stack.pop(); console.log(`FAIL ${file}: unclosed '${t[0]}' opened at line ${t[1]}`); failed++; }
  else if (failed === 0) console.log(`ok   ${file}`);
}
process.exit(failed ? 1 : 0);
