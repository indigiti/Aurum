<?php
declare(strict_types=1);
namespace Aurum;
final class Accounts {
    public static function scope(string $uid,string $id='primary'): string {
        if(!preg_match('/^[a-f0-9]{64}$/D',$uid)||!preg_match('/^(primary|a[1-4])$/D',$id))throw new \InvalidArgumentException('Invalid account');
        return 'user-'.$uid.($id==='primary'?'':'-'.$id);
    }
    public static function authorize(Storage $store,string $uid,IdentityPolicy $policy): array {
        $u=$store->transaction(self::scope($uid),static fn(array &$s)=>$s['user']??null);
        if(!is_array($u)||!isset($u['hash'],$u['email'])||($policy->requiresVerification($u['email'])&&!Identity::verified($u)))throw new \InvalidArgumentException('Sign in with verified identity');
        return $u;
    }
    public static function listing(Storage $store,string $uid): array {
        $out=[];
        foreach($store->all(self::scope($uid)) as $scope=>$s){
            if(!is_array($s['account']??null))continue;
            $id=$scope===self::scope($uid)?'primary':substr($scope,-2);
            if(!preg_match('/^(primary|a[1-4])$/D',$id)||self::scope($uid,$id)!==$scope)continue;
            $a=$s['account'];$out[]=['id'=>$id,'initial'=>$a['initial'],'equity'=>$a['equity'],'phase'=>$a['phase'],'status'=>$a['status']];
        }
        usort($out,static fn($a,$b)=>($a['id']==='primary'?0:(int)substr($a['id'],1))<=>($b['id']==='primary'?0:(int)substr($b['id'],1)));
        return $out;
    }
    public static function create(Storage $store,string $uid,string $email,int $size,array $rules): string {
        // Fixed independently locked slots prevent concurrent requests from exceeding the account cap.
        for($i=1;$i<=4;$i++){
            $id='a'.$i;$created=$store->transaction(self::scope($uid,$id),static function(array &$s)use($uid,$email,$size,$rules){
                if(is_array($s['account']??null))return false;
                $s=['ownerId'=>$uid,'ownerEmail'=>$email,'account'=>Engine::account($size,$rules)];return true;
            });
            if($created)return $id;
        }
        throw new \InvalidArgumentException('Maximum five simulated accounts per trader');
    }
}
