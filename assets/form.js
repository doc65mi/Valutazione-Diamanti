'use strict';
const form=document.querySelector('#valuation-form'), en=document.documentElement.lang==='en';
const t=(it,eng)=>en?eng:it;
let step=1,busy=false,csrf='',key=crypto.randomUUID().replaceAll('-',''),limits=null;
const MB=1024*1024,VIDEO_MAX=100*MB;
// 2.1.80: con «Oreficeria usata» il passo 4 (certificati) non serve e viene saltato.
const skip=n=>n===4&&field('cat').value==='used-gold';
const num=v=>{v=String(v).trim().replace(',','.');return /^\d{1,6}(\.\d{1,2})?$/.test(v)?Number(v):NaN;};
const $=s=>document.querySelector(s);
const field=n=>form.elements.namedItem(n);
const steps=[t('Oggetto','Item'),t('Altri oggetti','Other items'),t('Dettagli','Details'),t('Documenti','Documents'),t('Foto','Photographs'),t('Contatti','Contact details')];
// 2.1.80: invio con XMLHttpRequest per mostrare l'avanzamento del caricamento (utile con i video).
function send(data){return new Promise((resolve,reject)=>{const x=new XMLHttpRequest();x.open('POST','../api/index.php?action=request');x.setRequestHeader('X-CSRF-Token',csrf);
 x.upload.onprogress=e=>{if(e.lengthComputable&&e.total>MB)$('#next').textContent=t('Invio in corso… ','Submitting… ')+Math.floor(e.loaded/e.total*100)+'%';};
 x.onload=()=>{let result;try{result=JSON.parse(x.responseText);}catch(err){if(x.status===413){reject(Error(t('Gli allegati sono troppo grandi per il server. Togli il video e invialo su WhatsApp.','Your attachments are too large for the server. Please remove the video and send it on WhatsApp.')));return;}reject(err);return;}resolve({ok:x.status>=200&&x.status<300,status:x.status,result});};
 x.onerror=()=>reject(new SyntaxError('network'));x.send(data);});}
function error(s){const box=$('#form-error');box.textContent=s;if(s)box.scrollIntoView({block:'nearest',behavior:'smooth'});}
const fieldName=f=>(f.closest('label')?.querySelector('span')?.textContent||f.name).replace(/\s*\*$/,'').trim();
function update(){
 // 2.1.76: zaffiro, rubino e smeraldo sono voci a sé: seguono il percorso delle pietre preziose ma al passo 3 chiedono solo il peso
 // (tipo di pietra già noto, niente colore e purezza). «gemstone» resta solo per i link e le richieste precedenti.
 // 2.1.80: «Oreficeria usata» (used-gold) chiede titolo e peso dell'oro, senza pietra né certificato.
 const cat=field('cat').value,watch=cat==='watch',gold=cat==='used-gold',gemPick=['sapphire','ruby','emerald'].includes(cat),gem=gemPick||cat==='gemstone';
 for(const group of form.querySelectorAll('[data-kind]')){const kind=group.dataset.kind;const on=kind==='watch'?watch:kind==='gold'?gold:kind==='gemstone'?cat==='gemstone':kind==='diamond'?!watch&&!gem&&!gold:!watch&&!gold;group.hidden=!on;for(const f of group.querySelectorAll('input,select'))f.disabled=!on;}
 // 2.1.55: stesse fasce di peso per diamanti e pietre preziose; descrizione degli altri oggetti facoltativa;
 // il numero di certificato compare solo con un certificato GIA, HRD o IGI.
 // 2.1.67: per i gioielli con più pietre si può indicare «pietre piccole, sotto 1 ct» (percorso indicato nelle FAQ).
 {const small=['multi-stone','earrings','necklace','bracelet','brooch','other'].includes(cat),o=[...field('ct').options].find(x=>x.value==='small');if(o){o.hidden=!small;o.disabled=!small;if(!small&&field('ct').value==='small')field('ct').value='';}}
 $('#others-field').hidden=field('multi').value!=='yes';field('others').disabled=field('multi').value!=='yes';
 // 2.1.80: numero e file del certificato per ogni laboratorio; per diamante e solitario con GIA, HRD o IGI serve almeno uno dei due.
 // «Altro laboratorio» chiede il nome del laboratorio.
 {const c=field('cert'),v=c.disabled?'':c.value,has=v!==''&&v!=='none';$('#cert-number-field').hidden=!has;field('cert_no').disabled=!has;$('#cert-file-field').hidden=!has;field('cert_file').disabled=!has;
  const lab=v==='other-lab';$('#cert-lab-field').hidden=!lab;field('cert_lab').disabled=!lab;
  const need=['loose-diamond','solitaire'].includes(cat)&&['GIA','HRD','IGI'].includes(v);$('#cert-required-hint').hidden=!need;
  field('cert_no').setCustomValidity(need&&!field('cert_no').value.trim()&&!field('cert_file').files.length?t('Indica il numero del certificato oppure carica la foto o il PDF del certificato.','Please enter the report number or upload a photo or PDF of the report.'):'');}
 // 2.1.80: i carati esatti (facoltativi) scelgono da soli la fascia di peso.
 {const x=field('ct_exact'),raw=x.value.trim(),n=num(raw),small=[...field('ct').options].some(o=>o.value==='small'&&!o.disabled);let msg='';
  if(!x.disabled&&raw){if(!(n>=0.01&&n<=999.99))msg=t('Scrivi i carati con un numero, ad esempio 1,52.','Please enter the carat weight as a number, e.g. 1.52.');else if(n<1&&!small)msg=t('Valutiamo diamanti da 1 carato in su.','We assess diamonds of one carat or more.');else field('ct').value=n<1?'small':n<2?'1-2':n<3?'2-3':n<5?'3-5':'over-5';}
  x.setCustomValidity(msg);
  const g=field('gold_weight'),gr=g.value.trim(),gn=num(gr);g.setCustomValidity(!g.disabled&&gr&&!(gn>=0.1&&gn<=99999)?t('Scrivi il peso in grammi con un numero, ad esempio 25.','Please enter the weight in grams as a number, e.g. 25.'):'');}
 field('email').required=true;field('phone').required=['WhatsApp','phone'].includes(field('pref').value);
 for(const group of form.querySelectorAll('[data-choice-for]')){const select=field(group.dataset.choiceFor);for(const button of group.querySelectorAll('button')){const option=[...select.options].find(o=>o.value===button.dataset.value);button.hidden=!!option?.hidden;button.disabled=select.disabled||!!option?.disabled;button.setAttribute('aria-pressed',String(select.value===button.dataset.value));}}
 const name=field('name');name.setCustomValidity(name.value.trim()&&(!/\p{L}/u.test(name.value)||name.value.trim().length<2)?t('Inserisci un nome valido.','Please enter a valid name.'):'');
 const phone=field('phone'),p=phone.value.trim(),digits=p.replace(/\D/g,'');phone.setCustomValidity(p&&(!/^\+?[0-9 ().-]{7,40}$/.test(p)||digits.length<7||digits.length>15)?t('Inserisci un telefono valido.','Please enter a valid telephone number.'):'');
}
function show(){update();if(skip(step))step+=step>=4?1:-1;const intro=document.querySelector('.quote-intro');if(intro)intro.hidden=step!==1;for(const f of form.querySelectorAll('[data-step]'))f.hidden=Number(f.dataset.step)!==step;$('#step-label').textContent=t('Passaggio ','Step ')+step+t(' di 6 · ',' of 6 · ')+steps[step-1];$('#back').hidden=step===1;$('#next').textContent=step===6?t('Invia la richiesta gratuita','Submit your free enquiry'):step===5&&!$('#photos').files.length&&!$('#video').files.length?t('Salta e continua','Skip and continue'):t('Continua','Continue');document.querySelectorAll('.progress span').forEach((e,i)=>e.classList.toggle('done',i<step));if(step===6)recap();}
function validate(n){update();for(const f of form.querySelector('[data-step="'+n+'"]').querySelectorAll('input,select,textarea')){if(!f.disabled&&!f.checkValidity()){if(f.matches('.choice-native')){error(t('Seleziona un’opzione per: ','Please select an option for: ')+fieldName(f)+'.');form.querySelector('[data-choice-for="'+f.name+'"] button:not([hidden])')?.focus();}else{error(f.validity.valueMissing?t('Compila il campo obbligatorio: ','Please fill in the required field: ')+fieldName(f)+'.':t('Controlla il campo «','Please check the field “')+fieldName(f)+t('»: ','”: ')+f.validationMessage);f.focus();f.reportValidity();}return false;}}if(n===4)return certFileValid();if(n===5)return filesValid();return true;}
function certFileValid(){const f=field('cert_file');if(f.disabled||!f.files.length)return true;const x=f.files[0];if(!['image/jpeg','image/png','image/webp','application/pdf'].includes(x.type)){error(t('Per il certificato usa un file JPG, PNG, WebP o PDF.','Please use a JPG, PNG, WebP or PDF file for the report.'));return false;}if(x.size>5*MB){error(t('Il file del certificato può essere al massimo di 5 MB.','The report file can be up to 5 MB.'));return false;}return true;}
// 2.1.80: un video MP4 o MOV fino a 100 MB (o al limite del server, se più basso); oltre si suggerisce WhatsApp.
function videoMax(){return Math.min(VIDEO_MAX,limits?.video||VIDEO_MAX);}
function videoValid(){const v=$('#video').files[0];if(!v)return true;if(!(['video/mp4','video/quicktime'].includes(v.type)||/\.(mp4|mov)$/i.test(v.name))){error(t('Il video deve essere in formato MP4 o MOV.','The video must be an MP4 or MOV file.'));return false;}if(v.size>videoMax()){error(t('Il video supera '+Math.floor(videoMax()/MB)+' MB. Accorcialo oppure invialo su WhatsApp con il link qui sotto.','The video is larger than '+Math.floor(videoMax()/MB)+' MB. Please shorten it or send it on WhatsApp using the link below.'));return false;}return true;}
function totalValid(){const all=[...$('#photos').files,...(field('cert_file').disabled?[]:field('cert_file').files),...$('#video').files];const total=all.reduce((s,f)=>s+f.size,0);if(limits?.post&&total>limits.post-MB){error(t('Gli allegati superano il limite del server ('+Math.floor(limits.post/MB)+' MB in tutto). Togli il video e invialo su WhatsApp.','Your attachments exceed the server limit ('+Math.floor(limits.post/MB)+' MB in total). Please remove the video and send it on WhatsApp.'));return false;}return true;}
function filesValid(){const files=[...$('#photos').files],cert=field('cert_file').disabled?[]:[...field('cert_file').files];if(files.length>6){error(t('Puoi allegare al massimo 6 file.','You can attach up to 6 files.'));return false;}if(files.some(f=>f.size>5*1024*1024)||[...files,...cert].reduce((sum,f)=>sum+f.size,0)>20*1024*1024){error(t('Limite: 5 MB per file e 20 MB complessivi.','Limit: 5 MB per file and 20 MB in total.'));return false;}if(files.some(f=>!['image/jpeg','image/png','image/webp','application/pdf'].includes(f.type))){error(t('Usa file JPG, PNG, WebP o PDF.','Please use JPG, PNG, WebP or PDF files.'));return false;}return videoValid()&&totalValid();}
function recap(){const dl=$('#recap');dl.replaceChildren();for(const name of ['cat','multi','others','brand','stone','ct','ct_exact','shape','color','clarity','gem_quality','gold_karat','gold_weight','cert','cert_lab','cert_no','kit']){const f=field(name);if(f.disabled||!f.value)continue;const dt=document.createElement('dt'),dd=document.createElement('dd');dt.textContent=f.closest('label').firstChild.textContent;dd.textContent=f.tagName==='SELECT'?f.selectedOptions[0].textContent:f.value;dl.append(dt,dd);}}
// 2.1.80: per i campi di testo l'errore si toglie mentre si scrive, non all'uscita dal campo: così il clic su «Continua» non va a vuoto
// quando il messaggio sparisce e il pulsante si sposta.
const typed=t=>t.matches('textarea,input:not([type]),input[type=text],input[type=email],input[type=tel]');
form.addEventListener('change',e=>{if(!typed(e.target))error('');update();});form.addEventListener('input',e=>{if(typed(e.target))error('');update();});
function listFiles(){const list=$('#selected-files');list.replaceChildren();for(const f of [...$('#photos').files,...$('#video').files]){const li=document.createElement('li');li.textContent=f.name+' · '+(f.size/1024/1024).toFixed(2)+' MB';list.append(li);}error('');filesValid();show();}
$('#photos').addEventListener('change',listFiles);$('#video').addEventListener('change',listFiles);
$('#back').onclick=()=>{if(busy)return;step--;if(skip(step))step--;error('');show();$('#step-label').scrollIntoView({block:'start'});};
async function getCsrf(){const r=await fetch('../api/index.php?action=session');const d=await r.json();if(!r.ok)throw Error(d.error||t('Connessione non disponibile.','Unable to connect.'));csrf=d.csrf;limits=d.upload||null;}
form.onsubmit=async e=>{e.preventDefault();if(busy)return;error('');if(!validate(step))return;if(step<6){step++;if(skip(step))step++;show();$('#step-label').scrollIntoView({block:'start'});return;}for(let i=1;i<=6;i++){if(skip(i))continue;if(!validate(i)){step=i;show();validate(i);return;}}
 busy=true;$('#next').disabled=true;$('#back').disabled=true;$('#next').textContent=t('Invio in corso…','Submitting…');
 try{if(!csrf)await getCsrf();const data=new FormData(form);data.set('request_key',key);if(!window.CD)throw Error(t('Ricarica la pagina per la verifica di sicurezza.','Please reload the page for security verification.'));data.set('recaptcha_token',await window.CD.recaptchaToken());const r=await send(data);const result=r.result;if(!r.ok){if(r.status===403)csrf='';throw Error(result.error||t('Invio non riuscito. Riprova.','Your enquiry could not be submitted. Please try again.'));}try{sessionStorage.setItem('cd_receipt',result.id);}catch{}location.assign(t('grazie.html','thank-you.html'));}
 catch(err){error(err instanceof SyntaxError?t('Invio non riuscito. Controlla la connessione o la dimensione degli allegati.','Unable to submit. Please check your connection or attachment size.'):err.message);}
 finally{busy=false;$('#next').disabled=false;$('#back').disabled=false;show();}
};
// Le card delle categorie aprono il modulo con il tipo già scelto: ?tipo=diamanti|gioielli|pietre|vintage|orologi (2.1.49; ?cat= resta valido).
const query=new URLSearchParams(location.search);const urlCat=query.get('tipo')||query.get('cat');const aliases={oro:'used-gold',oreficeria:'used-gold',diamanti:'loose-diamond',diamante:'loose-diamond',gioielli:'solitaire',zaffiro:'sapphire',rubino:'ruby',smeraldo:'emerald',orologi:'watch',solitario:'solitaire'};
if(urlCat){const value=aliases[urlCat]||urlCat;if([...field('cat').options].some(o=>o.value===value)){field('cat').value=value;field('cat').dispatchEvent(new Event('change',{bubbles:true}));}}
form.querySelectorAll('[data-choice-for]').forEach(group=>{group.addEventListener('click',e=>{const button=e.target.closest('button[data-value]');if(!button||button.disabled)return;field(group.dataset.choiceFor).value=button.dataset.value;field(group.dataset.choiceFor).dispatchEvent(new Event('change',{bubbles:true}));});});
// 2.1.71 (checklist D11): nota sui campi obbligatori, segnati con l'asterisco dal CSS.
{const note=document.createElement('p');note.className='required-note';const star=document.createElement('b');star.textContent='*';note.append(t('I campi con ','Fields marked '),star,t(' sono obbligatori.',' are required.'));form.prepend(note);}
show();getCsrf().catch(err=>error(err.message));
