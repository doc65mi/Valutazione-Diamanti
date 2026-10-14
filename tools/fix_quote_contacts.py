from pathlib import Path
from bs4 import BeautifulSoup
root=Path(__file__).resolve().parent.parent
doc=root/'files MD/struttura.md'
text=doc.read_text(encoding='utf-8')+'\n## Release 2.1.9 — contatti e origine diamante\n- [ ] Rimuovere origine dal form IT/EN e aggiornare validazione server.\n- [ ] Sistemare griglia contatti, consenso e riepilogo del passaggio 6.\n- [ ] Verificare invio diamante senza origine e layout desktop/mobile.\n'
doc.write_text(text,encoding='utf-8')
for lang in ['it','en']:
    p=root/'pages-src'/f'valutazione.{lang}.html'
    soup=BeautifulSoup(p.read_text(encoding='utf-8'),'html.parser')
    origin=soup.find('select',attrs={'name':'origin'})
    if origin:origin.find_parent(attrs={'data-kind':'diamond'}).decompose()
    step=soup.find('fieldset',attrs={'data-step':'6'})
    grid=step.find(class_='form-grid')
    grid['class']=['form-grid','contact-grid']
    grid.find('input',attrs={'name':'name'}).parent['class']=['contact-name']
    pref=grid.find('select',attrs={'name':'pref'}).parent
    choices=grid.find(attrs={'data-choice-for':'pref'})
    wrapper=soup.new_tag('div',attrs={'class':'contact-preference'})
    pref.insert_before(wrapper)
    wrapper.append(pref.extract());wrapper.append(choices.extract())
    step.find('input',attrs={'name':'consent'}).parent['class']=['consent-row']
    step.find('details')['class']=['contact-privacy']
    step.find(id='recap')['class']=['contact-recap']
    p.write_text(str(soup),encoding='utf-8')
p=root/'assets/form.js';s=p.read_text(encoding='utf-8').replace("'ct','origin','cert'","'ct','cert'");p.write_text(s,encoding='utf-8')
p=root/'PHP di sistema/requests.php';s=p.read_text(encoding='utf-8');s=s.replace("if(!$gem&&!in_array($f['origin'],['natural','unknown'],true))", "if(!$gem&&$f['origin']!==''&&!in_array($f['origin'],['natural','unknown'],true))");p.write_text(s,encoding='utf-8')
p=root/'PHP di sistema/core.php';s=p.read_text(encoding='utf-8').replace("'2.1.8'","'2.1.9'");p.write_text(s,encoding='utf-8')
