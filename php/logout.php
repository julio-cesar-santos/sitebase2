<?php
require_once 'auth.php';
logoutUser();
header('Location: index.html'); // Redireciona para a página inicial
exit();
