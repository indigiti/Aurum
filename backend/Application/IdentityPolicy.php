<?php
declare(strict_types=1);
namespace Aurum;
final class IdentityPolicy {
    public function __construct(private string $environment,private bool $required,private array $admins){}
    public function requiresVerification(string $email): bool {
        return $this->environment!=='test'&&($this->environment==='production'||$this->required||in_array($email,$this->admins,true));
    }
    public function admin(array $user): bool {
        return in_array($user['email']??'',$this->admins,true)&&($this->environment==='test'||Identity::verified($user));
    }
}
