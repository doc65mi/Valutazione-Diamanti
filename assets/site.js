'use strict';
// Preserve the former page links while keeping the public output static.
const oldPage=new URLSearchParams(location.search).get('p');
if(oldPage){const routes={'acquistiamo':'acquistiamo.html','chisiamo':'chisiamo.html','faq':'faq.html','come-funziona':'index.html#come-funziona'};if(routes[oldPage])location.replace(routes[oldPage]);}
if(document.body.dataset.page==='grazie'){
 let id='';try{id=sessionStorage.getItem('cd_receipt')||'';}catch{}
 document.querySelector('#receipt').textContent=id?(document.documentElement.lang==='en'?'Enquiry received. Reference: ':'Richiesta ricevuta. Riferimento: ')+id:(document.documentElement.lang==='en'?'Submit the valuation form to receive your enquiry reference.':'Invia il modulo di valutazione per ricevere il riferimento della richiesta.');
}
document.querySelectorAll('[data-faq-toggle]').forEach((button,i)=>{
 const answer=button.nextElementSibling;if(!answer)return;
 const set=open=>{answer.hidden=!open;button.setAttribute('aria-expanded',String(open));const sign=button.lastElementChild;if(sign)sign.textContent=open?'−':'+';};
 set(i===0);button.onclick=()=>set(button.getAttribute('aria-expanded')!=='true');button.style.cursor='pointer';
});
