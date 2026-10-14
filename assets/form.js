'use strict';
const form=document.querySelector('#valuation-form'), en=document.documentElement.lang==='en';
const t=(it,eng)=>en?eng:it;
let step=1,busy=false,csrf='',key=crypto.randomUUID().replaceAll('-','');
const $=s=>document.querySelector(s);
const field=n=>form.elements.namedItem(n);
const steps=[t('Oggetto','Item'),t('Altri oggetti','Other items'),t('Dettagli','Details'),t('Documenti','Documents'),t('Foto','Photographs'),t('Contatti','Contact details')];
function error(s){$('#form-error').textContent=s;}
function update(){
 const cat=field('cat').value,watch=cat==='watch',gem=cat==='gemstone';
 for(const group of form.querySelectorAll('[data-kind]')){const kind=group.dataset.kind;const on=kind==='watch'?watch:kind==='gemstone'?gem:kind==='diamond'?!watch&&!gem:!watch;group.hidden=!on;for(const f of group.querySelectorAll('input,select'))f.disabled=!on;}
 for(const o of field('ct').options){o.hidden=gem?['2-3','3-5'].includes(o.value):['under-1','2-5'].includes(o.value);o.disabled=o.hidden;}
 if(field('ct').selectedOptions[0]?.disabled)field('ct').value='';
 $('#others-field').hidden=field('multi').value!=='yes';field('others').required=field('multi').value==='yes';field('others').disabled=field('multi').value!=='yes';
 field('email').required=true;field('phone').required=['WhatsApp','phone'].includes(field('pref').value);
 for(const group of form.querySelectorAll('[data-choice-for]')){const select=field(group.dataset.choiceFor);for(const button of group.querySelectorAll('button')){const option=[...select.options].find(o=>o.value===button.dataset.value);button.hidden=!!option?.hidden;button.disabled=select.disabled||!!option?.disabled;button.setAttribute('aria-pressed',String(select.value===button.dataset.value));}}
 const name=field('name');name.setCustomValidity(name.value.trim()&&(!/\p{L}/u.test(name.value)||name.value.trim().length<2)?t('Inserisci un nome valido.','Please enter a valid name.'):'');
 const phone=field('phone'),p=phone.value.trim(),digits=p.replace(/\D/g,'');phone.setCustomValidity(p&&(!/^\+?[0-9 ().-]{7,40}$/.test(p)||digits.length<7||digits.length>15)?t('Inserisci un telefono valido.','Please enter a valid telephone number.'):'');
}
function show(){update();const intro=document.querySelector('.quote-intro');if(intro)intro.hidden=step!==1;for(const f of form.querySelectorAll('[data-step]'))f.hidden=Number(f.dataset.step)!==step;$('#step-label').textContent=t('Passaggio ','Step ')+step+t(' di 6 · ',' of 6 · ')+steps[step-1];$('#back').hidden=step===1;$('#next').textContent=step===6?t('Invia la richiesta gratuita','Submit your free enquiry'):step===5&&!$('#photos').files.length?t('Salta e continua','Skip and continue'):t('Continua','Continue');document.querySelectorAll('.progress span').forEach((e,i)=>e.classList.toggle('done',i<step));if(step===6)recap();}
function validate(n){update();for(const f of form.querySelector('[data-step="'+n+'"]').querySelectorAll('input,select,textarea')){if(!f.disabled&&!f.checkValidity()){if(f.matches('.choice-native')){error(t('Seleziona un’opzione per: ','Please select an option for: ')+f.closest('label').textContent.trim());form.querySelector('[data-choice-for="'+f.name+'"] button:not([hidden])')?.focus();}else f.reportValidity();return false;}}if(n===5)return filesValid();return true;}
function filesValid(){const files=[...$('#photos').files];if(files.length>6){error(t('Puoi allegare al massimo 6 file.','You can attach up to 6 files.'));return false;}if(files.some(f=>f.size>5*1024*1024)||files.reduce((sum,f)=>sum+f.size,0)>20*1024*1024){error(t('Limite: 5 MB per file e 20 MB complessivi.','Limit: 5 MB per file and 20 MB in total.'));return false;}if(files.some(f=>!['image/jpeg','image/png','image/webp','application/pdf'].includes(f.type))){error(t('Usa file JPG, PNG, WebP o PDF.','Please use JPG, PNG, WebP or PDF files.'));return false;}return true;}
function recap(){const dl=$('#recap');dl.replaceChildren();for(const name of ['cat','multi','others','brand','stone','ct','cert','kit']){const f=field(name);if(f.disabled||!f.value)continue;const dt=document.createElement('dt'),dd=document.createElement('dd');dt.textContent=f.closest('label').firstChild.textContent;dd.textContent=f.tagName==='SELECT'?f.selectedOptions[0].textContent:f.value;dl.append(dt,dd);}}
form.addEventListener('change',()=>{error('');update();});form.addEventListener('input',()=>{update();});
$('#photos').addEventListener('change',()=>{const list=$('#selected-files');list.replaceChildren();for(const f of $('#photos').files){const li=document.createElement('li');li.textContent=f.name+' · '+(f.size/1024/1024).toFixed(2)+' MB';list.append(li);}filesValid();show();});
$('#back').onclick=()=>{if(busy)return;step--;error('');show();$('#step-label').scrollIntoView({block:'start'});};
async function getCsrf(){const r=await fetch('../api/index.php?action=session');const d=await r.json();if(!r.ok)throw Error(d.error||t('Connessione non disponibile.','Unable to connect.'));csrf=d.csrf;}
form.onsubmit=async e=>{e.preventDefault();if(busy)return;error('');if(!validate(step))return;if(step<6){step++;show();$('#step-label').scrollIntoView({block:'start'});return;}for(let i=1;i<=6;i++){if(!validate(i)){step=i;show();validate(i);return;}}
 busy=true;$('#next').disabled=true;$('#back').disabled=true;$('#next').textContent=t('Invio in corso…','Submitting…');
 try{if(!csrf)await getCsrf();const data=new FormData(form);data.set('request_key',key);if(!window.CD)throw Error(t('Ricarica la pagina per la verifica di sicurezza.','Please reload the page for security verification.'));data.set('recaptcha_token',await window.CD.recaptchaToken());const r=await fetch('../api/index.php?action=request',{method:'POST',headers:{'X-CSRF-Token':csrf},body:data});const result=await r.json();if(!r.ok){if(r.status===403)csrf='';throw Error(result.error||t('Invio non riuscito. Riprova.','Your enquiry could not be submitted. Please try again.'));}try{sessionStorage.setItem('cd_receipt',result.id);}catch{}location.assign('grazie.html');}
 catch(err){error(err instanceof SyntaxError?t('Invio non riuscito. Controlla la connessione o la dimensione degli allegati.','Unable to submit. Please check your connection or attachment size.'):err.message);}
 finally{busy=false;$('#next').disabled=false;$('#back').disabled=false;show();}
};
const urlCat=new URLSearchParams(location.search).get('cat');const aliases={diamanti:'loose-diamond',pietre:'gemstone',orologi:'watch',solitario:'solitaire'};if(urlCat)field('cat').value=aliases[urlCat]||urlCat;
form.querySelectorAll('[data-choice-for]').forEach(group=>{group.addEventListener('click',e=>{const button=e.target.closest('button[data-value]');if(!button||button.disabled)return;field(group.dataset.choiceFor).value=button.dataset.value;field(group.dataset.choiceFor).dispatchEvent(new Event('change',{bubbles:true}));});});
show();getCsrf().catch(err=>error(err.message));
