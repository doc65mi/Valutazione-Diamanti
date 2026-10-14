const {chromium}=require('C:/Program Files/WindowsApps/OpenAI.CodexPrimaryRuntime.v26-1007-641-0_26.1007.641.0_x64__3k8sg7r9htsxt/dependencies/node/node_modules/playwright');
const fs=require('fs'),path=require('path'),cp=require('child_process'),assert=require('assert');
const root=path.resolve(__dirname,'..');
(async()=>{
 const browser=await chromium.launch({executablePath:'C:/Program Files/Google/Chrome/Application/chrome.exe',headless:true});
 try{
  const ctx=await browser.newContext();
  await ctx.route('**/*',async route=>{
   const u=new URL(route.request().url());
   if(u.hostname!=='localhost'){if(u.protocol==='file:')return route.continue();return route.abort();}
   let file=path.join(root,decodeURIComponent(u.pathname));
   if(u.pathname==='/')file=path.join(root,'index.php');
   if(file.endsWith('.php')){
    const env={...process.env,REDIRECT_STATUS:'1',REQUEST_METHOD:'GET',SCRIPT_FILENAME:file,SCRIPT_NAME:u.pathname,QUERY_STRING:u.search.slice(1),SERVER_NAME:'localhost',SERVER_PROTOCOL:'HTTP/1.1',GATEWAY_INTERFACE:'CGI/1.1'};
    const r=cp.spawnSync('C:/laragon/bin/php/php-8.3.16-Win32-vs16-x64/php-cgi.exe',[],{env,cwd:root});assert(r.status===0);
    const output=r.stdout.toString(),location=output.match(/Location: ([^\r\n]+)/)[1];return route.fulfill({status:302,headers:{location:new URL(location,u).href},body:''});
   }
   if(!fs.existsSync(file))return route.fulfill({status:404,body:'Not found'});
   const types={'.html':'text/html; charset=utf-8','.css':'text/css','.js':'application/javascript'};
   return route.fulfill({status:200,contentType:types[path.extname(file)]||'application/octet-stream',body:fs.readFileSync(file)});
  });
  const page=await ctx.newPage();
  for(const entry of ['http://localhost:9999/','http://localhost:9999/index.php','http://localhost:9999/index.html','http://localhost:9999/ComproDiamanti.html','http://localhost:9999/ComproDiamanti.dc.html']){
   await page.goto(entry);await page.waitForURL('**/public/it/index.html');await page.locator('h1').waitFor({state:'visible'});
   assert((await page.locator('h1').textContent()).includes('Vendi il tuo diamante'));assert((await page.locator('body').evaluate(e=>getComputedStyle(e).margin))==='0px');console.log('PASS',entry);
  }
  await page.goto('file:///'+path.join(root,'index.html').replaceAll('\\','/'));
  await page.waitForURL('**/public/it/index.html');await page.locator('h1').waitFor({state:'visible'});console.log('PASS local HTML entry displays homepage (backend still requires HTTP)');
 }finally{await browser.close();}
})().catch(e=>{console.error(e);process.exitCode=1;});
