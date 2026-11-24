<?php
declare(strict_types=1);
namespace Aurum;
interface Storage {
    public function transaction(string $scope, callable $fn): mixed;
    public function all(string $prefix='user-'): array;
}
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
final class MariaDbStorage implements Storage {
    public function __construct(private \PDO $db) {}
    public function transaction(string $scope, callable $fn): mixed {
        if(!preg_match('/^[a-z0-9_-]{1,90}$/D',$scope))throw new \InvalidArgumentException('Invalid storage scope');
        $this->db->beginTransaction();
        try{
            $this->db->prepare("INSERT IGNORE INTO aurum_state_v2(scope,payload) VALUES (?,?)")->execute([$scope,'{"user":null,"account":null}']);
            $stmt=$this->db->prepare('SELECT payload FROM aurum_state_v2 WHERE scope=? FOR UPDATE');$stmt->execute([$scope]);
            $raw=$stmt->fetchColumn();if(!is_string($raw))throw new \RuntimeException('Database state unavailable');
            $s=json_decode($raw,true,512,JSON_THROW_ON_ERROR);$result=$fn($s);
            $this->db->prepare('UPDATE aurum_state_v2 SET payload=?,updated_at=CURRENT_TIMESTAMP WHERE scope=?')->execute([json_encode($s,JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES),$scope]);
            if(is_array($s['account']??null)&&str_starts_with($scope,'user-'))$this->syncFinancialRows($scope,$s['account']);
            $this->db->commit();return $result;
        }catch(\Throwable $e){if($this->db->inTransaction())$this->db->rollBack();throw $e;}
    }
    private function syncFinancialRows(string $scope,array $a): void {
        $this->db->prepare('INSERT INTO aurum_accounts(scope,initial_cents,balance_cents,equity_cents,phase,status) VALUES (?,?,?,?,?,?) ON DUPLICATE KEY UPDATE initial_cents=VALUES(initial_cents),balance_cents=VALUES(balance_cents),equity_cents=VALUES(equity_cents),phase=VALUES(phase),status=VALUES(status)')->execute([$scope,$a['initial'],$a['balance'],$a['equity'],$a['phase'],$a['status']]);
        $ledger=$this->db->prepare('INSERT INTO aurum_ledger(scope,event_id,event_type,delta_cents,balance_cents,phase,event_ms,reference_id) VALUES (?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE event_id=VALUES(event_id)');
        foreach($a['ledger']??[] as $l)$ledger->execute([$scope,$l['id'],$l['type'],$l['delta'],$l['balance'],$l['phase'],$l['timestamp'],$l['reference']??null]);
        $fills=$this->db->prepare('INSERT INTO aurum_fills(scope,fill_id,position_id,side,units,entry_cents,exit_cents,pnl_cents,closed_ms,reason) VALUES (?,?,?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE fill_id=VALUES(fill_id)');
        foreach($a['journal']??[] as $i=>$f)$fills->execute([$scope,$f['fillId']??hash('sha256',json_encode([$i,$f],JSON_THROW_ON_ERROR)),$f['id'],$f['side'],$f['units'],$f['entry'],$f['exit'],$f['pnl'],$f['closedAt'],$f['reason']]);
        $this->db->prepare('DELETE FROM aurum_open_positions WHERE scope=?')->execute([$scope]);
        $pos=$this->db->prepare('INSERT INTO aurum_open_positions(scope,position_id,side,units,entry_cents,sl_cents,tp_cents,opened_ms) VALUES (?,?,?,?,?,?,?,?)');
        foreach($a['positions']??[] as $p)$pos->execute([$scope,$p['id'],$p['side'],$p['units'],$p['entry'],$p['sl'],$p['tp'],$p['openedAt']]);
        $this->db->prepare('DELETE FROM aurum_pending_orders WHERE scope=?')->execute([$scope]);
        $pending=$this->db->prepare('INSERT INTO aurum_pending_orders(scope,order_id,order_type,units,trigger_cents,sl_cents,tp_cents,created_ms) VALUES (?,?,?,?,?,?,?,?)');
        foreach($a['pending']??[] as $p)$pending->execute([$scope,$p['id'],$p['type'],$p['units'],$p['price'],$p['sl']===null||$p['sl']===''?null:(int)round($p['sl']*100),$p['tp']===null||$p['tp']===''?null:(int)round($p['tp']*100),$p['createdAt']]);
    }
    public function all(string $prefix='user-'): array {
        $stmt=$this->db->prepare('SELECT scope,payload FROM aurum_state_v2 WHERE scope LIKE ?');$stmt->execute([$prefix.'%']);$out=[];
        while($row=$stmt->fetch(\PDO::FETCH_ASSOC))$out[$row['scope']]=json_decode($row['payload'],true,512,JSON_THROW_ON_ERROR);
        return $out;
    }
}
