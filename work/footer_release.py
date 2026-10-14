from pathlib import Path
r=Path.cwd();p=r/'PHP di sistema/core.php';s=p.read_text(encoding='utf-8').replace("'2.1.18'","'2.1.19'").replace("require_once __DIR__.'/seo.php';","require_once __DIR__.'/seo.php';\nrequire_once __DIR__.'/footer.php';")
s=s.replace("$url=seo_base().'/'.$lang", "tidy_footer($doc,$lang);\n    $url=seo_base().'/'.$lang")
a=s.index('    if($footer){$blogLink=');b=s.index('\n',a);s=s[:a]+s[b:];p.write_text(s,encoding='utf-8')
p=r/'PHP di sistema/blog.php';p.write_text(p.read_text(encoding='utf-8').replace("'2.1.18'","'2.1.19'"),encoding='utf-8')
p=r/'assets/frontend-ui.css';s=p.read_text(encoding='utf-8');s+='\n.site-subfooter{display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px 24px}.subfooter-links{display:flex;align-items:center;flex-wrap:wrap;gap:8px}.site-subfooter a,.site-subfooter button{color:inherit;font:inherit}.site-subfooter button{background:none;border:0;padding:0;text-decoration:underline;cursor:pointer}.site-subfooter button:hover,.site-subfooter a:hover{color:#C9B48A}@media(max-width:640px){.site-subfooter{justify-content:flex-start}.subfooter-links{row-gap:12px}}\n';p.write_text(s,encoding='utf-8')
