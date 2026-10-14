from pathlib import Path
root=Path(__file__).resolve().parent.parent
p=root/'assets/admin.js';s=p.read_text(encoding='utf-8')
start=s.index('function drawRequests()');end=s.index("$('#search').oninput",start)
s=s[:start]+'''function drawRequests(){
 const rows=filtered();$('#count').textContent=rows.length+' richieste (ultime 1000)';const inbox=$('#inbox');inbox.replaceChildren();
 if(!rows.length){inbox.append(el('p','Nessuna richiesta.'));return;}
 const wrapper=el('div',undefined,'requests-scroll');wrapper.tabIndex=0;wrapper.setAttribute('role','region');wrapper.setAttribute('aria-label','Elenco richieste di valutazione');
 const table=el('table',undefined,'requests-table');table.append(el('caption','Richieste di valutazione'));
 const head=el('thead'),heading=el('tr');for(const label of ['Data','Cliente','Oggetto','Contatti','Allegati','Stato','Dettaglio']){const th=el('th',label);th.scope='col';heading.append(th);}head.append(heading);table.append(head);const body=el('tbody');table.append(body);
 for(const r of rows){
  const row=el('tr'),date=el('td');date.append(el('span',new Date(r.created_at).toLocaleString('it-IT')),el('small',r.id));row.append(date);
  const name=el('th',r.payload.name);name.scope='row';name.append(el('small',r.language.toUpperCase()));row.append(name);
  const item=el('td',display(r.payload.cat));if(r.payload.brand)item.append(el('small',r.payload.brand));else if(r.payload.ct)item.append(el('small',display(r.payload.ct)+' ct'));row.append(item);
  const contact=el('td');for(const key of ['phone','email'])if(r.payload[key])contact.append(el('div',r.payload[key]));contact.append(el('small','Preferenza: '+display(r.payload.pref)));row.append(contact);
  row.append(el('td',String(r.attachments.length)));
  const state=el('td'),select=el('select');select.setAttribute('aria-label','Stato di '+r.id);for(const value of ['Nuova','Contattato','Appuntamento','Chiusa']){const o=el('option',value);o.value=value;select.append(o);}select.value=r.status;
  select.onchange=async()=>{select.disabled=true;try{await api('status',{id:r.id,status:select.value});r.status=select.value;drawRequests();}catch(e){select.value=r.status;message(e.message);}finally{select.disabled=false;}};state.append(select);row.append(state);
  const action=el('td'),button=el('button','Apri','request-toggle');button.type='button';button.setAttribute('aria-expanded','false');const detailId='request-'+r.id;button.setAttribute('aria-controls',detailId);button.setAttribute('aria-label','Apri dettaglio di '+r.payload.name);action.append(button);row.append(action);body.append(row);
  const detailRow=el('tr',undefined,'request-details');detailRow.id=detailId;detailRow.hidden=true;const cell=el('td');cell.colSpan=7;const card=el('article',undefined,'request');card.append(el('h2','Valutazione di '+r.payload.name));const dl=el('dl');for(const [key,label]of Object.entries(labels))if(r.payload[key])dl.append(el('dt',label),el('dd',display(r.payload[key])));card.append(dl);if(r.attachments.length)renderAttachments(card,r);else card.append(el('p','Nessun allegato.'));cell.append(card);detailRow.append(cell);body.append(detailRow);
  button.onclick=()=>{detailRow.hidden=!detailRow.hidden;button.setAttribute('aria-expanded',String(!detailRow.hidden));button.textContent=detailRow.hidden?'Apri':'Chiudi';};
 }
 wrapper.append(table);inbox.append(wrapper);
}
''' + s[end:];p.write_text(s,encoding='utf-8')
p=root/'PHP di sistema/core.php';s=p.read_text(encoding='utf-8').replace("'2.1.9'","'2.1.10'");p.write_text(s,encoding='utf-8')
p=root/'admin/index.php';s=p.read_text(encoding='utf-8').replace('../public/it/index.html','../it/index.html');p.write_text(s,encoding='utf-8')
