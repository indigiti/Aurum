<?php
declare(strict_types=1);
namespace Aurum;
require_once __DIR__.'/../Application/Port/Storage.php';
final class JsonStorage implements Storage {
    public function __construct(private string $dir) {}
    private function path(string $scope): string {
        if(!preg_match('/^[a-z0-9_-]{1,90}$/D',$scope))throw new \InvalidArgumentException('Invalid storage scope');
        return $this->dir.'/'.$scope.'.json';
    }
    public function transaction(string $scope, callable $fn): mixed {
        if(!is_dir($this->dir)&&!@mkdir($this->dir,0700,true)&&!is_dir($this->dir))throw new \RuntimeException('Storage unavailable');
        $path=$this->path($scope);$lock=fopen($path.'.lock','c');
        if(!$lock)throw new \RuntimeException('Lock unavailable');
        $tmp=null;
        try{
            if(!flock($lock,LOCK_EX))throw new \RuntimeException('Lock failed');
            $s=is_file($path)?json_decode((string)file_get_contents($path),true,512,JSON_THROW_ON_ERROR):['user'=>null,'account'=>null];
            $result=$fn($s);
            $tmp=tempnam($this->dir,'state-');
            if($tmp===false||file_put_contents($tmp,json_encode($s,JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES),LOCK_EX)===false||!chmod($tmp,0600)||!rename($tmp,$path))throw new \RuntimeException('Commit failed');
            return $result;
        }finally{if(is_string($tmp)&&is_file($tmp))unlink($tmp);flock($lock,LOCK_UN);fclose($lock);}
    }
    public function all(string $prefix='user-'): array {
        if(!is_dir($this->dir))return [];
        $out=[];
        foreach(glob($this->dir.'/'.$prefix.'*.json')?:[] as $path){
            $scope=basename($path,'.json');
            if(!preg_match('/^[a-z0-9_-]{1,90}$/D',$scope))continue;
            $lock=fopen($path.'.lock','c');if(!$lock)continue;
            try{if(!flock($lock,LOCK_SH))continue;$raw=file_get_contents($path);if($raw!==false)$out[$scope]=json_decode($raw,true,512,JSON_THROW_ON_ERROR);}
            finally{flock($lock,LOCK_UN);fclose($lock);}
        }
        return $out;
    }
}
