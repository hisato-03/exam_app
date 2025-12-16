#!/bin/bash
# MPMの競合を完全に解消
rm -f /etc/apache2/mods-enabled/mpm_event.*
rm -f /etc/apache2/mods-available/mpm_event.*
a2enmod mpm_prefork

# Apacheを起動
exec apache2ctl -D FOREGROUND
