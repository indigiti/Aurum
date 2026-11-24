<?php
declare(strict_types=1);
namespace Aurum;
interface Storage {
    public function transaction(string $scope, callable $fn): mixed;
    public function all(string $prefix='user-'): array;
}
