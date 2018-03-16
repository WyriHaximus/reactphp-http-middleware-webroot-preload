#!/bin/bash

function main() {
    php examples/basic.php &
    local pid=$!
    sleep 5
    ./vendor/bin/cigar
    local ec=$?
    kill -9 $pid
    exit $ec
}

main
