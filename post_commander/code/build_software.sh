#!/bin/bash
sudo ./build_controller.sh
sudo ./build_wm.sh
sudo gcc -o command_executor_unix command_executor_unix.c -ljson-c -Wall
