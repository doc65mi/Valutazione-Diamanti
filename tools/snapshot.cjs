const fs = require('fs');
const vm = require('vm');
const source = fs.readFileSync(fs.existsSync('tools/originals/ComproDiamanti.dc.html')?'tools/originals/ComproDiamanti.dc.html':'ComproDiamanti.dc.html','utf8');
const script = source.match(/<script type="text\/x-dc"[^>]*>([\s\S]*?)<\/script>/)[1];
const context = { DCLogic: class { props = {}; }, URLSearchParams, console };
vm.createContext(context);
vm.runInContext(script+';globalThis.instance = new Component();',context);
const pages = {};
for (const page of ['home','acquistiamo','chisiamo','faq']) {
  context.instance.state.page = page;
  pages[page] = context.instance.renderVals();
}
fs.writeFileSync('tools/snapshots.json',JSON.stringify(pages));
