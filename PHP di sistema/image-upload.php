<?php
declare(strict_types=1);
function upload_site_image(array $u): never {
    $slug=$_GET['slug']??'';$lang=$_GET['lang']??'it';page_path($slug,$lang);can_page($u,$slug);
    $slot=$_POST['slot']??'';$schema=slot_schema($slug,$lang);
    if(!is_string($slot)||!isset($schema[$slot])||$schema[$slot]['type']!=='image')fail('Campo immagine non valido.');
    if($u['role']!=='admin'&&$schema[$slot]['role']==='admin')fail('Permesso richiesto.',403);
    rate('site-image',30,3600);$file=$_FILES['image']??null;
    if(!$file||!is_string($file['tmp_name']??null)||($file['error']??1)!==UPLOAD_ERR_OK||!is_uploaded_file($file['tmp_name']))fail('Caricamento immagine non riuscito.');
    if(($file['size']??0)<1||$file['size']>5*1024*1024)fail('Limite immagine: 5 MB.');
    $mime=(new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
    if(!in_array($mime,['image/jpeg','image/png','image/webp'],true))fail('Usa immagini JPG, PNG o WebP.');
    $info=@getimagesize($file['tmp_name']);if(!$info||$info[0]*$info[1]>16000000)fail('Immagine non valida: massimo 16 megapixel.');
    $img=@imagecreatefromstring(file_get_contents($file['tmp_name']));if(!$img)fail('Immagine non leggibile.');
    $dir=ROOT.'/uploads/site';if(!is_dir($dir)&&!mkdir($dir,0755,true))fail('Cartella uploads non scrivibile.',500);
    $name=bin2hex(random_bytes(16)).'.png';imagealphablending($img,false);imagesavealpha($img,true);
    $ok=imagepng($img,$dir.'/'.$name);imagedestroy($img);if(!$ok)fail('Salvataggio immagine non riuscito.',500);
    audit('site-image',$slug.'.'.$lang.':'.$name);
    $base=rtrim(str_replace('\\','/',dirname(dirname($_SERVER['SCRIPT_NAME']))),'/');
    respond(['url'=>$base.'/uploads/site/'.$name],201);
}
