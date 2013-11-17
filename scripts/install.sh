#!/bin/bash

if [[ $EUID -eq 0 ]]; then
  echo "Run as non-root" 2>&1
  exit 1
fi

echo "$(whoami)   ALL=NOPASSWD: /bin/date" | sudo tee '/etc/sudoers.d/datis' 1>/dev/null
sudo chmod 440 '/etc/sudoers.d/datis'
chmod +x "$PWD/datis.php"
sudo ln -sf "$PWD/datis.php" "/usr/local/bin/datish"

cp "$PWD/config.ini.template" "$PWD/config.ini"

echo "Install completed."
echo "$EDITOR $PWD/config.ini"
echo "Run 'datish help' for more information."
