<?php


namespace App\Services;

interface BaseServiceInterface
{
    public function exists(array $conditions): bool;

    public function add(array $data): void;

    public function delete(array $conditions): void;
}
