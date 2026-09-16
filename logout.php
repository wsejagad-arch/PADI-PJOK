<?php
require_once 'auth.php';
padi_mulai_sesi();
session_destroy();
padi_kembali('index.php', 'Anda telah berhasil keluar.');
