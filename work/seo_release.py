from pathlib import Path
r=Path(__file__).resolve().parent.parent
p=r/'PHP di sistema/core.php'
s=p.read_text(encoding='utf-8').replace("'2.1.17'","'2.1.18'")
s=s.replace("require_once __DIR__.'/settings.php';","require_once __DIR__.'/settings.php';\nrequire_once __DIR__.'/seo.php';")
s=s.replace("if($legal['site_url']) {","$legal['site_url']=seo_base();\n    if($legal['site_url']) {")
s=s.replace("return str_replace('../../assets/', '../assets/', $doc->saveHTML());","$url=seo_base().'/'.$lang.'/'.($slug==='home'?'index':$slug).'.html';\n    seo_markup($doc,$lang,$url,$c['meta']['title'],$c['meta']['description'],$slug==='faq'?'FAQPage':($slug==='chisiamo'?'AboutPage':'WebPage'));\n    if($slug==='grazie'){$meta=$doc->createElement('meta');$meta->setAttribute('name','robots');$meta->setAttribute('content','noindex,follow');$head->appendChild($meta);}\n    return str_replace('../../assets/', '../assets/', $doc->saveHTML());")
s=s.replace("$base=rtrim($s['site_url'],'/');","$base=seo_base();")
s=s.replace("$base.'/blog/',ENT_XML1).'</loc></url>';","$base.'/blog/',ENT_XML1).'</loc></url><url><loc>'.htmlspecialchars($base.'/blog/?lang=en',ENT_XML1).'</loc></url>';")
s=s.replace('blog_article_url(blog_config(),$post)','blog_article_url([\'base_url\'=>$base.\'/blog\'],$post)')
p.write_text(s,encoding='utf-8')
p=r/'PHP di sistema/blog.php';s=p.read_text(encoding='utf-8').replace("'2.1.17'","'2.1.18'");p.write_text(s,encoding='utf-8')
p=r/'PHP di sistema/blog-view.php';s=p.read_text(encoding='utf-8').replace('return $doc->saveHTML();',"if($status===200)seo_markup($doc,$lang,$link->getAttribute('href'),$title,$description,$article?'WebPage':'Blog',$article);\n    else foreach(iterator_to_array($xp->query('//script[@type=\"application/ld+json\"]')) as $node)$node->parentNode->removeChild($node);\n    return $doc->saveHTML();")
p.write_text(s,encoding='utf-8')
