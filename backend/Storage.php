<?php
declare(strict_types=1);
namespace Aurum;
interface Storage { public function transaction(callable $fn): mixed; }
final class JsonStorage implements Storage {
    public function __construct(private string $dir) {}
    public function transaction(callable $fn): mixed {
        if (!is_dir($this->dir) && !@mkdir($this->dir,0700,true) && !is_dir($this->dir)) throw new \RuntimeException('Storage unavailable');
        $path=$this->dir.'/state.json'; $lock=fopen($this->dir.'/state.lock','c');
        if (!$lock) throw new \RuntimeException('Lock unavailable');
        $tmp=null;
        try {
            if (!flock($lock,LOCK_EX)) throw new \RuntimeException('Lock failed');
            $s=is_file($path)?json_decode(file_get_contents($path),true,512,JSON_THROW_ON_ERROR):['users'=>[],'accounts'=>[]];
            $result=$fn($s);
            $tmp=tempnam($this->dir,'state-');
            if ($tmp===false || file_put_contents($tmp,json_encode($s,JSON_THROW_ON_ERROR))===false || !chmod($tmp,0600) || !rename($tmp,$path)) throw new \RuntimeException('Commit failed');
            return $result;
        } finally { if(is_string($tmp)&&is_file($tmp)) unlink($tmp); flock($lock,LOCK_UN); fclose($lock); }
    }
}
final class MariaDbStorage implements Storage {
    public function __construct(private \PDO $db) {}
    public function transaction(callable $fn): mixed {
        $this->db->beginTransaction();
        try {
            $row=$this->db->query('SELECT payload FROM aurum_state WHERE id=1 FOR UPDATE')->fetchColumn();
            if(!is_string($row)) throw new \RuntimeException('Database not initialized');
            $s=json_decode($row,true,512,JSON_THROW_ON_ERROR);
            $result=$fn($s);
            $this->db->prepare('UPDATE aurum_state SET payload=? WHERE id=1')->execute([json_encode($s,JSON_THROW_ON_ERROR)]);
            $this->db->commit();return $result;
        } catch(\Throwable $e){if($this->db->inTransaction())$this->db->rollBack();throw $e;}
    }
}
