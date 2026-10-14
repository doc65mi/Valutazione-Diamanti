<?php
declare(strict_types=1);
// 2.1.24: su Plesk PHP-FPM può girare con umask 077, quindi imagepng() creava file 0600 che nginx (altro utente)
// non può leggere: l'immagine risultava caricata ma non si vedeva. Ora il file nasce in un temporaneo, riceve 0644
// e poi viene rinominato. Le foto grandi vengono ridotte a 2000 px e salvate in WebP (PNG solo senza WebP in GD).
function upload_ini_bytes(string $key): int {
    $v=trim((string)ini_get($key));$n=(int)$v;$unit=strtolower(substr($v,-1));
    return match($unit){'g'=>$n*1073741824,'m'=>$n*1048576,'k'=>$n*1024,default=>$n};
}
function upload_limit_text(): string {
    $limit=min(array_filter([upload_ini_bytes('upload_max_filesize'),upload_ini_bytes('post_max_size'),5*1024*1024]));
    return number_format($limit/1048576,1,',','').' MB';
}
function upload_site_image(array $u): never {
    $slug=$_GET['slug']??'';$lang=$_GET['lang']??'it';page_path($slug,$lang);can_page($u,$slug);
    // Oltre post_max_size PHP svuota $_POST e $_FILES: senza questo controllo l'errore era "Campo immagine non valido".
    if(!$_POST&&!$_FILES&&(int)($_SERVER['CONTENT_LENGTH']??0)>0)fail('Immagine troppo grande per il server: massimo '.upload_limit_text().'.',413);
    $slot=$_POST['slot']??'';$schema=slot_schema($slug,$lang);
    if(!is_string($slot)||!isset($schema[$slot])||$schema[$slot]['type']!=='image')fail('Campo immagine non valido.');
    if($u['role']!=='admin'&&$schema[$slot]['role']==='admin')fail('Permesso richiesto.',403);
    rate('site-image',30,3600);$file=$_FILES['image']??null;
    $error=is_array($file)?($file['error']??UPLOAD_ERR_NO_FILE):UPLOAD_ERR_NO_FILE;
    if($error===UPLOAD_ERR_INI_SIZE||$error===UPLOAD_ERR_FORM_SIZE)fail('Immagine troppo grande per il server: massimo '.upload_limit_text().'.',413);
    if($error===UPLOAD_ERR_NO_TMP_DIR||$error===UPLOAD_ERR_CANT_WRITE)fail('Il server non riesce a salvare il file caricato (cartella temporanea PHP).',500);
    if($error!==UPLOAD_ERR_OK||!is_string($file['tmp_name']??null)||!is_uploaded_file($file['tmp_name']))fail('Caricamento immagine non riuscito.');
    if(($file['size']??0)<1||$file['size']>5*1024*1024)fail('Limite immagine: 5 MB.');
    $mime=(new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
    if(!in_array($mime,['image/jpeg','image/png','image/webp'],true))fail('Usa immagini JPG, PNG o WebP.');
    $info=@getimagesize($file['tmp_name']);if(!$info||$info[0]*$info[1]>16000000)fail('Immagine non valida: massimo 16 megapixel.');
    if(upload_ini_bytes('memory_limit')>0&&upload_ini_bytes('memory_limit')<268435456)@ini_set('memory_limit','256M');
    $img=@imagecreatefromstring(file_get_contents($file['tmp_name']));if(!$img)fail('Immagine non leggibile.');
    if(imagesx($img)>2000){$scaled=imagescale($img,2000,-1,IMG_BICUBIC);imagedestroy($img);if(!$scaled)fail('Immagine non leggibile.');$img=$scaled;}
    imagepalettetotruecolor($img);imagealphablending($img,false);imagesavealpha($img,true);
    $dir=ROOT.'/uploads/site';if(!is_dir($dir)&&!mkdir($dir,0755,true))fail('Cartella uploads non scrivibile.',500);@chmod($dir,0755);
    $webp=function_exists('imagewebp');$name=bin2hex(random_bytes(16)).($webp?'.webp':'.png');$tmp=$dir.'/.tmp-'.bin2hex(random_bytes(8));
    $ok=$webp?imagewebp($img,$tmp,82):imagepng($img,$tmp,9);imagedestroy($img);
    if(!$ok||!chmod($tmp,0644)||!rename($tmp,$dir.'/'.$name)){@unlink($tmp);fail('Salvataggio immagine non riuscito.',500);}
    audit('site-image',$slug.'.'.$lang.':'.$name);
    $base=rtrim(str_replace('\\','/',dirname(dirname($_SERVER['SCRIPT_NAME']))),'/');
    respond(['url'=>$base.'/uploads/site/'.$name],201);
}
