<?php require 'common.php';admin_required();if($_SERVER['REQUEST_METHOD']!=='POST'){http_response_code(405);exit;}check_csrf();$id=filter_var($_POST['vehicle_id']??null,FILTER_VALIDATE_INT);if(!$id){http_response_code(400);exit('ID inválido');}$s=db()->prepare('SELECT id FROM vehicles WHERE id=?');$s->execute([$id]);if(!$s->fetch()){http_response_code(404);exit('Veículo não encontrado');}$action=$_POST['action']??'';$count=db()->prepare('SELECT COUNT(*) FROM photos WHERE vehicle_id=?');$count->execute([$id]);$n=(int)$count->fetchColumn();
if($action==='upload'){
 if($n>=12){http_response_code(400);exit('Limite de 12 fotos por veículo.');}
 $file=$_FILES['photo']??null;if(!$file||$file['error']!==UPLOAD_ERR_OK||$file['size']>5242880||$file['size']<=0||!is_uploaded_file($file['tmp_name'])){http_response_code(400);exit('Upload inválido ou maior que 5 MB.');}
 $mime=(new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);$types=['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'];if(!isset($types[$mime])||@getimagesize($file['tmp_name'])===false){http_response_code(400);exit('Formato de imagem inválido.');}
 $info=@getimagesize($file['tmp_name']);if(($info[0]??0)>8000||($info[1]??0)>8000){http_response_code(400);exit('Resolução excessiva.');}
 $path='uploads/'.bin2hex(random_bytes(16)).'.'.$types[$mime];$full=dirname(__DIR__).'/'.$path;
 // Reprocessa a imagem com GD para descartar metadados e conteúdo extra.
 if(!extension_loaded('gd')){http_response_code(500);exit('Ative a extensão GD no php.ini.');}
 $load=match($mime){'image/jpeg'=>'imagecreatefromjpeg','image/png'=>'imagecreatefrompng','image/webp'=>'imagecreatefromwebp'};
 $save=match($mime){'image/jpeg'=>'imagejpeg','image/png'=>'imagepng','image/webp'=>'imagewebp'};
 $im=@$load($file['tmp_name']);if(!$im){http_response_code(400);exit('Imagem corrompida.');}
 $ok=$mime==='image/jpeg'?$save($im,$full,85):$save($im,$full);imagedestroy($im);if(!$ok){http_response_code(500);exit('Não foi possível salvar a imagem.');}
 @chmod($full,0644);$s=db()->prepare('INSERT INTO photos(vehicle_id,path,position) VALUES(?,?,?)');$s->execute([$id,$path,$n+1]);admin_log('upload-photo',$path);
}elseif($action==='remove'||$action==='cover'){
 $pid=filter_var($_POST['photo_id']??null,FILTER_VALIDATE_INT);$s=db()->prepare('SELECT * FROM photos WHERE id=? AND vehicle_id=?');$s->execute([$pid,$id]);$p=$s->fetch();if(!$p){http_response_code(404);exit('Foto não encontrada');}
 if($action==='remove') {db()->prepare('DELETE FROM photos WHERE id=?')->execute([$pid]);if(str_starts_with($p['path'],'uploads/'))@unlink(dirname(__DIR__).'/'.$p['path']);admin_log('remove-photo',(string)$pid);}
 else{db()->prepare('UPDATE photos SET position=position+1 WHERE vehicle_id=?')->execute([$id]);db()->prepare('UPDATE photos SET position=0 WHERE id=?')->execute([$pid]);admin_log('cover-photo',(string)$pid);}
}else{http_response_code(400);exit('Ação inválida');}
go('edit.php?id='.$id);
