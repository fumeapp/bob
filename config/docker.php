<?php
return [
    'binary' => env('DOCKER_BINARY', '/usr/local/bin/docker'),
    'socket' => env('DOCKER_SOCKET', '/var/run/docker.sock'),
];
