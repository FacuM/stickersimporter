#!/bin/bash

terminate() {
    echo 'SIGTERM detected!';

    killall5;
}

trap terminate SIGTERM;

composer install && php listen.php &

# Run server asynchronously and listen for traps
wait $!;