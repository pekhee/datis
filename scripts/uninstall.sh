#!/bin/bash

if [[ $EUID -eq 0 ]]; then
  echo "Run as non-root" 2>&1
  exit 1
fi

sudo rm -f '/etc/sudoers.d/datis'
chmod -x "$PWD/datis.php"

sudo rm -f "/usr/local/bin/datish"

echo "Uninstalled"
