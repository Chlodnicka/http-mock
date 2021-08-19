<?php

namespace InterNations\Component\HttpMock;

use Symfony\Component\HttpFoundation\Request;

class StorageService
{
    private RequestStorage $storage;

    public function __construct()
    {
        $this->storage = new RequestStorage(getmypid(), __DIR__ . '/../state/');
    }

    public function delete(Request $request, string $name): void
    {
        $this->storage->clear($request, $name);
    }

    public function read(Request $request, string $name)
    {
        return $this->storage->read($request, $name);
    }

    public function store(Request $request, string $name, array $payload): void
    {
        $this->storage->store($request, $name, $payload);
    }

    public function prepend(Request $request, string $name, array $payload): void
    {
        $this->storage->prepend($request, $name, $payload);
    }
}