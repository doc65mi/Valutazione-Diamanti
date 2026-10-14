from pathlib import Path
import re,json
r=Path.cwd()
for folder in ['partials','pages-src']:
 for p in (r/folder).glob('*.html'):
  s=p.read_text(encoding='utf-8');n=re.sub(r'<a\b[^>]*\b(?:href|data-om-label)\s*=\s*[\"\'][^\"\']*instagram[^\"\']*[\"\'][^>]*>.*?</a>','',s,flags=re.I|re.S)
  if n!=s:p.write_text(n,encoding='utf-8')
for p in (r/'content').glob('*.json'):
 c=json.loads(p.read_text(encoding='utf-8'));slots=c.get('slots',{});remove=[k for k,v in slots.items() if 'instagram' in json.dumps(v).lower()]
 if remove:
  for k in remove:del slots[k]
  p.write_text(json.dumps(c,ensure_ascii=False,indent=2)+'\n',encoding='utf-8')
for name in ['core.php','blog.php']:
 p=r/'PHP di sistema'/name;p.write_text(p.read_text(encoding='utf-8').replace("'2.1.19'","'2.1.20'"),encoding='utf-8')
