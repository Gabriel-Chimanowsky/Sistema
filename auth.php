<?php
session_start();

function checkAuth() {
    if (!isset($_SESSION['usuario_id'])) {
        header("Location: login.php");
        exit;
    }
}

function isLoggedIn() {
    return isset($_SESSION['usuario_id']);
}
