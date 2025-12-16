#!/bin/bash

# MPMをすべて無効化してから prefork を有効化
a2dismod mpm_event || true
a2dismod mpm_worker || true
a2dismod mpm_prefork || true
a2enmod mpm_prefork

# Apacheを起動
exec apache2ctl -D FOREGROUND
