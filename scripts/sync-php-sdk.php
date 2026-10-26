<?php
declare(strict_types=1);

// Deterministic source-copy maintenance; no package publication or runtime edit.
$root=dirname(__DIR__);$source=$root.'/packages/php-sdk';
$write=in_array('--write',$argv,true);
$targets=[
    'plugins/woocommerce/sdk/payxcommerce-php',
    'plugins/opencart3/upload/system/library/payxcommerce-sdk',
    'plugins/opencart4/upload/extension/payxcommerce/system/library/payxcommerce-sdk',
    'plugins/magento2/Model/Api/sdk',
];
$files=['composer.json','autoload.php'];
$iterator=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($source.'/src',FilesystemIterator::SKIP_DOTS));
foreach($iterator as$file){
    if($file->isLink()||!$file->isFile()||$file->getExtension()!=='php')throw new RuntimeException('Unexpected SDK source');
    $files[]=substr($file->getPathname(),strlen($source)+1);
}
sort($files);$mismatch=0;
$transport=['autoload.php','src/Http/HttpClientInterface.php','src/Http/CurlHttpClient.php','src/Http/ResponseMetadata.php',
    'src/Exceptions/ApiException.php','src/Exceptions/AuthException.php','src/Exceptions/RateLimitException.php','src/Exceptions/ValidationException.php'];
foreach($targets as$target){
    $selected=str_contains($target,'opencart')?$transport:$files;
    // Remove only previously generated canonical files, never an unknown file.
    foreach(array_diff($files,$selected)as$file){
        $extra=$root.'/'.$target.'/'.$file;
        if(!is_file($extra))continue;
        if(hash_file('sha256',$extra)!==hash_file('sha256',$source.'/'.$file))throw new RuntimeException('Unexpected SDK copy; preserve and review.');
        $mismatch++;if($write&&!unlink($extra))throw new RuntimeException('Cannot remove surplus generated SDK file');
    }
    foreach($selected as$file){
    $path=$root.'/'.$target.'/'.$file;$contents=file_get_contents($source.'/'.$file);
    if(is_file($path)&&hash_file('sha256',$path)===hash('sha256',$contents))continue;
    $mismatch++;
    if($write){if(!is_dir(dirname($path))&&!mkdir(dirname($path),0755,true))throw new RuntimeException('Cannot create SDK copy');
        if(file_put_contents($path,$contents)!==strlen($contents))throw new RuntimeException('Cannot copy SDK source');}
    }
}
if(!$write&&$mismatch)throw new RuntimeException('Embedded SDK copies differ; run scripts/sync-php-sdk.php --write.');
echo count($targets).' embedded PHP SDK copies '.($write?'synchronized':'verified')." (full SDK for Woo/Magento; PHP8.0 transport-only for OpenCart).\n";
