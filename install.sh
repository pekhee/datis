#!/bin/bash


if [[ $EUID -eq 0 ]]; then
  echo "Run as non-root" 2>&1
  exit 1
else

echo "$(whoami)   ALL=NOPASSWD: /bin/date" | sudo tee '/etc/sudoers.d/datis' 1>/dev/null

chmod +x "$PWD/datis.php"

sudo ln -sf "$PWD/datis.php" "/usr/local/bin/datish"

fi

echo "Install completed."
echo "See config.ini for configurations."
echo "Run 'datish help' for more information."
