'use strict';
// Recensioni Google (2.1.33, carosello 2.1.36): riepilogo della scheda a sinistra e recensioni da 4 stelle in su in un carosello.
// Se il servizio non risponde il riquadro resta nascosto e la pagina mostra solo i link alla scheda Google.
(() => {
 const boxes=[...document.querySelectorAll('[data-google-reviews]')];if(!boxes.length)return;
 const en=document.documentElement.lang==='en';
 const t=en?{based:n=>'Based on '+(n===1?'1 review':n+' reviews'),all:'See all reviews',write:'Review us on',read:'Read more',close:'Show less',translated:'Translated by Google',prev:'Previous reviews',next:'Next reviews',page:n=>'Reviews page '+n,stars:n=>n+' out of 5 stars',levels:['Excellent','Very good','Good'],source:'Reviews from Google Maps. A selection of 4 and 5-star reviews is shown here; the average and total include every review.',reviews:n=>n===1?'1 review':n+' reviews'}
  :{based:n=>'Basato su '+(n===1?'1 recensione':n+' recensioni'),all:'Guarda tutte le recensioni',write:'Recensisci su',read:'Leggi tutto',close:'Riduci',translated:'Tradotta da Google',prev:'Recensioni precedenti',next:'Recensioni successive',page:n=>'Pagina recensioni '+n,stars:n=>n+' stelle su 5',levels:['Eccellente','Ottimo','Buono'],source:'Recensioni da Google Maps. Qui una selezione da 4 e 5 stelle; media e totale comprendono tutte le recensioni.',reviews:n=>n===1?'1 recensione':n+' recensioni'};
 const G='<svg viewBox="0 0 48 48" aria-hidden="true" focusable="false"><path fill="#FFC107" d="M43.6 20.1H42V20H24v8h11.3C33.7 32.7 29.2 36 24 36c-6.6 0-12-5.4-12-12s5.4-12 12-12c3.1 0 5.8 1.2 7.9 3.1l5.7-5.7C34 6.1 29.3 4 24 4 13 4 4 13 4 24s9 20 20 20 20-9 20-20c0-1.3-.1-2.6-.4-3.9z"/><path fill="#FF3D00" d="m6.3 14.7 6.6 4.8C14.7 15.1 19 12 24 12c3.1 0 5.8 1.2 7.9 3.1l5.7-5.7C34 6.1 29.3 4 24 4 16.3 4 9.7 8.3 6.3 14.7z"/><path fill="#4CAF50" d="M24 44c5.2 0 9.9-2 13.4-5.2l-6.2-5.2C29.2 35.1 26.7 36 24 36c-5.2 0-9.6-3.3-11.3-7.9l-6.5 5C9.5 39.6 16.2 44 24 44z"/><path fill="#1976D2" d="M43.6 20.1H42V20H24v8h11.3c-.8 2.2-2.2 4.2-4.1 5.6l6.2 5.2C37 39.2 44 34 44 24c0-1.3-.1-2.6-.4-3.9z"/></svg>';
 const el=(tag,cls,text)=>{const e=document.createElement(tag);if(cls)e.className=cls;if(text!==undefined)e.textContent=text;return e;};
 const link=(href,cls,text)=>{const a=el(href?'a':'span',cls,text);if(href){a.href=href;a.target='_blank';a.rel='noopener nofollow';}return a;};
 const gIcon=cls=>{const s=el('span','gr-g'+(cls?' '+cls:''));s.innerHTML=G;return s;};
 const number=v=>v.toLocaleString(en?'en-GB':'it-IT',{minimumFractionDigits:1,maximumFractionDigits:1});
 const stars=(value,cls)=>{const s=el('span','gr-stars'+(cls?' '+cls:''));s.setAttribute('role','img');s.setAttribute('aria-label',t.stars(number(value)));const fill=el('span','gr-stars-fill');fill.setAttribute('aria-hidden','true');fill.style.width=Math.max(0,Math.min(100,value/5*100))+'%';const base=el('span','gr-stars-base');base.setAttribute('aria-hidden','true');s.append(base,fill);return s;};
 const level=r=>r>=4.5?t.levels[0]:r>=4?t.levels[1]:r>=3.5?t.levels[2]:'';
 function avatar(r){
  const box=el('span','gr-avatar');const initial=(r.author||'G').trim().charAt(0).toUpperCase();
  const fallback=()=>{box.replaceChildren(initial);box.classList.add('gr-avatar-initial');box.style.background=['#5C6BC0','#5E35B1','#00897B','#6D4C41','#7A6236'][initial.charCodeAt(0)%5];};
  if(r.photo){const img=el('img');img.src=r.photo;img.alt='';img.loading='lazy';img.referrerPolicy='no-referrer';img.width=40;img.height=40;img.onerror=fallback;box.append(img);}else fallback();
  return box;
 }
 function card(r){
  const c=el('article','gr-card');const head=el('div','gr-card-head');const who=el('div','gr-who');
  who.append(link(r.author_url,'gr-author',r.author||'Google'),el('span','gr-time',r.time));head.append(avatar(r),who,gIcon('gr-card-g'));
  c.append(head,stars(r.rating,'gr-stars-card'));
  if(r.text){const text=el('p','gr-text',r.text);c.append(text);if(r.text.length>180){text.classList.add('gr-clamp');const b=el('button','gr-toggle',t.read);b.type='button';b.setAttribute('aria-expanded','false');b.onclick=()=>{const open=text.classList.toggle('gr-clamp')===false;b.textContent=open?t.close:t.read;b.setAttribute('aria-expanded',String(open));};c.append(b);}}
  return c;
 }
 function summaryBlock(d){
  const s=el('div','gr-side');const shop=el('span','gr-shop');shop.innerHTML='<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path fill="currentColor" d="M4 4h16l1.5 5a3 3 0 0 1-5 2.2A3 3 0 0 1 12 12a3 3 0 0 1-4.5-.8A3 3 0 0 1 2.5 9L4 4zm1 9.5c.7.3 1.4.4 2.2.3.9 0 1.7-.3 2.3-.8.7.5 1.6.8 2.5.8s1.8-.3 2.5-.8c.6.5 1.4.8 2.3.8.8 0 1.5 0 2.2-.3V20H5v-6.5z"/></svg>';
  const info=el('div','gr-side-info');if(level(d.rating))info.append(el('strong','gr-level',level(d.rating)));if(d.name)info.append(el('span','gr-name',d.name));
  const score=el('div','gr-score-row');score.append(el('strong','gr-score',number(d.rating)),stars(d.rating));
  const based=el('span','gr-based',t.based(d.count));based.append(gIcon());
  const actions=el('div','gr-actions');actions.append(link(d.url,'gr-btn',t.all));const w=link(d.write_url,'gr-btn gr-btn-light',t.write+' ');w.append(gIcon());actions.append(w);
  info.append(score,based,actions);s.append(shop,info);return s;
 }
 function carousel(list){
  const wrap=el('div','gr-carousel');const track=el('div','gr-track');track.tabIndex=0;list.forEach(r=>track.append(card(r)));
  const prev=el('button','gr-arrow gr-prev','‹'),next=el('button','gr-arrow gr-next','›');prev.type=next.type='button';prev.setAttribute('aria-label',t.prev);next.setAttribute('aria-label',t.next);
  const dots=el('div','gr-dots');
  const step=()=>{const c=track.firstElementChild;return c?c.getBoundingClientRect().width+parseFloat(getComputedStyle(track).columnGap||'0'):track.clientWidth;};
  const perView=()=>Math.max(1,Math.round(track.clientWidth/step()));
  const pages=()=>Math.max(1,list.length-perView()+1);
  const go=i=>track.scrollTo({left:i*step(),behavior:matchMedia('(prefers-reduced-motion: reduce)').matches?'auto':'smooth'});
  const current=()=>Math.round(track.scrollLeft/step());
  function update(){const n=pages(),i=Math.min(current(),n-1);if(dots.children.length!==n){dots.replaceChildren();for(let k=0;k<n;k++){const b=el('button','gr-dot');b.type='button';b.setAttribute('aria-label',t.page(k+1));b.onclick=()=>go(k);dots.append(b);}}
   [...dots.children].forEach((b,k)=>b.setAttribute('aria-current',k===i?'true':'false'));prev.disabled=i<=0;next.disabled=i>=n-1;const hide=n<2;prev.hidden=next.hidden=dots.hidden=hide;}
  prev.onclick=()=>go(Math.max(0,current()-1));next.onclick=()=>go(Math.min(pages()-1,current()+1));
  track.addEventListener('scroll',()=>requestAnimationFrame(update),{passive:true});addEventListener('resize',update);
  wrap.append(prev,track,next);requestAnimationFrame(update);return [wrap,dots];
 }
 function render(box,d){
  if(box.dataset.googleReviews==='full'){
   const inner=el('div','gr-inner');inner.append(summaryBlock(d));
   if(d.reviews.length){const main=el('div','gr-main');main.append(...carousel(d.reviews));inner.append(main);}
   box.replaceChildren(inner,el('p','gr-source',t.source));
  }else{const row=el('div','gr-summary');row.append(el('strong','gr-score',number(d.rating)),stars(d.rating),link(d.url,'gr-count',t.reviews(d.count)));box.replaceChildren(row,el('p','gr-source',t.source));}
  box.hidden=false;box.querySelectorAll('.gr-track').forEach(tr=>tr.dispatchEvent(new Event('scroll')));
 }
 fetch('../api/index.php?action=reviews&lang='+(en?'en':'it'),{credentials:'same-origin'}).then(r=>r.ok?r.json():null).then(j=>{const d=j&&j.reviews;if(d&&d.count>0)boxes.forEach(b=>render(b,d));}).catch(()=>{});
})();
