// build.js - generates index.php from index.dev.php
// Usage: node build.js

const fs = require('fs');
const path = require('path');
const { minify } = require('terser');

const SRC  = path.join(__dirname, 'index.dev.php');
const DEST = path.join(__dirname, 'index.php');
const PHP_PLACEHOLDER = '"__PHPBLOCK_0__"';

function minifyCSS(css) {
    return css
        .replace(/\/\*[\s\S]*?\*\//g, '')
        .replace(/[ \t]*\n[ \t]*/g, '\n')
        .replace(/\n{2,}/g, '\n')
        .replace(/\s*([{},;:!>~+])\s*/g, '$1')
        .replace(/;}/g, '}')
        .replace(/\n/g, '')
        .trim();
}

async function minifyJSBlock(js) {
    const phpTagRe = /<\?php[\s\S]*?\?>/g;
    const phpTags = [];
    const sanitized = js.replace(phpTagRe, m => { phpTags.push(m); return PHP_PLACEHOLDER; });
    const result = await minify(sanitized, {
        compress: { passes:3, drop_debugger:true, drop_console:false, dead_code:true,
                    unused:true, collapse_vars:true, reduce_vars:true, keep_fargs:false,
                    pure_getters:true, unsafe_comps:true, unsafe_methods:true },
        mangle: { reserved: ['$','jQuery','location','history','document','window',
                              'navigator','console','Promise','Audio'], toplevel:false },
        format: { comments:false }
    });
    if (result.error) throw result.error;
    let code = result.code;
    phpTags.forEach(tag => { code = code.replace(PHP_PLACEHOLDER, tag); });
    return code;
}

async function main() {
    let src = fs.readFileSync(SRC, 'utf8');
    const originalSize = Buffer.byteLength(src, 'utf8');
    src = src.replace(/<!--(?!\[if)[\s\S]*?-->/g, '');
    src = src.replace(/<style>([\s\S]*?)<\/style>/, (_, css) => {
        process.stdout.write('Minifying CSS...\n');
        return '<style>' + minifyCSS(css) + '</style>';
    });
    const chunks = [];
    let lastIndex = 0, blockNum = 0, m;
    const scriptRe = /<script>([\s\S]*?)<\/script>/g;
    while ((m = scriptRe.exec(src)) !== null) {
        blockNum++;
        process.stdout.write('Minifying JS block ' + blockNum + ' (' + m[1].length + ' chars)...\n');
        chunks.push(src.slice(lastIndex, m.index));
        chunks.push('<script>' + (await minifyJSBlock(m[1])) + '</script>');
        lastIndex = m.index + m[0].length;
    }
    chunks.push(src.slice(lastIndex));
    src = chunks.join('').replace(/^[ \t]+$/gm, '').replace(/\n{3,}/g, '\n\n');
    fs.writeFileSync(DEST, src, 'utf8');
    const minifiedSize = Buffer.byteLength(src, 'utf8');
    console.log('\nDone!');
    console.log('  Source   : ' + SRC);
    console.log('  Output   : ' + DEST);
    console.log('  Original : ' + (originalSize/1024).toFixed(1) + ' KB');
    console.log('  Minified : ' + (minifiedSize/1024).toFixed(1) + ' KB');
    console.log('  Savings  : ' + ((1-minifiedSize/originalSize)*100).toFixed(1) + '%');
}
main().catch(e => { console.error('Build failed:', e); process.exit(1); });
