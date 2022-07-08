#!/usr/bin/env bash
# Copy the file to the global command directory eg.: /usr/local/bin
# Rename robo.sh to robo.
# Set your projects directory:
project_home=`echo ~`

dir=$(pwd)
basedir=$dir

if case $dir in "$project_home"*) true;; *) false;; esac; then
  while case $dir in "$project_home"*) true;; *) false;; esac;
  do
    if [ -e "$dir/robo" ]; then
      break
    fi

    dir=${dir%/*}
  done;

  cd $dir
  cmd="./robo";

  $cmd $@

  cd $basedir
fi
