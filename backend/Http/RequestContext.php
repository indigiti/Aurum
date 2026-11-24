<?php
declare(strict_types=1);
namespace Aurum;
final class RequestContext {
    public static function requestedId(): string {
        $id=$_SERVER['HTTP_X_AURUM_ACCOUNT']??'primary';
        if(!is_string($id)||!preg_match('/^(primary|a[1-4])$/D',$id))throw new \InvalidArgumentException('Invalid account');
        return $id;
    }
}
