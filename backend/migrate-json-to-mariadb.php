<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
$root=getenv('AURUM_PRIVATE_PATH')?:dirname(__DIR__);
$dir=rtrim($root,'/').'/private-storage';$dsn=getenv('DB_DSN');
if(!$dsn||!str_starts_with($dsn,'mysql:')){fwrite(STDERR,"Set DB_DSN for MariaDB\n");exit(2);}
try{
    $pdo=new PDO($dsn,getenv('DB_USER')?:'',getenv('DB_PASSWORD')?:'',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false]);
    $pdo->exec("CREATE TABLE IF NOT EXISTS aurum_state_v2(scope VARCHAR(90) NOT NULL PRIMARY KEY,payload JSON NOT NULL,updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $records=[];$legacy=$dir.'/state.json';
    if(is_file($legacy)){
        $old=json_decode((string)file_get_contents($legacy),true,512,JSON_THROW_ON_ERROR);
        foreach(($old['users']??[]) as $uid=>$user)$records['user-'.$uid]=['user'=>$user,'account'=>$old['accounts'][$uid]??null];
    }
    foreach(glob($dir.'/user-*.json')?:[] as $path){
        $scope=basename($path,'.json');if(!preg_match('/^user-[a-f0-9]{64}$/D',$scope))continue;
        $records[$scope]=json_decode((string)file_get_contents($path),true,512,JSON_THROW_ON_ERROR);
    }
    if(!$records){fwrite(STDERR,"No account JSON records found in {$dir}\n");exit(3);}
    $backup=$dir.'/backups';if(!is_dir($backup)&&!mkdir($backup,0700,true)&&!is_dir($backup))throw new RuntimeException('Backup directory unavailable');
    if(is_file($legacy)&&!copy($legacy,$backup.'/state-'.gmdate('Ymd-His').'.json'))throw new RuntimeException('Could not back up legacy state');
    $insert=$pdo->prepare('INSERT IGNORE INTO aurum_state_v2(scope,payload) VALUES (?,?)');$check=$pdo->prepare('SELECT payload FROM aurum_state_v2 WHERE scope=?');
    $pdo->beginTransaction();$added=0;
    foreach($records as $scope=>$state){
        $payload=json_encode($state,JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES);$insert->execute([$scope,$payload]);
        if($insert->rowCount()===1){$added++;continue;}
        $check->execute([$scope]);$existing=$check->fetchColumn();
        if(!is_string($existing)||json_decode($existing,true)!==$state)throw new RuntimeException("Existing MariaDB record differs for {$scope}; refusing overwrite");
    }
    $pdo->commit();$verify=(int)$pdo->query('SELECT COUNT(*) FROM aurum_state_v2')->fetchColumn();
    printf("Migration verified. Source records: %d; inserted: %d; destination rows: %d. JSON source retained.\n",count($records),$added,$verify);
}catch(Throwable $e){if(isset($pdo)&&$pdo->inTransaction())$pdo->rollBack();fwrite(STDERR,"Migration failed: ".$e->getMessage()."\n");exit(1);}
