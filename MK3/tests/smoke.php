<?php
$base=dirname(__DIR__);$files=['index.php','veiculo.php','instalar.php','admin/index.php','admin/edit.php','admin/photo.php','admin/login.php','app/core.php'];$fail=0;foreach($files as $file){$txt=file_get_contents($base.'/'.$file);if(!str_contains($txt,'<?php')){$fail++;echo "FAIL PHP $file\n";}else echo "OK PHP $file\n";}
foreach(['app/.htaccess','uploads/.htaccess','database.sql','assets/site.js','assets/style.css'] as $file){if(!is_file($base.'/'.$file)){$fail++;echo "FAIL missing $file\n";}else echo "OK $file\n";}exit($fail?1:0);
