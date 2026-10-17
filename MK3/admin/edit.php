<?php require 'common.php';admin_required();$id=filter_input(INPUT_GET,'id',FILTER_VALIDATE_INT)?:0;$v=null;if($id){$s=db()->prepare('SELECT * FROM vehicles WHERE id=?');$s->execute([$id]);$v=$s->fetch();if(!$v){http_response_code(404);exit('Veículo não encontrado');}}$error='';
if($_SERVER['REQUEST_METHOD']==='POST'){check_csrf();$action=$_POST['action']??'save';if($action==='save'){
$title=trim((string)($_POST['title']??''));$brand=trim((string)($_POST['brand']??''));$model=trim((string)($_POST['model']??''));$priceRaw=trim((string)($_POST['price']??''));$price=$priceRaw===''?null:filter_var($priceRaw,FILTER_VALIDATE_FLOAT);
$kmRaw=trim((string)($_POST['mileage']??''));$km=$kmRaw===''?null:filter_var($kmRaw,FILTER_VALIDATE_INT,['options'=>['min_range'=>0]]);
$status=$_POST['status']??'available';if(!in_array($status,['available','sold','hidden'],true))$status='hidden';
$year=trim((string)($_POST['year_model']??''));$fuel=trim((string)($_POST['fuel']??''));$gear=trim((string)($_POST['transmission']??''));$color=trim((string)($_POST['color']??''));$desc=trim((string)($_POST['description']??''));$featured=isset($_POST['featured'])?1:0;
if($title===''||mb_strlen($title)>160||mb_strlen($brand)>100||mb_strlen($model)>120||mb_strlen($year)>20||mb_strlen($fuel)>60||mb_strlen($gear)>60||mb_strlen($color)>60||mb_strlen($desc)>8000||($priceRaw!==''&&($price===false||$price<0||$price>9999999999))||($kmRaw!==''&&$km===false)){$error='Verifique os campos: título obrigatório, limites de texto e números válidos.';}else{
$values=[$title,$brand,$model,$year?:null,$km,$gear?:null,$fuel?:null,$color?:null,$price,$desc,$status,$featured];
if($id){$s=db()->prepare('UPDATE vehicles SET title=?,brand=?,model=?,year_model=?,mileage=?,transmission=?,fuel=?,color=?,price=?,description=?,status=?,featured=? WHERE id=?');$s->execute([...$values,$id]);}else{$s=db()->prepare('INSERT INTO vehicles(title,brand,model,year_model,mileage,transmission,fuel,color,price,description,status,featured) VALUES(?,?,?,?,?,?,?,?,?,?,?,?)');$s->execute($values);$id=(int)db()->lastInsertId();}
admin_log('save-vehicle',(string)$id);go('edit.php?id='.$id.'&saved=1');}}
}
if($id){$s=db()->prepare('SELECT * FROM vehicles WHERE id=?');$s->execute([$id]);$v=$s->fetch();}$v=$v?:[];admin_top($id?'Editar veículo':'Novo veículo');if($error)echo '<div class="alert">'.h($error).'</div>';if(isset($_GET['saved']))echo '<div class="alert ok">Dados salvos.</div>';?>
<form method="post" class="userbox"><input type="hidden" name="action" value="save"><?=token_field()?><div class="form-grid">
<label>Título do anúncio *<input name="title" required maxlength="160" value="<?=h($v['title']??'')?>"></label>
<label>Marca<input name="brand" maxlength="100" value="<?=h($v['brand']??'')?>"></label>
<label>Modelo<input name="model" maxlength="120" value="<?=h($v['model']??'')?>"></label>
<label>Ano / Modelo<input name="year_model" maxlength="20" placeholder="2020/2021" value="<?=h($v['year_model']??'')?>"></label>
<label>Quilometragem<input type="number" min="0" name="mileage" value="<?=h($v['mileage']??'')?>"></label>
<label>Preço (R$; use ponto decimal)<input type="number" step="0.01" min="0" name="price" value="<?=h($v['price']??'')?>" placeholder="Em branco = sob consulta"></label>
<label>Câmbio<input name="transmission" maxlength="60" value="<?=h($v['transmission']??'')?>"></label>
<label>Combustível<input name="fuel" maxlength="60" value="<?=h($v['fuel']??'')?>"></label>
<label>Cor<input name="color" maxlength="60" value="<?=h($v['color']??'')?>"></label>
<label>Status<select name="status"><option value="available" <?=($v['status']??'available')==='available'?'selected':''?>>Disponível</option><option value="sold" <?=($v['status']??'')==='sold'?'selected':''?>>Vendido</option><option value="hidden" <?=($v['status']??'')==='hidden'?'selected':''?>>Oculto</option></select></label>
<label style="grid-column:1/-1">Descrição<textarea name="description" maxlength="8000" rows="5"><?=h($v['description']??'')?></textarea></label>
<label><span>Exibir no carrossel da página inicial</span><span><input type="checkbox" name="featured" value="1" <?=!empty($v['featured'])?'checked':''?>> Sim, destacar</span></label>
</div><p><button class="button red" type="submit">SALVAR VEÍCULO</button></p></form>
<?php if($id):$photos=car_photos($id);?><div class="userbox"><h2>Fotos do veículo</h2><p class="fineprint">JPG, PNG ou WebP; até 5 MB cada. Envie até 12 fotos. A primeira foto é a capa.</p><form method="post" action="photo.php" enctype="multipart/form-data"><?=token_field()?><input type="hidden" name="vehicle_id" value="<?=$id?>"><input type="hidden" name="action" value="upload"><input type="file" name="photo" accept="image/jpeg,image/png,image/webp" required><button class="button-sm">Adicionar foto</button></form>
<div class="gallery" style="margin-top:20px"><?php foreach($photos as $p):?><div><img src="../<?=h(photo_path($p['path']))?>" alt="Foto do veículo"><div class="photo-actions"><form method="post" action="photo.php"><?=token_field()?><input type="hidden" name="vehicle_id" value="<?=$id?>"><input type="hidden" name="photo_id" value="<?=$p['id']?>"><input type="hidden" name="action" value="cover"><button class="button-sm" type="submit">Tornar capa</button></form><form method="post" action="photo.php" onsubmit="return confirm('Remover esta foto?')"><?=token_field()?><input type="hidden" name="vehicle_id" value="<?=$id?>"><input type="hidden" name="photo_id" value="<?=$p['id']?>"><input type="hidden" name="action" value="remove"><button class="button-sm danger" type="submit">Remover</button></form></div></div><?php endforeach;?></div></div><?php endif;?>
<?php admin_bottom(); ?>
