<?php
declare(strict_types=1);

// Build only committed plugin runtime files, never a checkout/working directory.
// Installer layouts deliberately differ between CMS platforms.
$root=dirname(__DIR__);
$run=static function(array $args)use($root):string{
    $process=proc_open(array_merge(['git','-C',$root],$args),[1=>['pipe','w'],2=>['pipe','w']],$pipes);
    if(!is_resource($process))throw new RuntimeException('Cannot start Git');
    $out=stream_get_contents($pipes[1]);$error=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);
    if(proc_close($process)!==0)throw new RuntimeException('Git source read failed');return$out;
};
$revision=$argv[2]??'HEAD';
if($revision!=='HEAD'&&!preg_match('/^[a-f0-9]{40}$/',$revision))throw new RuntimeException('Use a full source commit SHA');
$sha=trim($run(['rev-parse','--verify',$revision.'^{commit}']));
if(!preg_match('/^[a-f0-9]{40}$/',$sha))throw new RuntimeException('Invalid source revision');
$output=$argv[1]??$root.'/dist/public-plugins';
if(!is_dir($output)&&!mkdir($output,0755,true))throw new RuntimeException('Cannot create output directory');
$read=static fn(string $path):string=>$run(['show',$sha.':'.$path]);
preg_match('/\*\s*Version:\s*([0-9.]+)/',$read('plugins/woocommerce/payxcommerce-gateway.php'),$woo);
$oc3=trim($read('plugins/opencart3/VERSION'));
$oc4=json_decode($read('plugins/opencart4/upload/extension/payxcommerce/install.json'),true,512,JSON_THROW_ON_ERROR)['version'];
$magento=json_decode($read('plugins/magento2/composer.json'),true,512,JSON_THROW_ON_ERROR)['version'];
$specs=[
    'woocommerce'=>['WooCommerce',$woo[1]??'','plugins/woocommerce/','payxcommerce/','payxcommerce-woocommerce-'.($woo[1]??'').'.zip',['payxcommerce/payxcommerce-gateway.php','payxcommerce/sdk/payxcommerce-php/src/Client.php']],
    'opencart3'=>['OpenCart 3',$oc3,'plugins/opencart3/upload/','upload/','payxcommerce.ocmod.zip',['upload/system/library/payxcommerce.php','upload/catalog/controller/extension/payment/payxcommerce.php']],
    'opencart4'=>['OpenCart 4',$oc4,'plugins/opencart4/upload/extension/payxcommerce/','','payxcommerce.ocmod.zip',['install.json','admin/controller/payment/payxcommerce.php','system/library/payxcommerce.php']],
    'magento2-app-code'=>['Magento 2 — app/code',$magento,'plugins/magento2/','PayXCommerce/Payment/','payxcommerce-magento2-app-code-'.$magento.'.zip',['PayXCommerce/Payment/registration.php','PayXCommerce/Payment/etc/module.xml']],
    'magento2-composer'=>['Magento 2 — Composer',$magento,'plugins/magento2/','','payxcommerce-magento2-composer-'.$magento.'.zip',['composer.json','registration.php','etc/module.xml']],
];
$catalog=[];
foreach($specs as$id=>[$title,$version,$prefix,$zipPrefix,$filename,$required]){
    if(!preg_match('/^\d+\.\d+\.\d+$/',$version))throw new RuntimeException('Invalid plugin version');
    $dir=$output.'/'.$id;if(!is_dir($dir)&&!mkdir($dir,0755,true))throw new RuntimeException('Cannot create package directory');
    $path=$dir.'/'.$filename;$temporary=$path.'.tmp-'.bin2hex(random_bytes(6));$zip=new ZipArchive();
    if($zip->open($temporary,ZipArchive::CREATE|ZipArchive::EXCL)!==true)throw new RuntimeException('Cannot create archive');
    $expected=[];$uncompressed=0;
    $entries=explode("\n",trim($run(['ls-tree','-r',$sha,'--',$prefix])));
    foreach($entries as$entry){
        if(!preg_match('/^(100644|100755) blob [a-f0-9]+\t(.+)$/',$entry,$m))throw new RuntimeException('Non-regular source entry');
        $source=$m[2];$relative=substr($source,strlen($prefix));
        if(preg_match('~(^|/)(tests?|tools|vendor|node_modules|\.git)(/|$)~i',$relative)||$relative==='README.md')continue;
        if(!str_starts_with($source,$prefix)||str_contains($relative,'..')||!preg_match('/\.(php|json|xml|js|css|png|twig|html)$/i',$relative))throw new RuntimeException('Unexpected source file');
        $contents=$read($source);
        if(preg_match('/(?:glpat-|ghp_|sk_live_|whsec_)[A-Za-z0-9_]{12,}/',$contents)||str_contains($contents,'/srv/payxcommerce-ai/'))throw new RuntimeException('Possible restricted material');
        $name=$zipPrefix.$relative;$expected[$name]=hash('sha256',$contents);$uncompressed+=strlen($contents);
        if(!$zip->addFromString($name,$contents)||!$zip->setMtimeName($name,1704067200)||!$zip->setCompressionName($name,ZipArchive::CM_DEFLATE,9)||!$zip->setExternalAttributesName($name,ZipArchive::OPSYS_UNIX,0100644<<16))throw new RuntimeException('ZIP member failure');
    }
    // Preserve the committed distribution license inside each install layout.
    $license=$read('LICENSE');
    $licenseName=match($id){'opencart3'=>'upload/system/library/payxcommerce-LICENSE.txt','opencart4'=>'system/library/payxcommerce-LICENSE.txt',default=>$zipPrefix.'LICENSE.txt'};
    $expected[$licenseName]=hash('sha256',$license);$uncompressed+=strlen($license);
    if(!$zip->addFromString($licenseName,$license)||!$zip->setMtimeName($licenseName,1704067200)||!$zip->setCompressionName($licenseName,ZipArchive::CM_DEFLATE,9)||!$zip->setExternalAttributesName($licenseName,ZipArchive::OPSYS_UNIX,0100644<<16))throw new RuntimeException('ZIP license member failure');
    $zip->close();$bytes=filesize($temporary);
    if(!$expected||$bytes<100||$bytes>=10*1024*1024||$uncompressed>=10*1024*1024)throw new RuntimeException('Package size guard failed');
    if($zip->open($temporary,ZipArchive::CHECKCONS)!==true||$zip->numFiles!==count($expected))throw new RuntimeException('Archive consistency failed');
    foreach($expected as$name=>$hash){$contents=$zip->getFromName($name);if($contents===false||hash('sha256',$contents)!==$hash)throw new RuntimeException('Source/member mismatch');}
    foreach($required as$name)if(!isset($expected[$name]))throw new RuntimeException('Required installer entry absent');
    $zip->close();if(!rename($temporary,$path))throw new RuntimeException('Atomic package publish failed');
    $catalog[$id]=['title'=>$title,'version'=>$version,'file'=>$id.'/'.$filename,'bytes'=>$bytes,'uncompressed_bytes'=>$uncompressed,'sha256'=>hash_file('sha256',$path),'files'=>count($expected),'source_commit'=>$sha];
}
file_put_contents($output.'/manifest.json',json_encode($catalog,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)."\n");
echo json_encode($catalog,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)."\n";
