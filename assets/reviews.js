'use strict';
// Recensioni Google (2.1.33): riempie i riquadri data-google-reviews con media, numero e ultime recensioni della scheda.
// Se il servizio non risponde il riquadro resta nascosto e la pagina mostra solo i link alla scheda Google.
(() => {
 const boxes=[...document.querySelectorAll('[data-google-reviews]')];if(!boxes.length)return;
 const en=document.documentElement.lang==='en';
 const t=en?{of:'out of 5',reviews:n=>n===1?'1 review':n+' reviews',more:'Show more reviews',less:'Show fewer reviews',read:'Read more',close:'Show less',translated:'Translated by Google',source:'Reviews from Google Maps',stars:n=>n+' out of 5 stars'}
  :{of:'su 5',reviews:n=>n===1?'1 recensione':n+' recensioni',more:'Mostra altre recensioni',less:'Mostra meno recensioni',read:'Leggi tutto',close:'Riduci',translated:'Tradotta da Google',source:'Recensioni da Google Maps',stars:n=>n+' stelle su 5'};
 const el=(tag,cls,text)=>{const e=document.createElement(tag);if(cls)e.className=cls;if(text!==undefined)e.textContent=text;return e;};
 const link=(href,cls,text)=>{const a=el(href?'a':'span',cls,text);if(href){a.href=href;a.target='_blank';a.rel='noopener nofollow';}return a;};
 const stars=(value,cls)=>{const s=el('span','gr-stars'+(cls?' '+cls:''));s.setAttribute('role','img');s.setAttribute('aria-label',t.stars(String(value).replace('.',en?'.':',')));const fill=el('span','gr-stars-fill','★★★★★');fill.style.width=Math.max(0,Math.min(100,value/5*100))+'%';s.append(el('span','gr-stars-base','★★★★★'),fill);return s;};
 const number=v=>v.toLocaleString(en?'en-GB':'it-IT',{minimumFractionDigits:1,maximumFractionDigits:1});
 function summary(d){const row=el('div','gr-summary');row.append(el('strong','gr-score',number(d.rating)),stars(d.rating),link(d.url,'gr-count',t.reviews(d.count)));return row;}
 function review(r){
  const card=el('article','gr-review');const head=el('div','gr-head');head.append(link(r.author_url,'gr-author',r.author||'Google'),stars(r.rating,'gr-stars-small'),el('span','gr-time',r.time));
  const text=el('p','gr-text',r.text);card.append(head,text);
  if(r.text.length>280){text.classList.add('gr-clamp');const b=el('button','gr-toggle',t.read);b.type='button';b.setAttribute('aria-expanded','false');b.onclick=()=>{const open=text.classList.toggle('gr-clamp')===false;b.textContent=open?t.close:t.read;b.setAttribute('aria-expanded',String(open));};card.append(b);}
  if(r.translated)card.append(el('span','gr-note',t.translated));
  if(r.url)card.append(link(r.url,'gr-link','Google ↗'));
  return card;
 }
 function render(box,d){
  box.replaceChildren(summary(d));
  if(box.dataset.googleReviews==='full'&&d.reviews.length){
   const list=el('div','gr-list');d.reviews.forEach((r,i)=>{const c=review(r);if(i>=3)c.hidden=true;list.append(c);});box.append(list);
   if(d.reviews.length>3){const b=el('button','gr-more',t.more);b.type='button';b.setAttribute('aria-expanded','false');b.onclick=()=>{const open=b.getAttribute('aria-expanded')!=='true';[...list.children].slice(3).forEach(c=>{c.hidden=!open;});b.textContent=open?t.less:t.more;b.setAttribute('aria-expanded',String(open));};box.append(b);}
  }
  box.append(el('p','gr-source',t.source));box.hidden=false;
 }
 fetch('../api/index.php?action=reviews&lang='+(en?'en':'it'),{credentials:'same-origin'}).then(r=>r.ok?r.json():null).then(j=>{const d=j&&j.reviews;if(d&&d.count>0)boxes.forEach(b=>render(b,d));}).catch(()=>{});
})();
