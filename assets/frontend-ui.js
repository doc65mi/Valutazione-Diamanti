'use strict';
(() => {
 const header=document.querySelector('body.public-page header');
 if(!header)return;
 const container=header.firstElementChild?.tagName==='DIV'?header.firstElementChild:header;
 const brand=container.querySelector('a');if(!brand)return;
 header.classList.add('responsive-header');container.classList.add('header-row');brand.classList.add('header-brand');
 const menu=document.createElement('div');menu.className='header-menu';menu.id='site-menu';
 for(const node of [...container.children])if(node!==brand&&!node.classList.contains('header-languages'))menu.append(node);
 const toggle=document.createElement('button');toggle.type='button';toggle.className='header-toggle';toggle.textContent='Menu';toggle.setAttribute('aria-controls',menu.id);toggle.setAttribute('aria-expanded','false');
 toggle.onclick=()=>{const open=header.classList.toggle('menu-open');toggle.setAttribute('aria-expanded',String(open));toggle.textContent=open?(document.documentElement.lang==='en'?'Close':'Chiudi'):'Menu';};
 menu.addEventListener('click',e=>{if(e.target.closest('a')){header.classList.remove('menu-open');toggle.setAttribute('aria-expanded','false');toggle.textContent='Menu';}});
 container.append(toggle,menu);
})();
