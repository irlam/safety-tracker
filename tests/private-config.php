<?php
declare(strict_types=1);
$root=dirname(__DIR__);
$file=tempnam(sys_get_temp_dir(),'safety-private-');
try {
 file_put_contents($file, '<?php foreach (["DB_HOST"=>"fixture","DB_PORT"=>3306,"DB_NAME"=>"fixture","DB_USER"=>"fixture","DB_PASS"=>"fixture","TIMEZONE"=>"Europe/London","UPLOAD_DIR"=>SAFETY_APP_ROOT."/uploads","AUTH_MODE"=>"password","AUTH_PASSWORD"=>"fixture"] as $k=>$v) define($k,$v);');
 $run=static function(string $path)use($root):int {
   $command=[PHP_BINARY,'-n','-r','putenv("SAFETY_CONFIG_FILE=" . '.var_export($path,true).'); require '.var_export($root.'/includes/config.php',true).'; if (UPLOAD_DIR !== SAFETY_APP_ROOT . "/uploads") exit(2);'];
   $p=proc_open($command,[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);fclose($pipes[0]);stream_get_contents($pipes[1]);fclose($pipes[1]);stream_get_contents($pipes[2]);fclose($pipes[2]);return proc_close($p);
 };
 if ($run($file)!==0) throw new RuntimeException('Valid private config rejected');
 if ($run($file.'-missing')===0) throw new RuntimeException('Missing private config accepted');
 file_put_contents($file,'<?php /* empty */');
 if ($run($file)===0)throw new RuntimeException('Incomplete private config accepted');
 echo "PASS: Safety private configuration and original upload-path binding; missing/incomplete configuration fails closed.\n";
} finally {@unlink($file);}
