<?php
require_once __DIR__ . '/Config/auth.php';

cerrarSesionAdmin();
header('Location: login.php');
exit;
